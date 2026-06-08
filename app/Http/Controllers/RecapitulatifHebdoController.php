<?php

namespace App\Http\Controllers;

use App\Models\RecapitulatifHebdo;
use App\Models\PresenceNavette;
use App\Models\RegistreTrajet;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RecapitulatifHebdoController extends Controller
{
    // SG VR génère le récapitulatif hebdomadaire
    public function generer(Request $request)
    {
        $request->validate([
            'semaine_debut' => 'required|date',
            'semaine_fin' => 'required|date|after:semaine_debut',
        ]);

        $debut = Carbon::parse($request->semaine_debut);
        $fin = Carbon::parse($request->semaine_fin);

        // Récupérer tous les registres transmis de la semaine
        $registres = RegistreTrajet::where('statut', 'transmis')
            ->whereBetween('date_trajet', [$debut, $fin])
            ->with(['presences.passager', 'ordreMission'])
            ->get();

        if ($registres->isEmpty()) {
            return response()->json([
                'message' => 'Aucun registre trouvé pour cette semaine'
            ], 404);
        }

        // Calculer le montant total (vacataires exclus automatiquement)
        $montantTotal = $registres->sum(function ($registre) {
            return $registre->presences
                ->where('statut_passager', 'permanent')
                ->sum('montant_retenue');
        });

        // Construire le détail par personne
        $detailParPersonne = [];
        foreach ($registres as $registre) {
            foreach ($registre->presences as $presence) {
                if ($presence->statut_passager === 'permanent') {
                    $passagerId = $presence->passager_id;
                    if (!isset($detailParPersonne[$passagerId])) {
                        $detailParPersonne[$passagerId] = [
                            'passager' => $presence->passager,
                            'nombre_trajets' => 0,
                            'montant_total' => 0,
                        ];
                    }
                    $detailParPersonne[$passagerId]['nombre_trajets']++;
                    $detailParPersonne[$passagerId]['montant_total'] += $presence->montant_retenue;
                }
            }
        }

        $recap = RecapitulatifHebdo::create([
            'sg_vr_id' => auth()->id(),
            'semaine_debut' => $debut,
            'semaine_fin' => $fin,
            'montant_total' => $montantTotal,
            'statut' => 'brouillon',
            'date_generation' => now(),
        ]);

        return response()->json([
            'message' => 'Récapitulatif généré avec succès',
            'recap' => $recap,
            'montant_total' => $montantTotal,
            'detail_par_personne' => array_values($detailParPersonne),
            'nombre_registres' => $registres->count(),
        ], 201);
    }

    // Liste des récapitulatifs
    public function index()
    {
        $recaps = RecapitulatifHebdo::with('sgVr')
            ->latest()
            ->get();

        return response()->json($recaps);
    }

    // Voir un récapitulatif avec détails
    public function show($id)
    {
        $recap = RecapitulatifHebdo::with('sgVr')->findOrFail($id);

        $debut = Carbon::parse($recap->semaine_debut);
        $fin = Carbon::parse($recap->semaine_fin);

        // Détail des présences de la semaine
        $registres = RegistreTrajet::where('statut', 'transmis')
            ->whereBetween('date_trajet', [$debut, $fin])
            ->with(['presences.passager', 'ordreMission', 'chauffeur'])
            ->get();

        $detailParPersonne = [];
        foreach ($registres as $registre) {
            foreach ($registre->presences as $presence) {
                if ($presence->statut_passager === 'permanent') {
                    $passagerId = $presence->passager_id;
                    if (!isset($detailParPersonne[$passagerId])) {
                        $detailParPersonne[$passagerId] = [
                            'passager' => $presence->passager,
                            'nombre_trajets' => 0,
                            'montant_total' => 0,
                            'trajets' => [],
                        ];
                    }
                    $detailParPersonne[$passagerId]['nombre_trajets']++;
                    $detailParPersonne[$passagerId]['montant_total'] += $presence->montant_retenue;
                    $detailParPersonne[$passagerId]['trajets'][] = [
                        'date' => $registre->date_trajet,
                        'trajet' => $registre->ordreMission->trajet,
                        'montant' => $presence->montant_retenue,
                    ];
                }
            }
        }

        return response()->json([
            'recap' => $recap,
            'detail_par_personne' => array_values($detailParPersonne),
            'nombre_registres' => $registres->count(),
        ]);
    }

    // SG VR valide le récapitulatif
    public function valider($id)
    {
        $recap = RecapitulatifHebdo::findOrFail($id);

        if ($recap->statut !== 'brouillon') {
            return response()->json([
                'message' => 'Ce récapitulatif est déjà validé'
            ], 422);
        }

        $recap->update(['statut' => 'valide']);

        return response()->json([
            'message' => 'Récapitulatif validé avec succès',
            'recap' => $recap
        ]);
    }
}