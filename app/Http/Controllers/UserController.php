<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Liste tous les utilisateurs (admin)
    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }

    // Créer un utilisateur (admin)
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:ddl,drh,sg_drh,chauffeur,sg_vr,vice_recteur,admin',
            'type_profil' => 'nullable|in:PER,PATS,ATR',
            'statut' => 'nullable|in:permanent,non_permanent,contractuel,vacataire',
            'ufr' => 'nullable|in:SATIC,SDD,ECOMIJ,ISFAR',
            'matricule' => 'nullable|string|unique:users',
            'tel' => 'nullable|string',
        ]);

        $user = User::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'type_profil' => $request->type_profil,
            'statut' => $request->statut,
            'ufr' => $request->ufr,
            'matricule' => $request->matricule,
            'tel' => $request->tel,
        ]);

        return response()->json([
            'message' => 'Utilisateur créé avec succès',
            'user' => $user
        ], 201);
    }

    // Voir un utilisateur
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // Modifier un utilisateur
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $user->update($request->except(['password', 'email']));

        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return response()->json([
            'message' => 'Utilisateur modifié avec succès',
            'user' => $user
        ]);
    }

    // Activer / désactiver un compte
    public function toggleActive($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Compte activé' : 'Compte désactivé',
            'user' => $user
        ]);
    }

    // Liste des chauffeurs
    public function chauffeurs()
    {
        $chauffeurs = User::where('role', 'chauffeur')->get();
        return response()->json($chauffeurs);
    }

    // Liste des DRH
    public function drhs()
    {
        $drhs = User::where('role', 'drh')->get();
        return response()->json($drhs);
    }
}