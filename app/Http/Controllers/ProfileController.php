<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
class ProfileController extends Controller
{
   public function me(Request $request)
{
    $user = $request->user();
    return response()->json([
        'id'            => $user->id,
        'prenom'        => $user->prenom,
        'nom'           => $user->nom,
        'email'         => $user->email,
        'role'          => $user->role,
        'avatar'        => $user->avatar ? Storage::url($user->avatar) : null,
        
        'type_profil'   => $user->type_profil,
        'statut'        => $user->statut,
        'ufr'           => $user->ufr,
        'departement'   => $user->departement,
        'matricule'     => $user->matricule,
        'tel'           => $user->tel,
        'grade_fonction'=> $user->grade_fonction,
        'nationalite'   => $user->nationalite,
        'date_embauche' => $user->date_embauche,
        'qr_code'       => $user->qr_code,
        'is_active'     => $user->is_active,
    ]);
}
public function deleteAvatar(Request $request)
{
    $user = $request->user();
    if ($user->avatar) {
        Storage::disk('public')->delete($user->avatar);
        $user->update(['avatar' => null]);
    }
    return response()->json(['message' => 'Photo supprimée']);
}

   public function uploadAvatar(Request $request)
{
    try {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'avatar' => Storage::url($path),
            'message' => 'Avatar mis a jour',
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ], 500);
    }
}
public function changePassword(Request $request)
   {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => [
                'required',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&._\-#])[A-Za-z\d@$!%*?&._\-#]{8,}$/'
            ],
        ], [
            'new_password.regex' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial (@$!%*?&._-#)',
        ]);
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return response()->json(['message' => 'Mot de passe modifié avec succès']);
   }
}