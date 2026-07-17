<?php

namespace App\Http\Controllers;

use App\Models\VoyageEtude;
use App\Models\VoyageEtudeBeneficiaire;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\VoyageEtudeAvis;
use App\Models\VoyageEtudeJustificatif;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
class VoyageEtudeController extends Controller
{
    public function publierListe(Request $request)
    {
        $request->validate([
            'date_publication' => 'required|date',
            'motif'            => 'required|string',
            'enseignants'      => 'required|array',
            'enseignants.*'    => 'exists:users,id',
        ]);

        $voyage = VoyageEtude::create([
            'vice_recteur_id'  => auth()->id(),
            'date_publication' => $request->date_publication,
            'motif'            => $request->motif,
            'destination'      => $request->motif,
            'date_debut'       => $request->date_publication,
            'date_fin'         => $request->date_publication,
            'statut_liste'     => 'brouillon',
        ]);

        foreach ($request->enseignants as $enseignantId) {
            VoyageEtudeBeneficiaire::create([
                'voyage_id'     => $voyage->id,
                'enseignant_id' => $enseignantId,
            ]);
        }

        return response()->json([
            'message' => 'Liste enregistree, en attente de signature',
            'voyage'  => $voyage->load('beneficiaires.enseignant'),
        ], 201);
    }

    public function transmettreListe(Request $request, $voyageId)
    {
        $request->validate([
            'signature' => 'required|string',
        ]);

        $voyage = VoyageEtude::findOrFail($voyageId);

        if ($voyage->vice_recteur_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisee'], 403);
        }

        if ($voyage->statut_liste !== 'brouillon') {
            return response()->json(['message' => 'Cette liste a deja ete transmise'], 409);
        }

        $voyage->update([
            'statut_liste' => 'publiee',
            'signature_liste_preliminaire' => $request->signature,
        ]);

       
$chefsDept = User::where('role', 'chef_departement')->get();
foreach ($chefsDept as $chef) {
    Notification::create([
        'user_id' => $chef->id,
        'type'    => 'voyage_etude_publie',
        'titre'   => 'Nouvelle liste de voyage d\'etudes publiee',
        'message' => 'Le Vice-Recteur a publie une liste de beneficiaires pour : ' . $voyage->motif . '. Veuillez informer les enseignants concernes.',
        'lu'      => false,
    ]);
}

$commission = User::where('role', 'commission')->get();
foreach ($commission as $membre) {
    Notification::create([
        'user_id' => $membre->id,
        'type'    => 'voyage_etude_publie',
        'titre'   => 'Nouvelle liste de voyage d\'etudes publiee',
        'message' => 'Le Vice-Recteur a publie une liste de beneficiaires pour : ' . $voyage->motif . '.',
        'lu'      => false,
    ]);
}

        return response()->json([
            'message' => 'Liste transmise avec succes aux Chefs de Departement',
            'voyage'  => $voyage->load('beneficiaires.enseignant'),
        ]);
    }

    public function notifierEnseignants($voyageId)
    {
        $voyage = VoyageEtude::with('beneficiaires.enseignant')->findOrFail($voyageId);

        foreach ($voyage->beneficiaires as $beneficiaire) {
            Notification::create([
                'user_id'  => $beneficiaire->enseignant_id,
                'type'     => 'voyage_etude_publie',
                'titre'    => 'Vous etes beneficiaire d\'un voyage d\'etudes',
                'message'  => 'Vous avez ete selectionne pour le voyage a ' . $voyage->destination . '. Soumettez vos justificatifs (rapport de dernier voyage + autres pieces) via la plateforme ; ils seront transmis a la Commission et au Vice-Recteur.',
                'lu'       => false,
            ]);
        }

        $voyage->update(['enseignants_notifies' => true]);

        return response()->json(['message' => 'Enseignants notifies avec succes']);
    }

    public function mesVoyages()
    {
        $user = auth()->user();

        $beneficiaires = VoyageEtudeBeneficiaire::where('enseignant_id', $user->id)
            ->where('masque_enseignant', false)
            ->with(['voyage.viceRecteur', 'autorisationAbsence'])
            ->latest()
            ->get();

        return response()->json($beneficiaires);
    }

