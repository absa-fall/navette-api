<?php

namespace App\Http\Controllers;

use App\Models\VoyageEtude;
use App\Models\User;
use Illuminate\Http\Request;

class VoyageEtudeController extends Controller
{
    // PER permanent soumet une demande
    public function store(Request $request)
    {
        $user = auth()->user();

        // Règle métier R1 : seuls les PER permanents
       if ($user->role !== 'enseignant' || $user->statut !== 'permanent') {
            return response()->json([
                'message' => 'Seuls les enseignants PER permanents peuvent soumettre une demande de voyage d\'études'
            ], 403);
        }

        // Règle métier R1 : délai de 2 ans
        if (!VoyageEtude::estEligible($user->id)) {
            return response()->json([
                'message' => 'Vous devez attendre 2 ans entre chaque voyage d\'études'
            ], 403);
        }

        // Règle métier R3 : rapport manquant bloque
        $voyageEnCours = VoyageEtude::where('enseignant_id', $user->id)
            ->where('statut', 'approuve')
            ->whereDoesntHave('rapport')
            ->exists();

        if ($voyageEnCours) {
            return response()->json([
                'message' => 'Vous avez un rapport de voyage en attente. Veuillez le soumettre avant de faire une nouvelle demande.'
            ], 403);
        }

        $request->validate([
            'destination' => 'required|string',
            'date_debut' => 'required|date|after:today',
            'date_fin' => 'required|date|after:date_debut',
            'objet' => 'required|string',
        ]);

        // Trouver le Vice-Recteur
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
            'message' => 'Demande de voyage soumise au Vice-Recteur',
            'voyage' => $voyage
        ], 201);
    }

    // Liste des voyages selon le rôle
    public function index()
    {
        $user = auth()->user();

        $voyages = match($user->role) {
            'ddl' => VoyageEtude::where('enseignant_id', $user->id)
                ->with(['rapport'])
                ->latest()->get(),
            'vice_recteur' => VoyageEtude::where('statut', 'en_attente')
                ->with(['enseignant'])
                ->latest()->get(),
            'admin' => VoyageEtude::with(['enseignant', 'viceRecteur', 'rapport'])
                ->latest()->get(),
            default => collect()
        };

        return response()->json($voyages);
    }

    // Voir un voyage
    public function show($id)
    {
        $voyage = VoyageEtude::with([
            'enseignant',
            'viceRecteur',
            'rapport'
        ])->findOrFail($id);

        return response()->json($voyage);
    }

    // Vice-Recteur approuve
    public function approuver(Request $request, $id)
    {
        $voyage = VoyageEtude::findOrFail($id);

        if ($voyage->statut !== 'en_attente') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        // Date limite rapport = date_fin + 30 jours
        $dateLimiteRapport = \Carbon\Carbon::parse($voyage->date_fin)->addDays(30);

        $voyage->update([
            'statut' => 'approuve',
            'vice_recteur_id' => auth()->id(),
            'ordre_mission_genere' => true,
            'date_limite_rapport' => $dateLimiteRapport,
        ]);

        return response()->json([
            'message' => 'Voyage approuvé. Ordre de mission généré automatiquement.',
            'voyage' => $voyage
        ]);
    }

    // Vice-Recteur rejette
    public function rejeter(Request $request, $id)
    {
        $request->validate([
            'commentaire_vr' => 'required|string'
        ]);

        $voyage = VoyageEtude::findOrFail($id);

        if ($voyage->statut !== 'en_attente') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

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

    // Vérifier l'éligibilité de l'utilisateur connecté
    public function verifierEligibilite()
    {
        $user = auth()->user();

        if ($user->type_profil !== 'PER' || $user->statut !== 'permanent') {
            return response()->json([
                'eligible' => false,
                'message' => 'Vous n\'êtes pas PER permanent'
            ]);
        }

        $eligible = VoyageEtude::estEligible($user->id);

        return response()->json([
            'eligible' => $eligible,
            'message' => $eligible
                ? 'Vous êtes éligible pour soumettre une demande'
                : 'Vous devez attendre 2 ans entre chaque voyage'
        ]);
    }
}