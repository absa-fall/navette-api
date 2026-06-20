<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'     => $user->id,
            'prenom' => $user->prenom,
            'nom'    => $user->nom,
            'email'  => $user->email,
            'role'   => $user->role,
            'avatar' => $user->avatar ? Storage::url($user->avatar) : null,
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = $request->user();

        // Supprimer ancien avatar
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'avatar' => Storage::url($path),
            'message' => 'Avatar mis à jour',
        ]);
    }
}