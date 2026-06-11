<?php

namespace App\Http\Controllers;

use App\Models\RapportVoyage;
use App\Models\VoyageEtude;
use Illuminate\Http\Request;

class RapportVoyageController extends Controller
{
    // Enseignant soumet son rapport
   public function store(Request $request)
{
    $request->validate([
        'voyage_id' => 'required|exists:voyages_etudes,id',
        'contenu' => 'required|string',
        'fichier_pdf' => 'nullable|file|mimes:pdf|max:10240',
    ]);

    $voyage = VoyageEtude::findOrFail($request->voyage_id);

    if ($voyage->enseignant_id !== auth()->id()) {
        return response()->json(['message' => 'Accès interdit'], 403);
    }

    if ($voyage->statut !== 'approuve') {
        return response()->json(['message' => 'Voyage non approuvé'], 403);
    }

    // 🔴 ICI (ton code upload PDF)
    $fichierPath = null;

    if ($request->hasFile('fichier_pdf')) {
        $fichierPath = $request->file('fichier_pdf')
            ->store('rapports', 'public');
    }

    $rapport = RapportVoyage::create([
        'voyage_id' => $request->voyage_id,
        'enseignant_id' => auth()->id(),
        'contenu' => $request->contenu,
        'fichier_pdf' => $fichierPath,
        'date_depot' => now(),
        'statut' => 'soumis',
    ]);

    return response()->json([
        'message' => 'Rapport soumis avec succès',
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
}