    public function soumettreJustificatifs(Request $request, $beneficiaireId)
    {
        $request->validate([
            'justificatifs'           => 'required|array|min:1|max:5',
            'justificatifs.*'         => 'file|mimes:pdf|max:10240',
            'justificatifs_autres'    => 'nullable|array|max:5',
            'justificatifs_autres.*'  => 'file|mimes:pdf|max:10240',
        ]);

       $beneficiaire = VoyageEtudeBeneficiaire::where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->firstOrFail();

        if ($beneficiaire->date_limite_soumission && now()->greaterThan($beneficiaire->date_limite_soumission)) {
            return response()->json([
                'message' => 'La date limite du ' . $beneficiaire->date_limite_soumission->format('d/m/Y') . ' pour renvoyer vos justificatifs est depassee. Contactez le Vice-Recteur.',
            ], 422);
        }

        foreach ($request->file('justificatifs') as $fichier) {
            $path = $fichier->store('justificatifs', 'public');

            VoyageEtudeJustificatif::create([
                'beneficiaire_id' => $beneficiaire->id,
                'fichier_pdf'     => $path,
                'nom_original'    => $fichier->getClientOriginalName(),
            ]);
        }

        if ($request->hasFile('justificatifs_autres')) {
            foreach ($request->file('justificatifs_autres') as $fichier) {
                $path = $fichier->store('justificatifs', 'public');

                VoyageEtudeJustificatif::create([
                    'beneficiaire_id' => $beneficiaire->id,
                    'fichier_pdf'     => $path,
                    'nom_original'    => $fichier->getClientOriginalName(),
                ]);
            }
        }

     $beneficiaire->update([
            'statut_justificatif'    => 'soumis',
            'date_limite_soumission' => null,
            'alerte_delai_envoyee'   => false,
        ]);

      
        \App\Models\VoyageEtudeAvis::where('beneficiaire_id', $beneficiaire->id)->delete();

        $nomEnseignant = auth()->user()->prenom . ' ' . auth()->user()->nom;
        $destination   = $beneficiaire->voyage->destination;

        $vr = User::where('role', 'vice_recteur')->first();
       $estRenvoi = $beneficiaire->wasChanged('statut_justificatif') && $beneficiaire->getOriginal('statut_justificatif') === 'incomplet';
        $libelleAction = $estRenvoi ? 'renvoyé ses justificatifs corrigés' : 'soumis ses justificatifs';
        $rappelMotif = ($estRenvoi && $beneficiaire->dernier_motif_rejet)
            ? ' Rappel du motif de rejet précédent : ' . $beneficiaire->dernier_motif_rejet
            : '';

        if ($vr) {
            Notification::create([
                'user_id'  => $vr->id,
                'type'     => 'justificatif_soumis',
                'titre'    => $estRenvoi ? 'Dossier corrigé et renvoyé' : 'Justificatifs reçus d\'un enseignant',
                'message'  => $nomEnseignant . ' a ' . $libelleAction . ' pour le voyage à ' . $destination . '. Veuillez les vérifier.' . $rappelMotif,
                'lu'       => false,
            ]);
        }
        $commission = User::where('role', 'commission')->get();
        foreach ($commission as $membre) {
            Notification::create([
                'user_id'  => $membre->id,
                'type'     => 'dossier_a_valider',
                'titre'    => $estRenvoi ? 'Dossier corrigé à valider' : 'Nouveau dossier à valider',
                'message'  => $nomEnseignant . ' a ' . $libelleAction . ' pour le voyage à ' . $destination . '. Veuillez donner votre avis.' . $rappelMotif,
                'lu'       => false,
            ]);
        }

        return response()->json([
            'message'      => 'Justificatifs soumis avec succes au Vice-Recteur et a la Commission',
            'beneficiaire' => $beneficiaire->load('justificatifs'),
        ]);
    }

    public function dossiersDepartement()
    {
        $user = auth()->user();

        if ($user->role === 'directeur_ufr') {
            $dossiers = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs', 'autorisationAbsence'])
                ->where('masque_directeur_ufr', false)
                ->where('statut_autorisation', 'envoye_directeur_ufr')
                ->latest()->get();
        } elseif ($user->role === 'recteur') {
            $dossiers = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs', 'autorisationAbsence'])
                ->where('masque_recteur', false)
                ->where('statut_autorisation', 'envoye_recteur')
                ->latest()->get();
        } else {
            $chefUFR = auth()->user()->ufr;
            $dossiers = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs', 'autorisationAbsence'])
                ->where('masque_chef_departement', false)
                ->whereHas('voyage', fn($q) => $q->whereIn('statut_liste', ['publiee', 'definitive']))
                ->whereHas('enseignant', fn($q) => $q->where('ufr', $chefUFR))
                ->latest()->get();
        }

        return response()->json($dossiers);
    }

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

