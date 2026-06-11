<?php

namespace App\Http\Controllers;

use App\Models\VoyageEtude;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class VoyageEtudeController extends Controller
{
    // =========================
    // 1. SOUMISSION VOYAGE
    // =========================
    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role !== 'enseignant' || $user->statut !== 'permanent') {
            return response()->json([
                'message' => 'Seuls les enseignants PER permanents peuvent soumettre une demande'
            ], 403);
        }

        if (!VoyageEtude::estEligible($user->id)) {
            return response()->json([
                'message' => 'Vous devez attendre 2 ans entre chaque voyage'
            ], 403);
        }

        $request->validate([
            'destination' => 'required|string',
            'date_debut' => 'required|date|after:today',
            'date_fin' => 'required|date|after:date_debut',
            'objet' => 'required|string',
        ]);

        $viceRecteur = User::where('role', 'vice_recteur')->first();

        $voyage = VoyageEtude::create([
            'enseignant_id' => $user->id,
            'vice_recteur_id' => $viceRecteur?->id,
            'destination' => $request->destination,
            'date_debut' => $request->date_debut,
            'date_fin' => $request->date_fin,
            'objet' => $request->objet,
            'statut' => 'en_attente',
        ]);

        return response()->json([
            'message' => 'Demande soumise',
            'voyage' => $voyage
        ], 201);
    }

    // =========================
    // 2. LISTE VOYAGES (IMPORTANT)
    // =========================
    public function index()
    {
        $user = auth()->user();

        $voyages = VoyageEtude::with(['rapport', 'enseignant', 'viceRecteur'])
            ->when($user->role === 'enseignant', function ($q) use ($user) {
                $q->where('enseignant_id', $user->id);
            })
            ->when($user->role === 'vice_recteur', function ($q) {
                $q->where('statut', 'en_attente');
            })
            ->latest()
            ->get();

        return response()->json($voyages);
    }

    // =========================
    // 3. VOIR UN VOYAGE
    // =========================
    public function show($id)
    {
        $voyage = VoyageEtude::with(['rapport', 'enseignant', 'viceRecteur'])
            ->findOrFail($id);

        return response()->json($voyage);
    }

    // =========================
    // 4. APPROUVER
    // =========================
    public function approuver($id)
    {
        $voyage = VoyageEtude::findOrFail($id);

        $voyage->update([
            'statut' => 'approuve',
            'vice_recteur_id' => auth()->id(),
            'ordre_mission_genere' => true,
            'date_limite_rapport' => Carbon::parse($voyage->date_fin)->addDays(30),
        ]);

        return response()->json([
            'message' => 'Voyage approuvé',
            'voyage' => $voyage
        ]);
    }

    // =========================
    // 5. REJETER
    // =========================
    public function rejeter(Request $request, $id)
    {
        $request->validate([
            'commentaire_vr' => 'required|string'
        ]);

        $voyage = VoyageEtude::findOrFail($id);

        $voyage->update([
            'statut' => 'rejete',
            'commentaire_vr' => $request->commentaire_vr,
            'vice_recteur_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Voyage rejeté',
            'voyage' => $voyage
        ]);
    }

    // =========================
    // 6. ÉLIGIBILITÉ
    // =========================
    public function verifierEligibilite()
    {
        $user = auth()->user();

        if ($user->role !== 'enseignant' || $user->statut !== 'permanent') {
            return response()->json([
                'eligible' => false,
                'message' => 'Vous n\'êtes pas PER permanent'
            ]);
        }

        return response()->json([
            'eligible' => VoyageEtude::estEligible($user->id),
        ]);
    }
}