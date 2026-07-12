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
        'date_publication' => 'required|date',
        'motif'            => 'required|string',
        'enseignants'      => 'required|array',
        'enseignants.*'    => 'exists:users,id',
    ]);

    $voyage = VoyageEtude::create([
        'vice_recteur_id'  => auth()->id(),
        'date_publication' => $request->date_publication,
        'motif'            => $request->motif,
        'destination'      => $request->motif, // garder pour compatibilité
        'date_debut'       => $request->date_publication,
        'date_fin'         => $request->date_publication,
        'statut_liste'     => 'publiee',
    ]);

    foreach ($request->enseignants as $enseignantId) {
        VoyageEtudeBeneficiaire::create([
            'voyage_id'     => $voyage->id,
            'enseignant_id' => $enseignantId,
        ]);
    }

    // Notifier les Chefs de Département
    $chefsDept = User::where('role', 'chef_departement')->get();
    foreach ($chefsDept as $chef) {
        Notification::create([
            'user_id' => $chef->id,
            'type'    => 'voyage_etude_publie',
            'titre'   => 'Nouvelle liste de voyage d\'etudes publiee',
            'message' => 'Le Vice-Recteur a publie une liste de beneficiaires pour : ' . $request->motif . '. Veuillez informer les enseignants concernes.',
            'lu'      => false,
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
        ->where('masque_enseignant', false)
        ->with(['voyage.viceRecteur', 'autorisationAbsence'])
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

        // Notifier le Chef de Département 
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
    ->whereHas('voyage', fn($q) => $q->whereIn('statut_liste', ['publiee', 'definitive'])
        ->where('masque_chef_departement', false))
    ->whereHas('enseignant', fn($q) => $q->where('ufr', $chefUFR))
    ->latest()->get();
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
// Supprimer un voyage (VR ou Chef Dept)
public function destroy($id)
{
    $voyage = VoyageEtude::findOrFail($id);
    $user = auth()->user();

    if ($user->role === 'vice_recteur') {
        $voyage->update(['masque_vr' => true]);
        return response()->json(['message' => 'Voyage masqué de votre vue']);
    }

    if ($user->role === 'chef_departement') {
        $voyage->update(['masque_chef_departement' => true]);
        return response()->json(['message' => 'Voyage masqué de votre vue']);
    }

    $voyage->delete();
    return response()->json(['message' => 'Voyage supprimé']);
}

    // ============================================
    // VR + COMMISSION — Voir dossiers à valider
    // ============================================
  public function dossiersAValider()
{
    $user = auth()->user();
    $champ = $user->role === 'commission' ? 'masque_commission' : 'masque_vice_recteur';

    $dossiers = VoyageEtudeBeneficiaire::with([
        'enseignant', 'voyage', 'justificatifs', 'avis.user', 'autorisationAbsence'
    ])
        ->where($champ, false)
        ->whereIn('statut_justificatif', ['transmis_vr', 'valide', 'incomplet'])
        ->latest()
        ->get()
        ->map(function ($d) {
            $arr = $d->toArray();
            $arr['autorisation_absence_id'] = $d->autorisationAbsence?->id;
            return $arr;
        });

    return response()->json($dossiers);
}

    // ============================================
    // VR + COMMISSION — Donner un avis sur un dossier
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
               if ($request->avis === 'valide') {
    Notification::create([
        'user_id' => $vr->id,
        'type'    => 'dossier_valide_commission',
        'titre'   => 'Dossier valide par la commission — Transmis au VR',
        'message' => 'La commission a valide le dossier de ' . $nomEns . ' pour le voyage a ' . $destination . ' et l\'a transmis pour votre validation finale.' . ($request->commentaire ? ' Commentaire : ' . $request->commentaire : ''),
        'lu'      => false,
    ]);
} else {
    Notification::create([
        'user_id' => $vr->id,
        'type'    => 'avis_commission',
        'titre'   => 'Dossier rejete par la commission — Transmis au VR',
        'message' => 'La commission a rejete le dossier de ' . $nomEns . ' pour le voyage a ' . $destination . ' et l\'a transmis pour votre information.' . ($request->commentaire ? ' Raison : ' . $request->commentaire : ''),
        'lu'      => false,
    ]);
}
            }
        } catch (\Exception $e) {
            \Log::error('Notification avis commission: ' . $e->getMessage());
        }
    }
 
    // Marquer comme rejeté si VR rejette
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
 
   // Si VR valide → statut valide + valider le rapport lié automatiquement
