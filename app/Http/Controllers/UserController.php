<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Mail\CodeActivationMail;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    public function index()
    {
        $actor = auth()->user();

        
        if ($actor->role === 'ddl') {
            $users = User::where('role', 'chauffeur')->get();
        } else {
            $users = User::all();
        }

        return response()->json($users);
    }

   public function store(Request $request)
{
    $actor = auth()->user();
    $isDdl = $actor->role === 'ddl';

    $rules = [
        'nom' => 'required|string',
        'prenom' => 'required|string',
        'email' => 'required|email|unique:users',
        'matricule' => 'nullable|string|unique:users',
        'tel' => 'nullable|string',
        'nationalite' => 'nullable|string',
        'date_embauche' => 'nullable|date',
    ];

    if ($isDdl) {
        $role = 'chauffeur';
        $typeProfil = null;
        $statut = null;
        $ufr = null;
        // Mot de passe par défaut : nom (en minuscule, sans espace) + 1234
        $defaultPassword = strtolower(str_replace(' ', '', $request->nom)) . '1234';
        $plainPassword = $defaultPassword;
    } else {
        $rules['password'] = 'required|min:6';
        $rules['role'] = 'required|in:ddl,drh,sg_drh,chauffeur,sg_vr,vice_recteur,admin,chef_departement,directeur_ufr,recteur,commission,enseignant';
        $rules['type_profil'] = 'nullable|in:PER,PATS,ATR';
        $rules['statut'] = 'nullable|in:permanent,non_permanent,contractuel,vacataire';
        $rules['ufr'] = 'nullable|in:SATIC,SDD,ECOMIJ,ISFAR';

        $role = $request->role;
        $typeProfil = $request->type_profil;
        $statut = $request->statut;
        $ufr = $request->ufr;
        $plainPassword = $request->password;
    }

    $request->validate($rules);


    $code = (string) random_int(100000, 999999);

    $user = User::create([
        'nom' => $request->nom,
        'prenom' => $request->prenom,
        'email' => $request->email,
        'password' => Hash::make($plainPassword),
        'role' => $role,
        'type_profil' => $typeProfil,
        'statut' => $statut,
        'ufr' => $ufr,
        'matricule' => $request->matricule,
        'tel' => $request->tel,
        'nationalite' => $request->nationalite,
        'date_embauche' => $request->date_embauche,
        'compte_actif' => false,
        'code_activation' => Hash::make($code),
        'code_activation_expire_at' => now()->addHours(48),
    ]);

    Mail::to($user->email)->send(new CodeActivationMail($user, $code));

    $response = [
        'message' => 'Utilisateur créé avec succès',
        'user' => $user,
    ];

    
    if ($isDdl) {
        $response['mot_de_passe_genere'] = $plainPassword;
    }

    return response()->json($response, 201);
}

    public function show($id)
    {
        $user = User::findOrFail($id);
        $actor = auth()->user();

       
        if ($actor->role === 'ddl' && $user->role !== 'chauffeur') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $actor = auth()->user();
        $isDdl = $actor->role === 'ddl';

        if ($isDdl) {
            
            if ($user->role !== 'chauffeur') {
                return response()->json(['message' => 'Action non autorisée'], 403);
            }

           
           $data = $request->only(['nom', 'prenom', 'matricule', 'tel', 'nationalite']);
        } else {
            $data = $request->except(['password', 'email']);
        }

        $user->update($data);

        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return response()->json([
            'message' => 'Utilisateur modifié avec succès',
            'user' => $user->fresh()
        ]);
    }

    public function destroy($id)
    {
        $actor = auth()->user();

        
        if ($actor->role === 'ddl') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé avec succès']);
    }

    public function toggleActive($id)
    {
        $user = User::findOrFail($id);
        $actor = auth()->user();

        
        if ($actor->role === 'ddl' && $user->role !== 'chauffeur') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Compte activé' : 'Compte désactivé',
            'user' => $user
        ]);
    }

    public function chauffeurs()
{
    $chauffeurs = User::where('role', 'chauffeur')->get();
    return response()->json($chauffeurs);
}

    public function drhs()
    {
        $drhs = User::where('role', 'drh')->get();
        return response()->json($drhs);
    }

    public function enseignantsPermanents()
    {
        $enseignants = User::where('role', 'enseignant')
            ->where('statut', 'permanent')
            ->where('is_active', true)
            ->get();

        return response()->json($enseignants);
    }
    public function changeOwnPassword(Request $request)
{
    $request->validate([
        'current_password' => 'required',
       'new_password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
    ]);

    $user = auth()->user();

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json(['message' => 'Mot de passe actuel incorrect'], 422);
    }

    $user->update(['password' => Hash::make($request->new_password)]);

    return response()->json(['message' => 'Mot de passe modifié avec succès']);
}
}