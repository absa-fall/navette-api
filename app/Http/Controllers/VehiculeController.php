<?php

namespace App\Http\Controllers;

use App\Models\Vehicule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VehiculeController extends Controller
{
    public function index()
    {
        $vehicules = Vehicule::all();
        return response()->json($vehicules);
    }

    public function store(Request $request)
    {
        $request->validate([
            'immatriculation'          => 'required|string|unique:vehicules',
            'capacite'                 => 'required|integer|min:1',
            'etat'                     => 'nullable|in:disponible,en_service,en_panne',
            'date_controle_technique'  => 'nullable|date',
        ]);

        // ✅ Génération automatique du QR code
        $qrCode = 'BUS-' . strtoupper(Str::random(8));

        $vehicule = Vehicule::create([
            'immatriculation'         => $request->immatriculation,
            'capacite'                => $request->capacite,
            'etat'                    => $request->etat ?? 'disponible',
            'date_controle_technique' => $request->date_controle_technique,
            'qr_code'                 => $qrCode,
        ]);

        return response()->json([
            'message'  => 'Véhicule ajouté avec succès',
            'vehicule' => $vehicule
        ], 201);
    }

    public function show($id)
    {
        $vehicule = Vehicule::findOrFail($id);
        return response()->json($vehicule);
    }

    public function update(Request $request, $id)
    {
        $vehicule = Vehicule::findOrFail($id);

        $request->validate([
            'immatriculation'         => 'string|unique:vehicules,immatriculation,' . $id,
            'capacite'                => 'integer|min:1',
            'etat'                    => 'in:disponible,en_service,en_panne',
            'date_controle_technique' => 'nullable|date',
        ]);

        $vehicule->update($request->all());

        return response()->json([
            'message'  => 'Véhicule modifié avec succès',
            'vehicule' => $vehicule
        ]);
    }

    public function destroy($id)
    {
        $vehicule = Vehicule::findOrFail($id);
        $vehicule->delete();

        return response()->json([
            'message' => 'Véhicule supprimé avec succès'
        ]);
    }

    public function disponibles()
    {
        $vehicules = Vehicule::where('etat', 'disponible')->get();
        return response()->json($vehicules);
    }
}