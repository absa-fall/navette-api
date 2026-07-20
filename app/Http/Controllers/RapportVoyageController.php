<?php

namespace App\Http\Controllers;

use App\Models\RapportVoyage;
use App\Models\VoyageEtude;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf; // composer require barryvdh/laravel-dompdf si pas déjà installé

class RapportVoyageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
    'voyage_id'   => 'nullable|exists:voyages_etudes,id',
    'contenu'     => 'nullable|string',
    'fichier_pdf' => 'nullable|file|mimes:pdf|max:10240',
]);

        if (!$request->filled('contenu') && !$request->hasFile('fichier_pdf')) {
            return response()->json(['message' => 'Veuillez rédiger le rapport ou joindre un fichier PDF.'], 422);
        }

        $beneficiaire = null;

if ($request->filled('voyage_id')) {
    $beneficiaire = \App\Models\VoyageEtudeBeneficiaire::where('voyage_id', $request->voyage_id)
        ->where('enseignant_id', auth()->id())
        ->first();

    if (!$beneficiaire) {
        return response()->json(['message' => 'Vous n\'etes pas beneficiaire de ce voyage'], 403);
    }
}

        $fichierPath = null;

        if ($request->hasFile('fichier_pdf')) {
            $fichierPath = $request->file('fichier_pdf')->store('rapports', 'public');
        } else {
            $enseignant = auth()->user();
           $donnees = json_decode($request->contenu, true) ?? [];

$pdf = Pdf::loadView('pdf.rapport-voyage', [
    'donnees'     => $donnees,
    'enseignant'  => $enseignant,
    'voyage'      => $beneficiaire?->voyage,
    'date'        => now(),
]);

           $identifiant = $beneficiaire->id ?? auth()->id();
$nomFichier = 'rapports/rapport_' . $identifiant . '_' . now()->timestamp . '.pdf';
            \Storage::disk('public')->put($nomFichier, $pdf->output());
            $fichierPath = $nomFichier;
        }

        $rapport = RapportVoyage::create([
    'voyage_id'     => $request->voyage_id ?: null,
    'enseignant_id' => auth()->id(),
    'contenu'       => $request->contenu ?? '',
    'fichier_pdf'   => $fichierPath,
    'date_depot'    => null,
    'statut'        => 'brouillon',
]);

        return response()->json([
            'message' => 'Brouillon enregistré. Relisez-le puis transmettez-le une fois signé.',
            'rapport' => $rapport
        ], 201);
    }
public function rattacherVoyage(Request $request, $id)
{
    $request->validate([
        'voyage_id' => 'required|exists:voyages_etudes,id',
    ]);

    $rapport = RapportVoyage::where('id', $id)
        ->where('enseignant_id', auth()->id())
        ->firstOrFail();

    $beneficiaire = \App\Models\VoyageEtudeBeneficiaire::where('voyage_id', $request->voyage_id)
        ->where('enseignant_id', auth()->id())
        ->first();

    if (!$beneficiaire) {
        return response()->json(['message' => 'Vous n\'êtes pas bénéficiaire de ce voyage'], 403);
    }

    $rapport->update(['voyage_id' => $request->voyage_id]);

    // Si le rapport a été rédigé en texte (pas un PDF téléversé), on régénère
    // le PDF avec les vraies infos du voyage maintenant qu'il est rattaché,
    // pour qu'il ressemble à un rapport rédigé directement pour ce voyage.
    if (!empty($rapport->contenu)) {
        $donnees = json_decode($rapport->contenu, true) ?? [];
        $enseignant = auth()->user();

        $pdf = Pdf::loadView('pdf.rapport-voyage', [
            'donnees'    => $donnees,
            'enseignant' => $enseignant,
            'voyage'     => $beneficiaire->voyage,
            'date'       => now(),
            'rapport'    => $rapport,
        ]);

        $nomFichier = 'rapports/rapport_' . $rapport->id . '_' . now()->timestamp . '.pdf';
        \Storage::disk('public')->put($nomFichier, $pdf->output());
        $rapport->update(['fichier_pdf' => $nomFichier]);
    }

    return response()->json([
        'message' => 'Rapport rattaché au voyage avec succès',
        'rapport' => $rapport->fresh(),
    ]);
}

