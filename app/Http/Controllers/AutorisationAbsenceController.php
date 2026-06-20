<?php

namespace App\Http\Controllers;

use App\Models\AutorisationAbsence;
use App\Models\VoyageEtudeBeneficiaire;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

class AutorisationAbsenceController extends Controller
{
    // ============================================
    // ENSEIGNANT — Crée et soumet la demande (étape 1)
    // Envoyée automatiquement au Chef de Département
    // ============================================
    public function store(Request $request, $beneficiaireId)
    {
        $request->validate([
            'motif_mission'     => 'required|string|max:255',
            'lieu_deplacement'  => 'required|string|max:255',
            'periode_debut'     => 'required|date',
            'periode_fin'       => 'required|date|after_or_equal:periode_debut',
            'organisme_charge'  => 'required|string',
        ]);

        $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])
            ->where('id', $beneficiaireId)
            ->where('enseignant_id', auth()->id())
            ->where('dans_liste_definitive', true)
            ->firstOrFail();

        if (!$beneficiaire->voyage->arrete_recteur) {
            return response()->json([
                'message' => "L'arrete n'a pas encore ete signe par le Recteur"
            ], 403);
        }

        $enseignant = auth()->user();

        $numero = 'AA-' . date('Y') . '-' . str_pad(
            AutorisationAbsence::whereYear('created_at', date('Y'))->count() + 1,
            4, '0', STR_PAD_LEFT
        );

        $autorisation = AutorisationAbsence::create([
            'beneficiaire_id'   => $beneficiaire->id,
            'enseignant_id'     => $enseignant->id,
            'numero'            => $numero,
            'date_presentation' => now(),
            'nom_demandeur'     => $enseignant->prenom . ' ' . $enseignant->nom,
            'fonction'          => $enseignant->fonction ?? 'Enseignant-Chercheur',
            'ufr_departement'   => $enseignant->ufr,
            'motif_mission'     => $request->motif_mission,
            'lieu_deplacement'  => $request->lieu_deplacement,
            'periode_debut'     => $request->periode_debut,
            'periode_fin'       => $request->periode_fin,
            'organisme_charge'  => $request->organisme_charge,
            'signature_enseignant' => true,
            'statut'            => 'soumise',
        ]);

        $beneficiaire->update(['statut_autorisation' => 'demande_chef_dept']);

        // Notifier le(s) Chef(s) de Département de l'UFR concerné
        $chefsDept = User::where('role', 'chef_departement')->where('ufr', $enseignant->ufr)->get();
        foreach ($chefsDept as $chef) {
            Notification::create([
                'user_id' => $chef->id,
                'type'    => 'demande_autorisation',
                'titre'   => "Demande d'autorisation d'absence — " . $numero,
                'message' => $enseignant->prenom . ' ' . $enseignant->nom . ' demande une autorisation d\'absence pour ' . $request->lieu_deplacement . '. Veuillez donner votre avis.',
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message'       => 'Demande soumise avec succes au Chef de Departement',
            'autorisation'  => $autorisation,
        ], 201);
    }

    // ============================================
    // CHEF DE DÉPARTEMENT — Donne son avis + signe (étape 2)
    // Envoie au Directeur UFR si favorable
    // ============================================
    public function avisChefDepartement(Request $request, $id)
    {
        $request->validate([
            'avis'        => 'required|in:favorable,defavorable',
            'commentaire' => 'nullable|string',
        ]);

        $autorisation = AutorisationAbsence::with(['enseignant', 'beneficiaire.voyage'])->findOrFail($id);

        $autorisation->update([
            'chef_departement_id'           => auth()->id(),
            'avis_chef_departement'         => $request->avis,
            'commentaire_chef_departement'  => $request->commentaire,
            'date_avis_chef_departement'    => now(),
            'statut'                        => $request->avis === 'favorable' ? 'avis_chef_departement' : 'rejetee',
        ]);

        if ($request->avis === 'defavorable') {
            Notification::create([
                'user_id' => $autorisation->enseignant_id,
                'type'    => 'autorisation_rejetee',
                'titre'   => 'Demande rejetee par le Chef de Departement',
                'message' => 'Votre demande d\'autorisation d\'absence a ete rejetee.' . ($request->commentaire ? ' Motif : ' . $request->commentaire : ''),
                'lu'      => false,
            ]);

            return response()->json(['message' => 'Avis defavorable enregistre', 'autorisation' => $autorisation]);
        }

        // Avis favorable -> transmettre au Directeur UFR
        $directeurUfr = User::where('role', 'directeur_ufr')->where('ufr', $autorisation->ufr_departement)->first()
            ?? User::where('role', 'directeur_ufr')->first();

        if ($directeurUfr) {
            Notification::create([
                'user_id' => $directeurUfr->id,
                'type'    => 'autorisation_a_approuver',
                'titre'   => 'Autorisation d\'absence a approuver — ' . $autorisation->numero,
                'message' => 'Le Chef de Departement a donne un avis favorable pour ' . $autorisation->nom_demandeur . '. Veuillez approuver ou refuser.',
                'lu'      => false,
            ]);
        }

        return response()->json(['message' => 'Avis favorable transmis au Directeur UFR', 'autorisation' => $autorisation]);
    }

    // ============================================
    // DIRECTEUR UFR — Approuve ou refuse + signe (étape 3)
    // Envoie au Recteur si approuvé
    // ============================================
    public function avisDirecteurUfr(Request $request, $id)
    {
        $request->validate([
            'avis'        => 'required|in:favorable,defavorable',
            'commentaire' => 'nullable|string',
        ]);

        $autorisation = AutorisationAbsence::with('enseignant')->findOrFail($id);

        $autorisation->update([
            'directeur_ufr_id'          => auth()->id(),
            'avis_directeur_ufr'        => $request->avis,
            'commentaire_directeur_ufr' => $request->commentaire,
            'date_avis_directeur_ufr'   => now(),
            'statut'                    => $request->avis === 'favorable' ? 'avis_directeur_ufr' : 'rejetee',
        ]);

        if ($request->avis === 'defavorable') {
            Notification::create([
                'user_id' => $autorisation->enseignant_id,
                'type'    => 'autorisation_rejetee',
                'titre'   => 'Demande refusee par le Directeur UFR',
                'message' => 'Votre demande d\'autorisation d\'absence a ete refusee.' . ($request->commentaire ? ' Motif : ' . $request->commentaire : ''),
                'lu'      => false,
            ]);

            return response()->json(['message' => 'Refus enregistre', 'autorisation' => $autorisation]);
        }

        $recteur = User::where('role', 'recteur')->first();
        if ($recteur) {
            Notification::create([
                'user_id' => $recteur->id,
                'type'    => 'autorisation_a_signer',
                'titre'   => 'Autorisation d\'absence a signer — ' . $autorisation->numero,
                'message' => 'Le Directeur UFR a approuve la demande de ' . $autorisation->nom_demandeur . '. Veuillez signer.',
                'lu'      => false,
            ]);
        }

        return response()->json(['message' => 'Approbation transmise au Recteur', 'autorisation' => $autorisation]);
    }

    // ============================================
    // RECTEUR — Signe (étape 4)
    // Envoie au VR
    // ============================================
    public function signerRecteur($id)
    {
        $autorisation = AutorisationAbsence::with('enseignant')->findOrFail($id);

        $autorisation->update([
            'recteur_id'              => auth()->id(),
            'date_signature_recteur'  => now(),
            'statut'                  => 'signee_recteur',
        ]);

        $vr = User::where('role', 'vice_recteur')->first();
        if ($vr) {
            Notification::create([
                'user_id' => $vr->id,
                'type'    => 'autorisation_signee',
                'titre'   => 'Autorisation signee — ' . $autorisation->numero,
                'message' => 'Le Recteur a signe l\'autorisation d\'absence de ' . $autorisation->nom_demandeur . '. Veuillez la transmettre a l\'enseignant.',
                'lu'      => false,
            ]);
        }

        return response()->json(['message' => 'Autorisation signee et transmise au Vice-Recteur', 'autorisation' => $autorisation]);
    }

    // ============================================
    // VICE-RECTEUR — Transmet directement à l'enseignant (étape 5, finale)
    // ============================================
    public function transmettreEnseignant($id)
    {
        $autorisation = AutorisationAbsence::findOrFail($id);

        $autorisation->update([
            'vr_id'                 => auth()->id(),
            'date_transmission_vr'  => now(),
            'statut'                => 'transmise',
        ]);

        $autorisation->beneficiaire()->update(['statut_autorisation' => 'approuve']);

        Notification::create([
            'user_id' => $autorisation->enseignant_id,
            'type'    => 'autorisation_disponible',
            'titre'   => 'Autorisation d\'absence disponible',
            'message' => 'Votre autorisation d\'absence (' . $autorisation->numero . ') a ete signee par le Recteur et transmise par le Vice-Recteur. Vous pouvez la telecharger.',
            'lu'      => false,
        ]);

        return response()->json(['message' => 'Autorisation transmise a l\'enseignant', 'autorisation' => $autorisation]);
    }

    // ============================================
    // VOIR UNE AUTORISATION (toutes parties prenantes)
    // ============================================
    public function show($id)
    {
        $autorisation = AutorisationAbsence::with([
            'enseignant', 'chefDepartement', 'directeurUfr', 'recteur', 'vr', 'beneficiaire.voyage'
        ])->findOrFail($id);

        return response()->json($autorisation);
    }

    // ============================================
    // LISTE — selon le rôle connecté (boîte de réception par étape)
    // ============================================
    public function index()
    {
        $user = auth()->user();

        $query = AutorisationAbsence::with(['enseignant', 'beneficiaire.voyage']);

        $query = match ($user->role) {
            'chef_departement' => $query->where('ufr_departement', $user->ufr)->where('statut', 'soumise'),
            'directeur_ufr'    => $query->where('ufr_departement', $user->ufr)->where('statut', 'avis_chef_departement'),
            'recteur'          => $query->where('statut', 'avis_directeur_ufr'),
            'vice_recteur'     => $query->where('statut', 'signee_recteur'),
            'enseignant'       => $query->where('enseignant_id', $user->id),
            default            => $query,
        };

        return response()->json($query->latest()->get());
    }
      public function destroy($id)
    {
        $autorisation = AutorisationAbsence::findOrFail($id);
        $user = auth()->user();

        if ($user->role === 'enseignant' && $autorisation->enseignant_id !== $user->id) {
            return response()->json(['message' => 'Action non autorisee'], 403);
        }

        $autorisation->delete();

        return response()->json(['message' => 'Demande supprimee']);
    }
}