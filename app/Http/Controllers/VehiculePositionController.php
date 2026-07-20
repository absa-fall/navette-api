<?php

namespace App\Http\Controllers;

use App\Models\Vehicule;
use Illuminate\Http\Request;

class VehiculePositionController extends Controller
{
    // Chauffeur envoie sa position (rattachée au véhicule)
    public function update(Request $request, $vehiculeId)
    {
        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $vehicule = Vehicule::findOrFail($vehiculeId);

        $vehicule->update([
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'position_maj_at' => now(),
            'suivi_actif'     => true,
        ]);

        return response()->json(['message' => 'Position mise à jour']);
    }

    // Chauffeur arrête le suivi
    public function stop($vehiculeId)
    {
        $vehicule = Vehicule::findOrFail($vehiculeId);

        $vehicule->update(['suivi_actif' => false]);

        return response()->json(['message' => 'Suivi arrêté']);
    }

    // Usager consulte la position actuelle
    public function show($vehiculeId)
    {
        $vehicule = Vehicule::findOrFail($vehiculeId);

        if (!$vehicule->suivi_actif) {
            return response()->json(['suivi_actif' => false]);
        }

        return response()->json([
            'suivi_actif'     => true,
            'latitude'        => $vehicule->latitude,
            'longitude'       => $vehicule->longitude,
            'position_maj_at' => $vehicule->position_maj_at,
        ]);
    }
}