    public function destroy($id)
{
    $voyage = VoyageEtude::findOrFail($id);
    $user = auth()->user();

    if ($user->role === 'vice_recteur') {
        $voyage->update(['masque_vr' => true]);
        return response()->json(['message' => 'Voyage(s) supprimé(s)']);
    }

    if ($user->role === 'chef_departement') {
        $voyage->beneficiaires()
            ->whereHas('enseignant', fn($q) => $q->where('ufr', $user->ufr))
            ->update(['masque_chef_departement' => true]);
        return response()->json(['message' => 'Voyage(s) supprimé(s)']);
    }

    if ($user->role === 'recteur') {
        $voyage->update(['masque_recteur' => true]);
        return response()->json(['message' => 'Voyage(s) supprimé(s)']);
    }

    if ($user->role === 'commission') {
        $voyage->update(['masque_commission' => true]);
        return response()->json(['message' => 'Voyage(s) supprimé(s)']);
    }

    if ($user->role === 'admin') {
        $voyage->update(['masque_admin' => true]);
        return response()->json(['message' => 'Voyage(s) supprimé(s)']);
    }

    $voyage->delete();
    return response()->json(['message' => 'Voyage supprimé']);
}

    public function dossiersAValider()
    {
        $user = auth()->user();
        $champ = $user->role === 'commission' ? 'masque_commission' : 'masque_vice_recteur';

        $dossiers = VoyageEtudeBeneficiaire::with([
            'enseignant', 'voyage', 'justificatifs', 'avis.user', 'autorisationAbsence'
        ])
            ->where($champ, false)
            ->whereIn('statut_justificatif', ['soumis', 'transmis_vr', 'valide', 'incomplet'])
            ->latest()
            ->get()
            ->map(function ($d) {
                $arr = $d->toArray();
                $arr['autorisation_absence_id'] = $d->autorisationAbsence?->id;
                return $arr;
            });

        return response()->json($dossiers);
    }

