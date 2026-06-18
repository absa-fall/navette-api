<?php

namespace App\Http\Controllers;

use App\Models\VoyageEtude;
use App\Models\VoyageEtudeBeneficiaire;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\VoyageEtudeAvis;
use App\Models\VoyageEtudeJustificatif;

class VoyageEtudeController extends Controller
{
    // ============================================
    // VICE-RECTEUR — Publier liste bénéficiaires
    // → Notifie les CHEFS DE DÉPARTEMENT (pas les enseignants directement)
    // ============================================
    public function publierListe(Request $request)
    {
        $request->validate([
            'destination'   => 'required|string',
            'date_debut'    => 'required|date',
            'date_fin'      => 'required|date|after:date_debut',
            'description'   => 'nullable|string',
            'enseignants'   => 'required|array',
            'enseignants.*' => 'exists:users,id',
        ]);

        $voyage = VoyageEtude::create([
            'vice_recteur_id' => auth()->id(),
            'destination'     => $request->destination,
            'date_debut'      => $request->date_debut,
            'date_fin'        => $request->date_fin,
            'description'     => $request->description,
            'statut_liste'    => 'publiee',
        ]);

        foreach ($request->enseignants as $enseignantId) {
            VoyageEtudeBeneficiaire::create([
                'voyage_id'     => $voyage->id,
                'enseignant_id' => $enseignantId,
            ]);
        }

        // Notifier les Chefs de Département (pas les enseignants directement)
        $chefsDept = User::where('role', 'chef_departement')->get();
        foreach ($chefsDept as $chef) {
            Notification::create([
                'user_id'  => $chef->id,
                'type'     => 'voyage_etude_publie',
                'titre'    => 'Nouvelle liste de voyage d\'etudes publiee',
                'message'  => 'Le Vice-Recteur a publie une liste de beneficiaires pour le voyage a ' . $request->destination . '. Veuillez informer les enseignants concernes et recueillir leurs justificatifs.',
                'lu'       => false,
            ]);
        }

        return response()->json([
            'message' => 'Liste publiee avec succes',
            'voyage'  => $voyage->load('beneficiaires.enseignant'),
        ], 201);
    }