if ($request->avis === 'valide' && $auteur->role === 'vice_recteur') {
    $beneficiaire->update(['statut_justificatif' => 'valide']);

    // Valider automatiquement le rapport de l'enseignant pour ce voyage
    \App\Models\RapportVoyage::where('enseignant_id', $beneficiaire->enseignant_id)
        ->where('voyage_id', $beneficiaire->voyage_id)
        ->where('statut', '!=', 'valide')
        ->update([
            'statut'        => 'valide',
            'commentaire_vr' => $request->commentaire ?? 'Validé automatiquement avec le dossier de voyage',
        ]);
// Rejeter aussi le rapport lié
    \App\Models\RapportVoyage::where('enseignant_id', $beneficiaire->enseignant_id)
        ->where('voyage_id', $beneficiaire->voyage_id)
        ->where('statut', '!=', 'valide')
        ->update([
            'statut'         => 'rejete',
            'commentaire_vr' => $request->commentaire ?? 'Rejeté avec le dossier de voyage',
        ]);
    // Notifier l'enseignant que son rapport est validé
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
 
    // Vérifier que tous les bénéficiaires remplissent les conditions obligatoires
    $beneficiairesSelectionnes = VoyageEtudeBeneficiaire::with(['avis.user'])
        ->whereIn('id', $request->beneficiaires)
        ->get();
 
    $erreurs = [];
    foreach ($beneficiairesSelectionnes as $b) {
        $enseignant = User::find($b->enseignant_id);
        $nomEns     = $enseignant ? $enseignant->prenom . ' ' . $enseignant->nom : 'Enseignant #' . $b->enseignant_id;
 
        // 1. Justificatifs obligatoires
        if (!in_array($b->statut_justificatif, ['transmis_vr', 'valide'])) {
            $erreurs[] = $nomEns . ' : justificatifs non soumis';
            continue;
        }
 
        // 2. Avis commission obligatoire
        $avisCommission = $b->avis->filter(fn($a) => $a->user?->role === 'commission' && $a->avis === 'valide');
        if ($avisCommission->isEmpty()) {
            $erreurs[] = $nomEns . ' : avis commission manquant';
            continue;
        }
 
        // 3. Avis VR obligatoire
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
 
    VoyageEtudeBeneficiaire::whereIn('id', $request->beneficiaires)
        ->update(['dans_liste_definitive' => true]);
 
    $voyage->update(['statut_liste' => 'definitive']);
 
    // Notifier le Recteur
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
 
    // Notifier les Chefs de Département par UFR
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
// Masquer un voyage pour le rôle connecté
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

// Masquer un dossier justificatif pour le rôle connecté
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
    // ============================================
// RECTEUR — Approuver l'autorisation de sortie → transmet au VR
// ============================================
public function approuverAutorisationRecteur($beneficiaireId)
{
    $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage'])->findOrFail($beneficiaireId);

    $beneficiaire->update([
        'statut_autorisation' => 'approuve_recteur',
    ]);

    // Transmettre au VR (pas directement à l'enseignant)
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
// ============================================
// VR — Transmettre l'autorisation finale a l'enseignant
// ============================================
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
    // ============================================
    // LISTE TOUS LES VOYAGES (VR + Recteur)
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

    // Filtre selon le rôle
    if ($user->role === 'vice_recteur') {
        $query->where('masque_vr', false);
    }
    // Le recteur voit tous les voyages définitifs (pas de filtre masque)
    if ($user->role === 'recteur') {
        $query->where('statut_liste', 'definitive');
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
    public function voirAutorisationSortie($beneficiaireId)
{
    $beneficiaire = VoyageEtudeBeneficiaire::with(['enseignant', 'voyage', 'justificatifs'])
        ->findOrFail($beneficiaireId);

    // Enseignant → uniquement son propre dossier
    if (auth()->user()->role === 'enseignant' && $beneficiaire->enseignant_id !== auth()->id()) {
        return response()->json(['message' => 'Accès refusé'], 403);
    }

    return response()->json([
        'statut_autorisation' => $beneficiaire->statut_autorisation,
        'autorisation_sortie' => $beneficiaire->autorisation_sortie,
        'beneficiaire'        => $beneficiaire,
    ]);
}
}