public function transmettre(Request $request, $id)
{
    $request->validate([
        'signature'               => 'required|string',
        'justificatifs'           => 'nullable|array',
        'justificatifs.*'         => 'file|mimes:pdf|max:10240',
        'justificatifs_autres'    => 'nullable|array|max:5',
        'justificatifs_autres.*'  => 'file|mimes:pdf|max:10240',
        'destination_libre'       => 'nullable|string|max:255',
        'date_debut_libre'        => 'nullable|date',
        'date_fin_libre'          => 'nullable|date',
    ]);

    $rapport = RapportVoyage::with('voyage')->findOrFail($id);

    if ($rapport->enseignant_id !== auth()->id()) {
        return response()->json(['message' => 'Action non autorisée'], 403);
    }

    if ($rapport->statut !== 'brouillon') {
        return response()->json(['message' => 'Ce rapport a déjà été transmis.'], 403);
    }

    $beneficiaire = \App\Models\VoyageEtudeBeneficiaire::where('voyage_id', $rapport->voyage_id)
        ->where('enseignant_id', auth()->id())
        ->first();

    // Même contrôle de date limite qu'avant, appliqué avant toute écriture
    if ($beneficiaire && $beneficiaire->date_limite_soumission && now()->greaterThan($beneficiaire->date_limite_soumission)) {
        return response()->json([
            'message' => 'La date limite du ' . $beneficiaire->date_limite_soumission->format('d/m/Y') . ' pour renvoyer vos justificatifs est depassee. Contactez le Vice-Recteur.',
        ], 422);
    }

    return DB::transaction(function () use ($request, $rapport, $beneficiaire) {

       
        $rapport->update(array_filter([
            'signature_enseignant' => $request->signature,
            'statut'               => 'soumis',
            'date_depot'           => now(),
            'destination_libre'    => !$rapport->voyage_id ? $request->destination_libre : null,
            'date_debut_libre'     => !$rapport->voyage_id ? $request->date_debut_libre : null,
            'date_fin_libre'       => !$rapport->voyage_id ? $request->date_fin_libre : null,
        ], fn($v, $k) => !in_array($k, ['destination_libre','date_debut_libre','date_fin_libre']) || $v !== null, ARRAY_FILTER_USE_BOTH));
// Régénère le PDF avec la signature maintenant disponible, dans le même format que la vue React
$donnees = json_decode($rapport->contenu, true) ?? [];
$enseignant = auth()->user();

$pdf = Pdf::loadView('pdf.rapport-voyage', [
    'donnees'    => $donnees,
    'enseignant' => $enseignant,
    'voyage'     => $rapport->voyage,
    'date'       => now(),
    'rapport'    => $rapport, // pour accéder à date_depot, signature_enseignant, statut, commentaire_vr, destination_libre, date_debut_libre, date_fin_libre
]);

$nomFichier = 'rapports/rapport_' . $rapport->id . '_' . now()->timestamp . '.pdf';
\Storage::disk('public')->put($nomFichier, $pdf->output());
$rapport->update(['fichier_pdf' => $nomFichier]);
        $estRenvoi = false;

        if ($beneficiaire) {
            $estRenvoi = $beneficiaire->statut_justificatif === 'incomplet';

            // 2. Le rapport lui-même compte comme pièce du dossier
            \App\Models\VoyageEtudeJustificatif::create([
                'beneficiaire_id' => $beneficiaire->id,
                'fichier_pdf'     => $rapport->fichier_pdf,
                'nom_original'    => 'Rapport_de_voyage_' . $rapport->id . '.pdf',
            ]);

            // 3. Justificatifs obligatoires (talons, caches, invitation) envoyés en même temps
            if ($request->hasFile('justificatifs')) {
                foreach ($request->file('justificatifs') as $fichier) {
                    $path = $fichier->store('justificatifs', 'public');
                    \App\Models\VoyageEtudeJustificatif::create([
                        'beneficiaire_id' => $beneficiaire->id,
                        'fichier_pdf'     => $path,
                        'nom_original'    => $fichier->getClientOriginalName(),
                    ]);
                }
            }

            // 4. Justificatifs optionnels supplémentaires
            if ($request->hasFile('justificatifs_autres')) {
                foreach ($request->file('justificatifs_autres') as $fichier) {
                    $path = $fichier->store('justificatifs', 'public');
                    \App\Models\VoyageEtudeJustificatif::create([
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
        }

        // 5. Notifications — dossier complet (rapport + justificatifs) au VR et à la Commission
        $enseignant     = auth()->user();
        $nomEnseignant  = $enseignant->prenom . ' ' . $enseignant->nom;
        $destination    = $rapport->voyage?->destination ?? '';
        $libelleAction  = $estRenvoi ? 'renvoyé son dossier corrigé (rapport + justificatifs)' : 'soumis son dossier complet (rapport + justificatifs)';
        $rappelMotif    = ($estRenvoi && $beneficiaire?->dernier_motif_rejet)
            ? ' Rappel du motif de rejet précédent : ' . $beneficiaire->dernier_motif_rejet
            : '';

        $vr = \App\Models\User::where('role', 'vice_recteur')->first();
        if ($vr) {
            \App\Models\Notification::create([
                'user_id' => $vr->id,
                'type'    => 'justificatif_soumis',
                'titre'   => $estRenvoi ? 'Dossier corrigé et renvoyé' : 'Dossier complet reçu',
                'message' => $nomEnseignant . ' a ' . $libelleAction . ' pour le voyage à ' . $destination . '.' . $rappelMotif,
                'lu'      => false,
            ]);
        }

        $commission = \App\Models\User::where('role', 'commission')->get();
        foreach ($commission as $membre) {
            \App\Models\Notification::create([
                'user_id' => $membre->id,
                'type'    => 'dossier_a_valider',
                'titre'   => $estRenvoi ? 'Dossier corrigé à valider' : 'Nouveau dossier à valider',
                'message' => $nomEnseignant . ' a ' . $libelleAction . ' pour le voyage à ' . $destination . '.' . $rappelMotif,
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message' => 'Rapport et justificatifs transmis avec succès.',
            'rapport' => $rapport->fresh(),
        ]);
    });
}

    // Récupère le rapport de l'enseignant connecté pour un voyage donné (ou null si pas encore rédigé)
    public function monRapportPourVoyage($voyageId)
    {
        $rapport = RapportVoyage::where('voyage_id', $voyageId)
            ->where('enseignant_id', auth()->id())
            ->latest()
            ->first();

        if (!$rapport) {
            return response()->json(null, 200);
        }

        return response()->json($rapport);
    }



// Liste des rapports selon le rôle
public function index()
{
    $user = auth()->user();

    $rapports = match($user->role) {
        'ddl' => $this->avecJustificatifs(
            RapportVoyage::where('enseignant_id', $user->id)->with(['voyage'])->latest()->get()
        ),
        'enseignant' => $this->avecJustificatifs(
            RapportVoyage::where('enseignant_id', $user->id)->with(['voyage'])->latest()->get()
        ),
        // Le VR ne doit voir que les rapports réellement transmis, pas les brouillons
        'vice_recteur' => RapportVoyage::where('statut', 'soumis')
            ->with(['enseignant', 'voyage'])
            ->latest()->get(),
        'admin' => RapportVoyage::with(['enseignant', 'voyage'])
            ->latest()->get(),
        default => collect()
    };

    return response()->json($rapports);
}


private function avecJustificatifs($rapports)
{
    return $rapports->each(function ($rapport) {
        $beneficiaire = \App\Models\VoyageEtudeBeneficiaire::where('voyage_id', $rapport->voyage_id)
            ->where('enseignant_id', $rapport->enseignant_id)
            ->with('justificatifs')
            ->first();

        $rapport->setRelation('justificatifs', $beneficiaire?->justificatifs ?? collect());
    });
}

// Voir un rapport
    // Voir un rapport
    public function show($id)
    {
        $rapport = RapportVoyage::with([
            'enseignant',
            'voyage'
        ])->findOrFail($id);

        return response()->json($rapport);
    }

    public function valider($id)
{
    $rapport = RapportVoyage::with('voyage')->findOrFail($id);

    if ($rapport->statut !== 'soumis') {
        return response()->json(['message' => 'Action non autorisée'], 403);
    }

    $rapport->update(['statut' => 'valide']);

    \App\Models\Notification::create([
        'user_id' => $rapport->enseignant_id,
        'type'    => 'rapport_valide',
        'titre'   => 'Rapport de voyage validé',
        'message' => 'Votre rapport de voyage à ' . ($rapport->voyage->destination ?? '') . ' a été validé par le Vice-Recteur.',
        'lu'      => false,
    ]);

    return response()->json([
        'message' => 'Rapport validé avec succès',
        'rapport' => $rapport
    ]);
}

public function rejeter(Request $request, $id)
{
    $request->validate([
        'commentaire_vr' => 'required|string'
    ]);

    $rapport = RapportVoyage::with('voyage')->findOrFail($id);

    if ($rapport->statut !== 'soumis') {
        return response()->json(['message' => 'Action non autorisée'], 403);
    }

    $rapport->update([
        'statut' => 'rejete',
        'commentaire_vr' => $request->commentaire_vr,
    ]);

    \App\Models\Notification::create([
        'user_id' => $rapport->enseignant_id,
        'type'    => 'rapport_rejete',
        'titre'   => 'Rapport de voyage rejeté',
        'message' => 'Votre rapport de voyage à ' . ($rapport->voyage->destination ?? '') . ' a été rejeté. Motif : ' . $request->commentaire_vr,
        'lu'      => false,
    ]);

    return response()->json([
        'message' => 'Rapport rejeté',
        'rapport' => $rapport
    ]);
}

    public function download($id)
    {
        $rapport = RapportVoyage::with('voyage')->findOrFail($id);

        $user = auth()->user();

        if ($user->role !== 'vice_recteur' && $rapport->enseignant_id !== $user->id) {
            return response()->json(['message' => 'Accès interdit'], 403);
        }

        if (!$rapport->fichier_pdf) {
            return response()->json(['message' => 'Aucun fichier PDF pour ce rapport'], 404);
        }

        $cheminComplet = storage_path('app/public/' . $rapport->fichier_pdf);

        if (!file_exists($cheminComplet)) {
            return response()->json(['message' => 'Le fichier PDF est introuvable sur le serveur'], 404);
        }

        return response()->file($cheminComplet);
    }

    // Enseignant re-soumet un rapport rejeté (repart directement en "soumis",
    // ce cas particulier ne repasse pas par l'étape brouillon)
    public function resoumettre(Request $request, $id)
    {
        $request->validate([
            'contenu' => 'nullable|string',
            'fichier_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if (!$request->filled('contenu') && !$request->hasFile('fichier_pdf')) {
            return response()->json(['message' => 'Veuillez rédiger le rapport ou joindre un fichier PDF.'], 422);
        }

        $rapport = RapportVoyage::findOrFail($id);

        if ($rapport->enseignant_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        if ($rapport->statut !== 'rejete') {
            return response()->json(['message' => 'Vous ne pouvez re-soumettre qu\'un rapport rejeté'], 403);
        }

        $fichierPath = $rapport->fichier_pdf;

        if ($request->hasFile('fichier_pdf')) {
            $fichierPath = $request->file('fichier_pdf')->store('rapports', 'public');
        } elseif ($request->filled('contenu') && $request->contenu !== $rapport->contenu) {
           $donnees = json_decode($request->contenu, true) ?? [];

$pdf = Pdf::loadView('pdf.rapport-voyage', [
    'donnees'    => $donnees,
    'enseignant' => auth()->user(),
    'voyage'     => $rapport->voyage,
    'date'       => now(),
]);
            $nomFichier = 'rapports/rapport_' . $rapport->id . '_' . now()->timestamp . '.pdf';
            \Storage::disk('public')->put($nomFichier, $pdf->output());
            $fichierPath = $nomFichier;

            \App\Models\VoyageEtudeJustificatif::where('fichier_pdf', $rapport->fichier_pdf)
                ->update(['fichier_pdf' => $fichierPath]);
        }

       $rapport->update([
            'contenu' => $request->filled('contenu') ? $request->contenu : $rapport->contenu,
            'fichier_pdf' => $fichierPath,
            'date_depot' => now(),
            'statut' => 'soumis',
            'commentaire_vr' => null,
        ]);

        // Le rapport corrigé repart en évaluation : les anciens avis (VR et/ou commission,
        // donnés sur la version rejetée) ne doivent plus s'appliquer à cette nouvelle version.
        $beneficiaire = \App\Models\VoyageEtudeBeneficiaire::where('voyage_id', $rapport->voyage_id)
            ->where('enseignant_id', $rapport->enseignant_id)
            ->first();

        if ($beneficiaire) {
            \App\Models\VoyageEtudeAvis::where('beneficiaire_id', $beneficiaire->id)->delete();

            $beneficiaire->update([
                'statut_justificatif'    => 'soumis',
                'date_limite_soumission' => null,
                'alerte_delai_envoyee'   => false,
            ]);
        }

        return response()->json([
            'message' => 'Rapport re-soumis avec succès',
            'rapport' => $rapport
        ]);
    }

    // Enseignant supprime un rapport de son historique (suppression réelle)
    public function supprimerHistorique($id)
    {
        $rapport = RapportVoyage::findOrFail($id);

        if ($rapport->enseignant_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        if ($rapport->fichier_pdf) {
            \Storage::disk('public')->delete($rapport->fichier_pdf);
        }

        \App\Models\VoyageEtudeJustificatif::where('fichier_pdf', $rapport->fichier_pdf)->delete();

        $rapport->delete();

        return response()->json(['message' => 'Rapport supprimé définitivement']);
    }

    // Suppression en masse depuis l'onglet Historique (sélection multiple avec cases à cocher)
    public function supprimerSelection(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:rapports_voyage,id',
        ]);

        $rapports = RapportVoyage::whereIn('id', $request->ids)
            ->where('enseignant_id', auth()->id())
            ->get();

        foreach ($rapports as $rapport) {
            if ($rapport->fichier_pdf) {
                \Storage::disk('public')->delete($rapport->fichier_pdf);
            }
            \App\Models\VoyageEtudeJustificatif::where('fichier_pdf', $rapport->fichier_pdf)->delete();
            $rapport->delete();
        }

        return response()->json([
            'message' => count($rapports) . ' rapport(s) supprimé(s).',
        ]);
    }
}