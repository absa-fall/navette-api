<?php

namespace App\Http\Controllers;

use App\Models\VoyageEtude;
use App\Models\VoyageEtudeBeneficiaire;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VoyageEtudeController extends Controller
{
    // ============================================
    // VICE-RECTEUR — Publier liste bénéficiaires
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

            Notification::create([
                'user_id'  => $enseignantId,
                'type'     => 'voyage_etude_publie',
                'titre'    => 'Vous etes beneficiaire d\'un voyage d\'etudes',
                'message'  => 'Le Vice-Recteur vous a selectionne pour le voyage a ' . $request->destination . '. Soumettez vos justificatifs a votre Chef de Departement.',
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json([
            'message' => 'Liste publiee avec succes',
            'voyage'  => $voyage->load('beneficiaires.enseignant'),
        ], 201);
    }

    // ============================================
    // ENSEIGNANT — Voir ses voyages
    // ============================================
    public function mesVoyages()
    {
        $user = auth()->user();

        $beneficiaires = VoyageEtudeBeneficiaire::where('enseignant_id', $user->id)
            ->with('voyage.viceRecteur')
            ->latest()
            ->get();

        return response()->json($beneficiaires);
    }

    // ============================================
    // ENSEIGNANT — Soumettre justificatifs
    // ============================================
    public function soumettreJustificatifs(Request $request, $beneficiaireId)
    {
        $request->validate([
            'justificatif_pdf' => 'required|file|mimes:pdf|max:10240',
        ]);

        $beneficiaire = VoyageEtudeBeneficiaire::where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->firstOrFail();

        $path = $request->file('justificatif_pdf')->store('justificatifs', 'public');

        $beneficiaire->update([
            'justificatif_pdf'    => $path,
            'statut_justificatif' => 'soumis',
        ]);

        $chefDept = User::where('role', 'chef_departement')->first();
        if ($chefDept) {
            Notification::create([
                'user_id'  => $chefDept->id,
                'type'     => 'justificatif_soumis',
                'titre'    => 'Justificatifs recus',
                'message'  => auth()->user()->prenom . ' ' . auth()->user()->nom . ' a soumis ses justificatifs pour le voyage a ' . $beneficiaire->voyage->destination,
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json([
            'message'      => 'Justificatifs soumis avec succes',
            'beneficiaire' => $beneficiaire,
        ]);
    }

    // ============================================
    // CHEF DÉPARTEMENT — Voir les dossiers
    // ============================================
    public function dossiersDepartement()
    {
        $dossiers = VoyageEtudeBeneficiaire::where('statut_justificatif', 'soumis')
            ->with(['enseignant', 'voyage'])
            ->latest()
            ->get();

        return response()->json($dossiers);
    }

    // ============================================
    // CHEF DÉPARTEMENT — Envoyer au VR
    // ============================================
    public function envoyerAuVR($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['voyage', 'enseignant'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_justificatif' => 'valide',
        ]);

        $vr = User::where('role', 'vice_recteur')->first();
        if ($vr) {
            Notification::create([
                'user_id'  => $vr->id,
                'type'     => 'dossier_recu',
                'titre'    => 'Dossier recu du Chef de Departement',
                'message'  => 'Le dossier de ' . $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom . ' pour le voyage a ' . $beneficiaire->voyage->destination . ' a ete transmis.',
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Dossier transmis au Vice-Recteur']);
    }

    // ============================================
    // VICE-RECTEUR — Publier liste définitive
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

        $recteur = User::where('role', 'recteur')->first();
        if ($recteur) {
            Notification::create([
                'user_id'  => $recteur->id,
                'type'     => 'liste_definitive',
                'titre'    => 'Liste definitive a valider',
                'message'  => 'La liste definitive du voyage a ' . $voyage->destination . ' vous a ete transmise pour arrete.',
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        $beneficiairesDefinitifs = VoyageEtudeBeneficiaire::whereIn('id', $request->beneficiaires)
            ->with('enseignant')
            ->get();

        foreach ($beneficiairesDefinitifs as $b) {
            Notification::create([
                'user_id'  => $b->enseignant_id,
                'type'     => 'liste_definitive_publiee',
                'titre'    => 'Vous etes sur la liste definitive',
                'message'  => 'Vous figurez sur la liste definitive du voyage a ' . $voyage->destination . '. Faites votre demande d\'autorisation d\'absence.',
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Liste definitive publiee']);
    }

    // ============================================
    // RECTEUR — Signer l'arrêté
    // ============================================
    public function signerArrete($voyageId)
    {
        $voyage = VoyageEtude::findOrFail($voyageId);
        $voyage->update(['arrete_recteur' => true]);

        $vr = User::where('role', 'vice_recteur')->first();
        if ($vr) {
            Notification::create([
                'user_id'  => $vr->id,
                'type'     => 'arrete_signe',
                'titre'    => 'Arrete signe',
                'message'  => 'L\'arrete pour le voyage a ' . $voyage->destination . ' a ete signe par le Recteur.',
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Arrete signe avec succes']);
    }

    // ============================================
    // ENSEIGNANT — Demande autorisation absence
    // ============================================
    public function demanderAutorisation($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->where('dans_liste_definitive', true)
            ->firstOrFail();

        $beneficiaire->update([
            'statut_autorisation' => 'demande_chef_dept',
        ]);

        $chefDept = User::where('role', 'chef_departement')->first();
        if ($chefDept) {
            Notification::create([
                'user_id'  => $chefDept->id,
                'type'     => 'demande_autorisation',
                'titre'    => 'Demande d\'autorisation d\'absence',
                'message'  => auth()->user()->prenom . ' ' . auth()->user()->nom . ' demande une autorisation d\'absence pour le voyage a ' . $beneficiaire->voyage->destination,
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Demande envoyee au Chef de Departement']);
    }

    // ============================================
    // CHEF DÉPARTEMENT — Autorisation de sortie → Directeur UFR
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
                'message'  => 'L\'autorisation de sortie de ' . $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom . ' vous a ete transmise.',
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Autorisation de sortie envoyee au Directeur UFR']);
    }

    // ============================================
    // DIRECTEUR UFR — Envoyer au VR
    // ============================================
    public function envoyerAutorisationVR($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_autorisation' => 'envoye_vr',
        ]);

        $vr = User::where('role', 'vice_recteur')->first();
        if ($vr) {
            Notification::create([
                'user_id'  => $vr->id,
                'type'     => 'autorisation_vr',
                'titre'    => 'Autorisation de sortie recue',
                'message'  => 'L\'autorisation de sortie de ' . $beneficiaire->enseignant->prenom . ' ' . $beneficiaire->enseignant->nom . ' vous a ete transmise par le Directeur UFR.',
                'ordre_id' => null,
                'lu'       => false,
            ]);
        }

        return response()->json(['message' => 'Autorisation envoyee au Vice-Recteur']);
    }

    // ============================================
    // VICE-RECTEUR — Approuver autorisation finale
    // ============================================
    public function approuverAutorisation($beneficiaireId)
    {
        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

        $beneficiaire->update([
            'statut_autorisation' => 'approuve',
        ]);

        Notification::create([
            'user_id'  => $beneficiaire->enseignant_id,
            'type'     => 'autorisation_approuvee',
            'titre'    => 'Autorisation approuvee',
            'message'  => 'Votre autorisation de sortie pour le voyage a ' . $beneficiaire->voyage->destination . ' a ete approuvee par le Vice-Recteur.',
            'ordre_id' => null,
            'lu'       => false,
        ]);

        return response()->json(['message' => 'Autorisation approuvee']);
    }

    // ============================================
    // LISTE TOUS LES VOYAGES (VR)
    // ============================================
    public function index()
    {
        $voyages = VoyageEtude::with(['beneficiaires.enseignant', 'viceRecteur'])
            ->latest()
            ->get();

        return response()->json($voyages);
    }

    // ============================================
    // VOIR UN VOYAGE
    // ============================================
    public function show($id)
    {
        $voyage = VoyageEtude::with(['beneficiaires.enseignant', 'viceRecteur'])
            ->findOrFail($id);

        return response()->json($voyage);
    }

    // ============================================
    // ELIGIBILITE
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
}