    // ============================================
    // CHEF DE DÉPARTEMENT — Notifier les enseignants concernés
    // → Après réception de la liste du VR
    // ============================================
    public function notifierEnseignants($voyageId)
    {
        $voyage = VoyageEtude::with('beneficiaires.enseignant')->findOrFail($voyageId);

        foreach ($voyage->beneficiaires as $beneficiaire) {
            Notification::create([
                'user_id'  => $beneficiaire->enseignant_id,
                'type'     => 'voyage_etude_publie',
                'titre'    => 'Vous etes beneficiaire d\'un voyage d\'etudes',
                'message'  => 'Vous avez ete selectionne pour le voyage a ' . $voyage->destination . '. Soumettez vos justificatifs (rapport de dernier voyage + autres pieces) a votre Chef de Departement.',
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Enseignants notifies avec succes']);
    }

    // ============================================
    // ENSEIGNANT — Voir ses voyages
    // ============================================
    public function mesVoyages()
    {
        $user = auth()->user();

        $beneficiaires = VoyageEtudeBeneficiaire::where('enseignant_id', $user->id)
            ->with(['voyage.viceRecteur', 'justificatifs'])
            ->latest()
            ->get();

        return response()->json($beneficiaires);
    }

    // ============================================
    // ENSEIGNANT — Soumettre justificatifs au Chef de Département
    // ============================================
    public function soumettreJustificatifs(Request $request, $beneficiaireId)
    {
        $request->validate([
            'justificatifs'   => 'required|array|min:1|max:5',
            'justificatifs.*' => 'file|mimes:pdf|max:10240',
        ]);

        $beneficiaire = VoyageEtudeBeneficiaire::where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->firstOrFail();

        foreach ($request->file('justificatifs') as $fichier) {
            $path = $fichier->store('justificatifs', 'public');

            VoyageEtudeJustificatif::create([
                'beneficiaire_id' => $beneficiaire->id,
                'fichier_pdf'     => $path,
                'nom_original'    => $fichier->getClientOriginalName(),
            ]);
        }

        $beneficiaire->update([
            'statut_justificatif' => 'soumis',
        ]);

        // Notifier le Chef de Département (pas le VR directement)
        $chefsDept = User::where('role', 'chef_departement')->get();
        foreach ($chefsDept as $chef) {
            Notification::create([
                'user_id'  => $chef->id,
                'type'     => 'justificatif_soumis',
                'titre'    => 'Justificatifs recus d\'un enseignant',
                'message'  => auth()->user()->prenom . ' ' . auth()->user()->nom . ' a soumis ses justificatifs pour le voyage a ' . $beneficiaire->voyage->destination . '. Veuillez les verifier et les transmettre au Vice-Recteur.',
                'lu'       => false,
            ]);
        }

        return response()->json([
            'message'      => 'Justificatifs soumis avec succes au Chef de Departement',
            'beneficiaire' => $beneficiaire->load('justificatifs'),
        ]);
    }

    // ============================================
    // CHEF DE DÉPARTEMENT — Voir les dossiers reçus des enseignants
    // ============================================
    public function dossiersDepartement()
    {
        $user = auth()->user();

        if ($user->role === 'directeur_ufr') {
            // Directeur UFR voit les demandes d'autorisation de sortie
            $dossiers = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs'])
                ->where('statut_autorisation', 'envoye_directeur_ufr')
                ->latest()
                ->get();
        } else {
            // Chef Département voit uniquement les bénéficiaires de son UFR
            $chefUFR = auth()->user()->ufr;
            $dossiers = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs'])
                ->whereHas('voyage', function ($q) {
                    $q->whereIn('statut_liste', ['publiee', 'definitive']);
                })
                ->whereHas('enseignant', function ($q) use ($chefUFR) {
                    $q->where('ufr', $chefUFR);
                })
                ->latest()
                ->get();
        }

        return response()->json($dossiers);
    }

    // ============================================
    // CHEF DE DÉPARTEMENT — Transmettre dossier au VR + Commission
    // ============================================
    public function envoyerAuVR($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['voyage', 'enseignant'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_justificatif' => 'transmis_vr',
        ]);

        $nomEnseignant = $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom;
        $destination   = $beneficiaire->voyage->destination;

        try {
            $vr = User::where('role', 'vice_recteur')->first();
            if ($vr) {
                Notification::create([
                    'user_id' => $vr->id,
                    'type'    => 'dossier_recu',
                    'titre'   => 'Dossier recu du Chef de Departement',
                    'message' => 'Le dossier de ' . $nomEnseignant . ' pour le voyage a ' . $destination . ' a ete transmis.',
                    'lu'      => false,
                ]);
            }

            $commission = User::where('role', 'commission')->get();
            foreach ($commission as $membre) {
                Notification::create([
                    'user_id' => $membre->id,
                    'type'    => 'dossier_a_valider',
                    'titre'   => 'Dossier a valider',
                    'message' => 'Le dossier de ' . $nomEnseignant . ' pour le voyage a ' . $destination . ' est disponible.',
                    'lu'      => false,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Notification envoyerAuVR: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Dossier transmis au Vice-Recteur et a la Commission']);
    }

    // ============================================
    // VR + COMMISSION — Voir dossiers à valider
    // ============================================
    public function dossiersAValider()
    {
        $dossiers = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs', 'avis.user'])
            ->whereIn('statut_justificatif', ['transmis_vr', 'valide', 'incomplet'])
            ->latest()
            ->get();

        return response()->json($dossiers);
    }

    // ============================================
    // VR + COMMISSION — Donner un avis sur un dossier
    // ============================================
    public function donnerAvis(Request $request, $beneficiaireId)
    {
        $request->validate([
            'avis'        => 'required|in:valide,rejete',
            'commentaire' => 'nullable|string',
        ]);

        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])
            ->findOrFail($beneficiaireId);

        VoyageEtudeAvis::updateOrCreate(
            [
                'beneficiaire_id' => $beneficiaire->id,
                'user_id'         => auth()->id(),
            ],
            [
                'avis'        => $request->avis,
                'commentaire' => $request->commentaire,
            ]
        );

        $auteur      = auth()->user();
        $destination = $beneficiaire->voyage->destination;
        $nomEns      = $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom;

        // Si c'est la commission qui donne l'avis → notifier le VR
        if ($auteur->role === 'commission') {
            try {
                $vr = User::where('role', 'vice_recteur')->first();
                if ($vr) {
                    $avisLabel = $request->avis === 'valide' ? 'valide' : 'rejete';
                    Notification::create([
                        'user_id' => $vr->id,
                        'type'    => 'avis_commission',
                        'titre'   => 'Avis de la commission',
                        'message' => 'La commission a ' . $avisLabel . ' le dossier de ' . $nomEns . ' pour le voyage a ' . $destination . '.' . ($request->commentaire ? ' Commentaire : ' . $request->commentaire : ''),
                        'lu'      => false,
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Notification avis commission: ' . $e->getMessage());
            }
        }

        // Marquer comme rejeté si avis défavorable (seulement le VR peut valider définitivement)
        if ($request->avis === 'rejete' && $auteur->role === 'vice_recteur') {
            $beneficiaire->update(['statut_justificatif' => 'incomplet']);
            try {
                Notification::create([
                    'user_id' => $beneficiaire->enseignant_id,
                    'type'    => 'dossier_rejete',
                    'titre'   => 'Dossier incomplet',
                    'message' => 'Votre dossier pour le voyage a ' . $destination . ' a ete juge incomplet par le Vice-Recteur. Veuillez completer vos justificatifs aupres de votre Chef de Departement.',
                    'lu'      => false,
                ]);
            } catch (\Exception $e) {
                \Log::error('Notification rejet VR: ' . $e->getMessage());
            }
        }

        // Si VR valide → statut valide
        if ($request->avis === 'valide' && $auteur->role === 'vice_recteur') {
            $beneficiaire->update(['statut_justificatif' => 'valide']);
        }

        return response()->json([
            'message' => 'Avis enregistre',
            'avis'    => $beneficiaire->load('avis.user'),
        ]);
    }

    // ============================================
    // VICE-RECTEUR — Publier liste définitive + envoyer au Recteur
    // ============================================
    public function publierListeDefinitive(Request $request, $voyageId)
    {
        $request->validate([
            'beneficiaires'   => 'required|array',
            'beneficiaires.*' => 'exists:voyage_etude_beneficiaires,id',
        ]);

        $voyage = VoyageEtude::findOrFail($voyageId);

        VoyageEtudeBeneficiaire::where('voyage_id', $voyageId)
            ->update(['dans_liste_definitive' => false]);

        VoyageEtudeBeneficiaire::whereIn('id', $request->beneficiaires)
            ->update(['dans_liste_definitive' => true]);

        $voyage->update(['statut_liste' => 'definitive']);

        // Notifier le Recteur pour signer l'arrêté
        $recteur = User::where('role', 'recteur')->first();
        if ($recteur) {
            Notification::create([
                'user_id'  => $recteur->id,
                'type'     => 'liste_definitive',
                'titre'    => 'Liste definitive a signer',
                'message'  => 'La liste definitive du voyage a ' . $voyage->destination . ' a ete validee par le Vice-Recteur et sa commission. Veuillez signer l\'arrete.',
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Liste definitive publiee et envoyee au Recteur']);
    }

    // ============================================
    // RECTEUR — Signer l'arrêté → notifie le VR
    // ============================================
    public function signerArrete($voyageId)
    {
        $voyage = VoyageEtude::findOrFail($voyageId);
        $voyage->update(['arrete_recteur' => true]);

        // Notifier le VR
        $vr = User::where('role', 'vice_recteur')->first();
        if ($vr) {
            Notification::create([
                'user_id'  => $vr->id,
                'type'     => 'arrete_signe',
                'titre'    => 'Arrete signe par le Recteur',
                'message'  => 'L\'arrete pour le voyage a ' . $voyage->destination . ' a ete signe. Veuillez notifier les enseignants beneficiaires definitifs.',
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Arrete signe avec succes']);
    }

    // ============================================
    // VICE-RECTEUR — Notifier les enseignants après signature arrêté
    // ============================================
    public function notifierBeneficiairesDefinitifs($voyageId)
    {
        $voyage = VoyageEtude::findOrFail($voyageId);

        if (!$voyage->arrete_recteur) {
            return response()->json(['message' => 'L\'arrete n\'a pas encore ete signe par le Recteur'], 403);
        }

        $beneficiairesDefinitifs = VoyageEtudeBeneficiaire::where('voyage_id', $voyageId)
            ->where('dans_liste_definitive', true)
            ->with('enseignant')
            ->get();

        foreach ($beneficiairesDefinitifs as $b) {
            Notification::create([
                'user_id'  => $b->enseignant_id,
                'type'     => 'arrete_signe_beneficiaire',
                'titre'    => 'Arrete signe — Vous etes beneficiaire definitif',
                'message'  => 'L\'arrete pour le voyage a ' . $voyage->destination . ' a ete signe par le Recteur. Vous figurez sur la liste definitive. Vous pouvez maintenant faire votre demande d\'autorisation d\'absence.',
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Enseignants beneficiaires notifies']);
    }

    // ============================================
    // ENSEIGNANT — Demande d'autorisation d'absence → Chef Département
    // (uniquement si arrêté signé + dans liste définitive)
    // ============================================
    public function demanderAutorisation($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->where('dans_liste_definitive', true)
            ->firstOrFail();

        // Vérifier que l'arrêté a bien été signé
        if (!$beneficiaire->voyage->arrete_recteur) {
            return response()->json([
                'message' => 'L\'arrete n\'a pas encore ete signe par le Recteur'
            ], 403);
        }

        $beneficiaire->update([
            'statut_autorisation' => 'demande_chef_dept',
        ]);

        $chefsDept = User::where('role', 'chef_departement')->get();
        foreach ($chefsDept as $chef) {
            Notification::create([
                'user_id'  => $chef->id,
                'type'     => 'demande_autorisation',
                'titre'    => 'Demande d\'autorisation d\'absence',
                'message'  => auth()->user()->prenom . ' ' . auth()->user()->nom . ' demande une autorisation d\'absence pour le voyage a ' . $beneficiaire->voyage->destination . '. Veuillez faire l\'autorisation de sortie.',
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Demande envoyee au Chef de Departement']);
    }

    // ============================================
    // CHEF DE DÉPARTEMENT — Autorisation de sortie → Directeur UFR
    // ============================================
    public function autorisationSortie($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_autorisation' => 'envoye_directeur_ufr',
        ]);

        $directeurUFR = User::where('role', 'directeur_ufr')->first();
        if ($directeurUFR) {
            Notification::create([
                'user_id'  => $directeurUFR->id,
                'type'     => 'autorisation_sortie',
                'titre'    => 'Autorisation de sortie a valider',
                'message'  => 'Le Chef de Departement a emis une autorisation de sortie pour ' . $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom . ' (voyage a ' . $beneficiaire->voyage->destination . '). Veuillez la transmettre au Recteur.',
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Autorisation de sortie envoyee au Directeur UFR']);
    }

    // ============================================
    // DIRECTEUR UFR — Transmettre autorisation au Recteur
    // ============================================
    public function envoyerAutorisationRecteur($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_autorisation' => 'envoye_recteur',
        ]);

        $recteur = User::where('role', 'recteur')->first();
        if ($recteur) {
            Notification::create([
                'user_id'  => $recteur->id,
                'type'     => 'autorisation_recteur',
                'titre'    => 'Autorisation de sortie recue',
                'message'  => 'Le Directeur UFR vous a transmis l\'autorisation de sortie de ' . $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom . ' pour le voyage a ' . $beneficiaire->voyage->destination . '.',
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Autorisation envoyee au Recteur']);
    }

    // ============================================
    // RECTEUR — Approuver l'autorisation de sortie → notifie le VR
    // ============================================
    public function approuverAutorisationRecteur($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_autorisation' => 'approuve_recteur',
        ]);

        // Notifier le VR
        $vr = User::where('role', 'vice_recteur')->first();
        if ($vr) {
            Notification::create([
                'user_id'  => $vr->id,
                'type'     => 'autorisation_approuvee_recteur',
                'titre'    => 'Autorisation approuvee par le Recteur',
                'message'  => 'Le Recteur a approuve l\'autorisation de sortie de ' . $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom . ' pour le voyage a ' . $beneficiaire->voyage->destination . '.',
                'lu'       => false,
            ]);
        }

        // Notifier l'enseignant
        Notification::create([
            'user_id'  => $beneficiaire->enseignant_id,
            'type'     => 'autorisation_approuvee',
            'titre'    => 'Autorisation de sortie approuvee',
            'message'  => 'Votre autorisation de sortie pour le voyage a ' . $beneficiaire->voyage->destination . ' a ete approuvee par le Recteur.',
            'lu'       => false,
        ]);

        return response()->json(['message' => 'Autorisation approuvee par le Recteur']);
    }

    // ============================================
    // LISTE TOUS LES VOYAGES (VR + Recteur)
    // ============================================
    public function index()
    {
        $voyages = VoyageEtude::with(['beneficiaires.enseignant', 'beneficiaires.justificatifs', 'beneficiaires.avis.user', 'viceRecteur'])
            ->latest()
            ->get();

        return response()->json($voyages);
    }

    // ============================================
    // VOIR UN VOYAGE
    // ============================================
    public function show($id)
    {
        $voyage = VoyageEtude::with(['beneficiaires.enseignant', 'beneficiaires.justificatifs', 'beneficiaires.avis.user', 'viceRecteur'])
            ->findOrFail($id);

        return response()->json($voyage);
    }

    // ============================================
    // ÉLIGIBILITÉ
    // ============================================
    public function verifierEligibilite()
    {
        $user = auth()->user();

        if ($user->role !== 'enseignant' || $user->statut !== 'permanent') {
            return response()->json([
                'eligible' => false,
                'message'  => 'Seuls les enseignants permanents peuvent beneficier d\'un voyage d\'etudes'
            ]);
        }

        return response()->json([
            'eligible' => true,
            'message'  => 'Vous etes eligible au voyage d\'etudes'
        ]);
    }

    // ============================================
    // VICE-RECTEUR — Ajouter un enseignant manuellement à un voyage
    // ============================================
    public function ajouterBeneficiaire(Request $request, $voyageId)
    {
        $request->validate([
            'enseignant_id' => 'required|exists:users,id',
        ]);

        $voyage = VoyageEtude::findOrFail($voyageId);

        // Vérifier qu'il n'est pas déjà dans la liste
        $existe = VoyageEtudeBeneficiaire::where('voyage_id', $voyageId)
            ->where('enseignant_id', $request->enseignant_id)
            ->first();

        if ($existe) {
            return response()->json(['message' => 'Cet enseignant est deja dans la liste'], 422);
        }

        $beneficiaire = VoyageEtudeBeneficiaire::create([
            'voyage_id'     => $voyageId,
            'enseignant_id' => $request->enseignant_id,
        ]);

        $enseignant = User::find($request->enseignant_id);

        // Notifier le Chef Dép de l'UFR de l'enseignant
        $chefDept = User::where('role', 'chef_departement')->where('ufr', $enseignant->ufr)->first();
        if ($chefDept) {
            Notification::create([
                'user_id' => $chefDept->id,
                'type'    => 'voyage_etude_publie',
                'titre'   => 'Nouvel enseignant ajoute — ' . $enseignant->ufr,
                'message' => $enseignant->prenom . ' ' . $enseignant->nom . ' a ete ajoute a la liste du voyage a ' . $voyage->destination . '. Veuillez l\'informer et recueillir ses justificatifs.',
                'lu'      => false,
            ]);
        }
return response()->json([
            'message'      => 'Enseignant ajoute avec succes',
            'beneficiaire' => $beneficiaire->load('enseignant'),
        ], 201);
    }

    // ============================================
    // ENSEIGNANT — Envoyer un rapport validé comme justificatif
    // ============================================
    public function justificatifDepuisRapport(Request $request, $beneficiaireId)
    {
        $request->validate([
            'rapport_id' => 'required|exists:rapport_voyages,id',
        ]);

        $beneficiaire = VoyageEtudeBeneficiaire::where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->firstOrFail();

        $rapport = \App\Models\RapportVoyage::where('id', $request->rapport_id)
            ->where('enseignant_id', auth()->id())
            ->where('statut', 'valide')
            ->firstOrFail();

        VoyageEtudeJustificatif::create([
            'beneficiaire_id' => $beneficiaire->id,
            'fichier_pdf'     => $rapport->fichier_pdf,
            'nom_original'    => 'Rapport_' . $rapport->voyage->destination . '.pdf',
        ]);

        $beneficiaire->update([
            'statut_justificatif' => 'soumis'
        ]);

        $enseignant = auth()->user();
        $chefDept = User::where('role', 'chef_departement')
            ->where('ufr', $enseignant->ufr)
            ->first();

        if ($chefDept) {
            Notification::create([
                'user_id' => $chefDept->id,
                'type'    => 'justificatif_soumis',
                'titre'   => 'Rapport soumis comme justificatif — ' . $enseignant->ufr,
                'message' => $enseignant->prenom . ' ' . $enseignant->nom .
                    ' a soumis son rapport de voyage comme justificatif pour le voyage a ' .
                    $beneficiaire->voyage->destination . '.',
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message' => 'Rapport envoye comme justificatif avec succes'
        ]);
    }
}