    public function donnerAvis(Request $request, $beneficiaireId)
    {
       $request->validate([
            'avis'        => 'required|in:valide,rejete',
            'commentaire' => 'nullable|string',
            'date_limite' => 'nullable|date|after:today',
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

        if ($auteur->role === 'commission') {
            try {
                $vr = User::where('role', 'vice_recteur')->first();
                if ($vr) {
                    if ($request->avis === 'valide') {
                      Notification::create([
                            'user_id' => $vr->id,
                            'type'    => 'dossier_valide_commission',
                            'titre'   => 'Dossier validé par la commission',
                            'message' => 'La commission a validé le dossier de ' . $nomEns . ' pour le voyage à ' . $destination . '. Votre validation finale est requise.' . ($request->commentaire ? ' Commentaire : ' . $request->commentaire : ''),
                            'lu'      => false,
                        ]);
                    } else {
                      Notification::create([
                            'user_id' => $vr->id,
                            'type'    => 'avis_commission',
                            'titre'   => 'Dossier rejeté par la commission',
                            'message' => 'La commission a rejeté le dossier de ' . $nomEns . ' pour le voyage à ' . $destination . '.' . ($request->commentaire ? ' Motif : ' . $request->commentaire : ''),
                            'lu'      => false,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Notification avis commission: ' . $e->getMessage());
            }
        }

       if ($request->avis === 'rejete' && $auteur->role === 'vice_recteur') {
            $dateLimite = $request->date_limite
                ? \Carbon\Carbon::parse($request->date_limite)
                : now()->addDays(15);

            $beneficiaire->update([
                'statut_justificatif'     => 'incomplet',
                'date_limite_soumission'  => $dateLimite,
                'alerte_delai_envoyee'    => false,
                'deja_rejete'             => true,
                'dernier_motif_rejet'     => $request->commentaire,
            ]);
            try {
                Notification::create([
                    'user_id' => $beneficiaire->enseignant_id,
                    'type'    => 'dossier_rejete',
                    'titre'   => 'Dossier incomplet',
                    'message' => 'Votre dossier pour le voyage à ' . $destination . ' a été jugé incomplet par le Vice-Recteur. Veuillez compléter vos justificatifs et les soumettre avant le ' . $dateLimite->format('d/m/Y') . '.' . ($request->commentaire ? ' Motif : ' . $request->commentaire : ''),
                    'lu'      => false,
                ]);
            } catch (\Exception $e) {
                \Log::error('Notification rejet VR: ' . $e->getMessage());
            }
        }

        if ($request->avis === 'valide' && $auteur->role === 'vice_recteur') {
            $beneficiaire->update(['statut_justificatif' => 'valide']);

            \App\Models\RapportVoyage::where('enseignant_id', $beneficiaire->enseignant_id)
                ->where('voyage_id', $beneficiaire->voyage_id)
                ->where('statut', '!=', 'valide')
                ->update([
                    'statut'        => 'valide',
                    'commentaire_vr' => $request->commentaire ?? 'Validé automatiquement avec le dossier de voyage',
                ]);

            \App\Models\RapportVoyage::where('enseignant_id', $beneficiaire->enseignant_id)
                ->where('voyage_id', $beneficiaire->voyage_id)
                ->where('statut', '!=', 'valide')
                ->update([
                    'statut'         => 'rejete',
                    'commentaire_vr' => $request->commentaire ?? 'Rejeté avec le dossier de voyage',
                ]);

            try {
                Notification::create([
                    'user_id' => $beneficiaire->enseignant_id,
                    'type'    => 'rapport_valide',
                    'titre'   => 'Rapport de voyage validé',
                    'message' => 'Votre rapport de voyage pour ' . $beneficiaire->voyage->destination . ' a été validé par le Vice-Recteur.',
                    'lu'      => false,
                ]);
            } catch (\Exception $e) {
                \Log::error('Notification rapport validé: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Avis enregistre',
            'avis'    => $beneficiaire->load('avis.user'),
        ]);
    }

    public function publierListeDefinitive(Request $request, $voyageId)
    {
       $request->validate([
    'beneficiaires'   => 'required|array',
    'beneficiaires.*' => 'exists:voyage_etude_beneficiaires,id',
    'signature'        => 'required|string',
]);
        $voyage = VoyageEtude::findOrFail($voyageId);

        $beneficiairesSelectionnes = VoyageEtudeBeneficiaire::with(['avis.user'])
            ->whereIn('id', $request->beneficiaires)
            ->get();

        $erreurs = [];
        foreach ($beneficiairesSelectionnes as $b) {
            $enseignant = User::find($b->enseignant_id);
            $nomEns     = $enseignant ? $enseignant->prenom . ' ' . $enseignant->nom : 'Enseignant #' . $b->enseignant_id;

            if (!in_array($b->statut_justificatif, ['transmis_vr', 'valide'])) {
                $erreurs[] = $nomEns . ' : justificatifs non soumis';
                continue;
            }

            $avisCommission = $b->avis->filter(fn($a) => $a->user?->role === 'commission' && $a->avis === 'valide');
            if ($avisCommission->isEmpty()) {
                $erreurs[] = $nomEns . ' : avis commission manquant';
                continue;
            }

            $avisVR = $b->avis->first(fn($a) => $a->user?->role === 'vice_recteur' && $a->avis === 'valide');
            if (!$avisVR) {
                $erreurs[] = $nomEns . ' : validation VR manquante';
            }
        }

        if (!empty($erreurs)) {
            return response()->json([
                'message' => 'Conditions non reunies pour certains beneficiaires',
                'erreurs' => $erreurs,
            ], 422);
        }

        VoyageEtudeBeneficiaire::where('voyage_id', $voyageId)
            ->update(['dans_liste_definitive' => false]);

        $dateRetour = \Carbon\Carbon::parse($voyage->date_fin);
        $limiteRapport = \Carbon\Carbon::createFromDate($dateRetour->year, 3, 31);
        if ($dateRetour->greaterThan($limiteRapport)) {
            $limiteRapport = \Carbon\Carbon::createFromDate($dateRetour->year + 1, 3, 31);
        }

        VoyageEtudeBeneficiaire::whereIn('id', $request->beneficiaires)
            ->update([
                'dans_liste_definitive' => true,
                'date_limite_rapport'   => $limiteRapport,
                'alerte_rapport_envoyee' => false,
            ]);
$voyage->update([
    'statut_liste' => 'definitive',
    'signature_liste_definitive' => $request->signature,
    'date_liste_definitive' => now(),
]);

        $recteur = User::where('role', 'recteur')->first();
        if ($recteur) {
            Notification::create([
                'user_id' => $recteur->id,
                'type'    => 'liste_definitive',
                'titre'   => 'Liste definitive a signer',
                'message' => 'La liste definitive du voyage a ' . $voyage->destination . ' a ete validee par le VR et sa commission. Veuillez signer l\'arrete.',
                'lu'      => false,
            ]);
        }

        $beneficiairesDefinitifs = VoyageEtudeBeneficiaire::whereIn('id', $request->beneficiaires)
            ->with('enseignant')
            ->get();

        $parUFR = $beneficiairesDefinitifs->groupBy(fn($b) => $b->enseignant?->ufr);
        foreach ($parUFR as $ufr => $bens) {
            $chefDept = User::where('role', 'chef_departement')->where('ufr', $ufr)->first();
            if (!$chefDept) continue;
            $noms = $bens->map(fn($b) => $b->enseignant?->prenom . ' ' . $b->enseignant?->nom)->join(', ');
            Notification::create([
                'user_id' => $chefDept->id,
                'type'    => 'liste_definitive_transmise',
                'titre'   => 'Liste definitive transmise — ' . $ufr,
                'message' => 'La liste definitive du voyage a ' . $voyage->destination . ' a ete publiee. Beneficiaires de votre UFR : ' . $noms . '.',
                'lu'      => false,
            ]);
        }

        return response()->json(['message' => 'Liste definitive publiee et envoyee au Recteur et aux Chefs de Departement']);
    }

    public function beneficiaires($id)
    {
        $voyage = VoyageEtude::findOrFail($id);

        $beneficiaires = $voyage->beneficiaires()
            ->with('enseignant')
            ->get();

        return response()->json($beneficiaires);
    }

    public function signerArrete($voyageId)
    {
        $voyage = VoyageEtude::findOrFail($voyageId);
        $voyage->update(['arrete_recteur' => true]);

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

    public function demanderAutorisation($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->where('dans_liste_definitive', true)
            ->firstOrFail();

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

    public function masquerVoyage($id)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::findOrFail($id);
        $role = auth()->user()->role;
        $champ = match($role) {
            'chef_departement' => 'masque_chef_departement',
            'directeur_ufr'    => 'masque_directeur_ufr',
            'recteur'          => 'masque_recteur',
            'vice_recteur'     => 'masque_vice_recteur',
            'commission'       => 'masque_commission',
            'enseignant'       => 'masque_enseignant',
            default            => null,
        };

        if (!$champ) return response()->json(['message' => 'Role non autorise'], 403);

        $beneficiaire->update([$champ => true]);
        return response()->json(['message' => 'Masque avec succes']);
    }

    public function destroyBeneficiaire($id)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::findOrFail($id);
        $role = auth()->user()->role;

        $champ = match($role) {
            'chef_departement' => 'masque_chef_departement',
            'directeur_ufr'    => 'masque_directeur_ufr',
            'recteur'          => 'masque_recteur',
            'vice_recteur'     => 'masque_vice_recteur',
            'commission'       => 'masque_commission',
            default            => null,
        };

        if (!$champ) return response()->json(['message' => 'Role non autorise'], 403);

        $beneficiaire->update([$champ => true]);
        return response()->json(['message' => 'Dossier masque avec succes']);
    }

    public function approuverAutorisationRecteur($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_autorisation' => 'approuve_recteur',
        ]);

        $vr = User::where('role', 'vice_recteur')->first();
        if ($vr) {
            Notification::create([
                'user_id' => $vr->id,
                'type'    => 'autorisation_approuvee_recteur',
                'titre'   => 'Autorisation approuvee par le Recteur',
                'message' => 'Le Recteur a approuve l\'autorisation de sortie de ' . $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom . ' pour le voyage a ' . $beneficiaire->voyage->destination . '. Veuillez la transmettre a l\'enseignant.',
                'lu'      => false,
            ]);
        }

        return response()->json(['message' => 'Autorisation approuvee et transmise au Vice-Recteur']);
    }

    public function transmettreAutorisationEnseignant($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_autorisation' => 'approuve',
        ]);

        Notification::create([
            'user_id' => $beneficiaire->enseignant_id,
            'type'    => 'autorisation_approuvee',
            'titre'   => 'Autorisation de sortie approuvee',
            'message' => 'Votre autorisation de sortie pour le voyage a ' . $beneficiaire->voyage->destination . ' a ete approuvee par le Recteur et transmise par le Vice-Recteur. Vous pouvez partir sereinement.',
            'lu'      => false,
        ]);

        return response()->json(['message' => 'Autorisation transmise a l\'enseignant']);
    }

    public function index()
    {
        $user = auth()->user();

        $query = VoyageEtude::with([
            'beneficiaires.enseignant',
            'beneficiaires.justificatifs',
            'beneficiaires.avis.user',
            'beneficiaires.autorisationAbsence',
            'viceRecteur',
            'arrete'
        ]);

        if ($user->role === 'vice_recteur') {
    $query->where('masque_vr', false);
}

if ($user->role === 'recteur') {
    $query->where('statut_liste', 'definitive')
          ->where('masque_recteur', false);
}

if ($user->role === 'commission') {
    $query->where('masque_commission', false);
}

if ($user->role === 'admin') {
    $query->where('masque_admin', false);
}
        $voyages = $query->latest()->get()->map(function ($voyage) use ($user) {
            $arr = $voyage->toArray();
            $arr['beneficiaires'] = $voyage->beneficiaires
                ->when($user->role === 'vice_recteur', fn($c) => $c->where('masque_vice_recteur', false))
                ->map(function ($b) {
                    $arr = $b->toArray();
                    $arr['autorisation_absence_id'] = $b->autorisationAbsence?->id;
                    return $arr;
                })->values();
            return $arr;
        });

        return response()->json($voyages);
    }

    public function show($id)
    {
        $voyage = VoyageEtude::with(['beneficiaires.enseignant', 'beneficiaires.justificatifs', 'beneficiaires.avis.user', 'viceRecteur'])
            ->findOrFail($id);

        return response()->json($voyage);
    }

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

   public function creerEnseignantManuel(Request $request)
    {
        $request->validate([
            'prenom'        => 'required|string',
            'nom'           => 'required|string',
            'email'         => 'required|email|unique:users',
            'ufr'           => 'nullable|in:SATIC,SDD,ECOMIJ,ISFAR',
            'departement'   => 'nullable|string',
            'matricule'     => 'required|string|unique:users',
            'date_embauche' => 'nullable|date',
        ]);

        $code = (string) random_int(100000, 999999);

        $enseignant = User::create([
            'nom'                       => $request->nom,
            'prenom'                    => $request->prenom,
            'email'                     => $request->email,
            'password'                  => Str::random(32),
            'role'                      => 'enseignant',
            'ufr'                       => $request->ufr,
            'departement'               => $request->departement,
            'matricule'                 => $request->matricule,
            'date_embauche'             => $request->date_embauche,
            'is_active'                 => true,
            'compte_actif'              => false,
            'code_activation'           => Hash::make($code),
            'code_activation_expire_at' => now()->addHours(48),
        ]);

        try {
            Mail::to($enseignant->email)->send(new \App\Mail\CodeActivationMail($enseignant, $code));
        } catch (\Exception $e) {
            \Log::error('Envoi email code activation: ' . $e->getMessage());
        }

        $chefDept = User::where('role', 'chef_departement')->where('ufr', $enseignant->ufr)->first();
        if ($chefDept) {
            Notification::create([
                'user_id' => $chefDept->id,
                'type'    => 'enseignant_ajoute_manuellement',
                'titre'   => 'Nouvel enseignant ajoute — ' . $enseignant->ufr,
                'message' => 'Le Vice-Recteur a ajoute ' . $enseignant->prenom . ' ' . $enseignant->nom . ' a une liste de voyage d\'etudes. Un code d\'activation lui a ete envoye par email.',
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message'    => 'Enseignant cree avec succes. Un code d\'activation a ete envoye par email.',
            'enseignant' => $enseignant,
        ], 201);
    }
    public function ajouterBeneficiaire(Request $request, $voyageId)
    {
        $request->validate([
            'enseignant_id' => 'required|exists:users,id',
        ]);
        $voyage = VoyageEtude::findOrFail($voyageId);
        $existe = VoyageEtudeBeneficiaire::where('voyage_id', $voyageId)
            ->where('enseignant_id', $request->enseignant_id)
            ->first();
        if ($existe) {
            return response()->json(['message' => 'Cet enseignant est deja dans la liste'], 422);
        }

        $enseignantACheck = User::find($request->enseignant_id);
        if ($enseignantACheck && $enseignantACheck->bloque_prochain_voyage) {
            return response()->json([
                'message' => 'Cet enseignant n\'est pas eligible : il n\'a pas soumis son rapport/justificatifs du voyage precedent dans les delais. Seul le Vice-Recteur peut lever ce blocage.',
            ], 422);
        }

        $beneficiaire = VoyageEtudeBeneficiaire::create([
            'voyage_id'     => $voyageId,
            'enseignant_id' => $request->enseignant_id,
        ]);

        $enseignant = User::find($request->enseignant_id);

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
public function leverBlocage($enseignantId)
    {
        if (auth()->user()->role !== 'vice_recteur') {
            return response()->json(['message' => 'Non autorise'], 403);
        }

        $enseignant = User::findOrFail($enseignantId);
        $enseignant->update([
            'bloque_prochain_voyage' => false,
            'date_blocage'           => null,
        ]);

        try {
            Notification::create([
                'user_id' => $enseignant->id,
                'type'    => 'blocage_leve',
                'titre'   => 'Eligibilite restauree',
                'message' => 'Le Vice-Recteur a leve votre blocage. Vous etes de nouveau eligible a un prochain voyage d\'etudes.',
                'lu'      => false,
            ]);
        } catch (\Exception $e) {
            \Log::error('Notification levee blocage: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Blocage leve avec succes']);
    }
    public function enseignantsBloques()
    {
        $enseignants = User::where('bloque_prochain_voyage', true)
            ->where('role', 'enseignant')
            ->orderByDesc('date_blocage')
            ->get(['id', 'nom', 'prenom', 'email', 'ufr', 'departement', 'date_blocage']);

        return response()->json($enseignants);
    }
    public function activerCompte(Request $request)
    {
        $request->validate([
    'email'     => 'required|email',
    'code'      => 'required|string',
    'matricule' => 'required|string',
    'password'  => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
]);

        $enseignant = User::where('email', $request->email)
            ->where('compte_actif', false)
            ->first();

        if (!$enseignant) {
            return response()->json(['message' => 'Compte introuvable ou deja active'], 404);
        }

        if (!$enseignant->code_activation_expire_at || now()->greaterThan($enseignant->code_activation_expire_at)) {
            return response()->json(['message' => 'Le code d\'activation a expire. Contactez le Vice-Recteur.'], 422);
        }

        if (!Hash::check($request->code, $enseignant->code_activation)) {
            return response()->json(['message' => 'Code d\'activation incorrect'], 422);
        }

        if (trim($request->matricule) !== trim($enseignant->matricule)) {
            return response()->json(['message' => 'Le matricule ne correspond pas a nos enregistrements'], 422);
        }

        $enseignant->update([
            'password'                  => $request->password,
            'compte_actif'              => true,
            'code_activation'           => null,
            'code_activation_expire_at' => null,
        ]);

        return response()->json(['message' => 'Compte active avec succes. Vous pouvez maintenant vous connecter.']);
    }
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

    public function voirAutorisationSortie($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs'])
            ->findOrFail($beneficiaireId);

        if (auth()->user()->role === 'enseignant' && $beneficiaire->enseignant_id !== auth()->id()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json([
            'statut_autorisation' => $beneficiaire->statut_autorisation,
            'autorisation_sortie' => $beneficiaire->autorisation_sortie,
            'beneficiaire'        => $beneficiaire,
        ]);
    }

    public function listesPubliees()
    {
        $voyages = VoyageEtude::where('statut_liste', 'publiee')
            ->where('masque_commission', false)
            ->with(['beneficiaires.enseignant', 'viceRecteur'])
            ->latest()
            ->get();

        return response()->json($voyages);
    }
}
