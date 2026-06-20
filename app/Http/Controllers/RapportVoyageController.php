<?php

namespace App\Http\Controllers;

use App\Models\RapportVoyage;
use App\Models\VoyageEtude;
use Illuminate\Http\Request;

class RapportVoyageController extends Controller
{
public function store(Request $request)
{
    $request->validate([
        'voyage_id'   => 'required|exists:voyages_etudes,id',
        'contenu'     => 'required|string',
        'fichier_pdf' => 'nullable|file|mimes:pdf|max:10240',
    ]);

    // Vérifier que l'enseignant est bien bénéficiaire de ce voyage
    $beneficiaire = \App\Models\VoyageEtudeBeneficiaire::where('voyage_id', $request->voyage_id)
        ->where('enseignant_id', auth()->id())
        ->first();

    if (!$beneficiaire) {
        return response()->json(['message' => 'Vous n\'etes pas beneficiaire de ce voyage'], 403);
    }

    $fichierPath = null;
    if ($request->hasFile('fichier_pdf')) {
        $fichierPath = $request->file('fichier_pdf')->store('rapports', 'public');
    }

    // 1. Créer le rapport (archivage)
    $rapport = RapportVoyage::create([
        'voyage_id'     => $request->voyage_id,
        'enseignant_id' => auth()->id(),
        'contenu'       => $request->contenu,
        'fichier_pdf'   => $fichierPath,
        'date_depot'    => now(),
        'statut'        => 'soumis',
    ]);

    // 2. Le rapport fait partie des justificatifs du voyage d'études en cours
    //    → on l'ajoute automatiquement aux justificatifs et on notifie le Chef Dép
    if ($fichierPath) {
        \App\Models\VoyageEtudeJustificatif::create([
            'beneficiaire_id' => $beneficiaire->id,
            'fichier_pdf'     => $fichierPath,
            'nom_original'    => 'Rapport_de_voyage_' . $rapport->id . '.pdf',
        ]);
    }

    $beneficiaire->update(['statut_justificatif' => 'soumis']);

    // 3. Notifier le Chef Dép de l'UFR de l'enseignant (même circuit que les justificatifs classiques)
    $enseignant = auth()->user();
    $chefDept = \App\Models\User::where('role', 'chef_departement')
        ->where('ufr', $enseignant->ufr)
        ->first();

    if ($chefDept) {
        \App\Models\Notification::create([
            'user_id' => $chefDept->id,
            'type'    => 'justificatif_soumis',
            'titre'   => 'Rapport soumis comme justificatif — ' . $enseignant->ufr,
            'message' => $enseignant->prenom . ' ' . $enseignant->nom . ' a soumis son rapport de voyage comme justificatif pour le voyage a ' . $beneficiaire->voyage->destination . '. Veuillez le verifier et le transmettre au Vice-Recteur.',
            'lu'      => false,
        ]);
    }

    return response()->json([
        'message' => 'Rapport soumis avec succès au Chef de Departement comme justificatif',
        'rapport' => $rapport
    ], 201);
}
    // Liste des rapports selon le rôle
   public function index()
{
    $user = auth()->user();

    $rapports = match($user->role) {
        'ddl' => RapportVoyage::where('enseignant_id', $user->id)
            ->with(['voyage'])
            ->latest()->get(),
        'enseignant' => RapportVoyage::where('enseignant_id', $user->id)
            ->with(['voyage'])
            ->latest()->get(),
        'vice_recteur' => RapportVoyage::where('statut', 'soumis')
            ->with(['enseignant', 'voyage'])
            ->latest()->get(),
        'admin' => RapportVoyage::with(['enseignant', 'voyage'])
            ->latest()->get(),
        default => collect()
    };

    return response()->json($rapports);
}
    // Voir un rapport
    public function show($id)
    {
        $rapport = RapportVoyage::with([
            'enseignant',
            'voyage'
        ])->findOrFail($id);

        return response()->json($rapport);
    }

    // Vice-Recteur valide le rapport
    public function valider($id)
    {
        $rapport = RapportVoyage::findOrFail($id);

        if ($rapport->statut !== 'soumis') {
            return response()->json([
                'message' => 'Action non autorisée'
            ], 403);
        }

        $rapport->update(['statut' => 'valide']);

        return response()->json([
            'message' => 'Rapport validé avec succès',
            'rapport' => $rapport
        ]);
    }

    // Vice-Recteur rejette le rapport
    public function rejeter(Request $request, $id)
    {
        $request->validate([
            'commentaire_vr' => 'required|string'
        ]);

        $rapport = RapportVoyage::findOrFail($id);

        if ($rapport->statut !== 'soumis') {
            return response()->json([
                'message' => 'Action non autorisée'
            ], 403);
        }

        $rapport->update([
            'statut' => 'rejete',
            'commentaire_vr' => $request->commentaire_vr,
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

    // sécurité
    if ($user->role !== 'vice_recteur' && $rapport->enseignant_id !== $user->id) {
        return response()->json(['message' => 'Accès interdit'], 403);
    }

    if (!$rapport->fichier_pdf) {
        return response()->json(['message' => 'Fichier introuvable'], 404);
    }

    return response()->file(
        storage_path('app/public/' . $rapport->fichier_pdf)
    );
}
    // Enseignant re-soumet un rapport rejeté
    public function resoumettre(Request $request, $id)
    {
        $request->validate([
            'contenu' => 'required|string',
            'fichier_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $rapport = RapportVoyage::findOrFail($id);

        if ($rapport->enseignant_id !== auth()->id()) {
            return response()->json([
                'message' => 'Action non autorisée'
            ], 403);
        }

        if ($rapport->statut !== 'rejete') {
            return response()->json([
                'message' => 'Vous ne pouvez re-soumettre qu\'un rapport rejeté'
            ], 403);
        }

        $fichierPath = $rapport->fichier_pdf;
        if ($request->hasFile('fichier_pdf')) {
            $fichierPath = $request->file('fichier_pdf')
                ->store('rapports', 'public');
        }

        $rapport->update([
            'contenu' => $request->contenu,
            'fichier_pdf' => $fichierPath,
            'date_depot' => now(),
            'statut' => 'soumis',
            'commentaire_vr' => null,
        ]);

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

    // Supprimer le fichier physique s'il existe
    if ($rapport->fichier_pdf) {
        \Storage::disk('public')->delete($rapport->fichier_pdf);
    }

    // Supprimer aussi le justificatif lié si présent
    \App\Models\VoyageEtudeJustificatif::where('fichier_pdf', $rapport->fichier_pdf)->delete();

    $rapport->delete();

    return response()->json(['message' => 'Rapport supprimé définitivement']);
}
}