<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
           'password' => 'required',
        ]);

        if ($request->email !== strtolower($request->email)) {
            return response()->json([
                'message' => 'L\'email doit être saisi uniquement en minuscules.'
            ], 422);
        }

        $email = trim($request->email);
        $user  = User::where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte est désactivé'
            ], 403);
        }

       // Générer le QR automatiquement si l'usager n'en a pas
if ($user->role === 'usager' && !$user->qr_code) {
    $user->update(['qr_code' => 'UADB-' . strtoupper(Str::random(8))]);
}
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => [
                'id'          => $user->id,
                'nom'         => $user->nom,
                'prenom'      => $user->prenom,
                'email'       => $user->email,
                'role'        => $user->role,
                'type_profil' => $user->type_profil,
                'statut'      => $user->statut,
                'ufr'         => $user->ufr,
                'qr_code'     => $user->qr_code,
            ],
            'token' => $token,
        ]);
    }
public function register(Request $request)
{
    $request->validate([
        'nom'           => 'required|string|max:100',
        'prenom'        => 'required|string|max:100',
        'email'         => 'required|email|unique:users,email',
        'password'      => 'required|min:6',
        'tel'           => 'nullable|string',
        'matricule'     => 'nullable|string|unique:users,matricule',
        'type_profil' => 'nullable|in:PER,PATS,ATR,vacataire',
        'statut'        => 'nullable|in:permanent,non_permanent,contractuel,vacataire',
        'ufr'           => 'nullable|in:SATIC,SDD,ECOMIJ,ISFAR',
        'departement'   => 'nullable|string|max:100',
        'date_embauche' => 'nullable|date',
    ]);

    
    $role = ($request->type_profil === 'PER') ? 'enseignant' : 'usager';

    $qrCode = 'UADB-' . strtoupper(Str::random(8));

    $user = User::create([
        'nom'           => $request->nom,
        'prenom'        => $request->prenom,
        'email'         => strtolower(trim($request->email)),
        'password'      => Hash::make($request->password),
        'tel'           => $request->tel,
        'matricule'     => $request->matricule,
        'type_profil'   => $request->type_profil,
        'statut'        => $request->statut,
        'ufr'           => $request->ufr,
        'departement'   => $request->departement,
        'role'          => $role,
        'qr_code'       => $qrCode,
        'date_embauche' => $request->date_embauche,
    ]);

    return response()->json([
        'message' => 'Compte créé avec succès',
        'user'    => $user,
    ], 201);
}
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

   public function me(Request $request)
{
    $user = $request->user();
    
    return response()->json([
        'user' => [
            'id'          => $user->id,
            'nom'         => $user->nom,
            'prenom'      => $user->prenom,
            'email'       => $user->email,
            'role'        => $user->role,
            'type_profil' => $user->type_profil,
            'statut'      => $user->statut,
            'ufr'         => $user->ufr,
            'qr_code'     => $user->qr_code,
        ]
    ]);
}
   public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email',
    ]);

    $status = Password::sendResetLink(
        $request->only('email')
    );

    if ($status === Password::RESET_LINK_SENT) {
        return response()->json([
            'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email.'
        ]);
    }

    return response()->json([
        'message' => 'Impossible d\'envoyer le lien. Vérifiez votre adresse email.'
    ], 422);
}

public function resetPassword(Request $request)
{
    $request->validate([
        'token'    => 'required',
        'email'    => 'required|email',
        'password' => 'required|min:6|confirmed',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            event(new PasswordReset($user));
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.'
        ]);
    }

    return response()->json([
        'message' => 'Ce lien de réinitialisation est invalide ou expiré.'
    ], 422);
} 
}
