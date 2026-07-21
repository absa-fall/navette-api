<?php

namespace App\Http\Controllers;

use App\Models\RecapitulatifHebdo;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RecapitulatifHebdoController extends Controller
{
    public function generer(Request $request)
    {
        try {
            $request->validate([
                'semaine_debut' => 'required|date',
                'semaine_fin'   => 'required|date|after:semaine_debut',
            ]);

            $debut = Carbon::parse($request->semaine_debut)->startOfDay();
            $fin   = Carbon::parse($request->semaine_fin)->endOfDay();

            $reservations = Reservation::whereBetween('date_reservation', [$debut, $fin])
                ->where('validee_montee', true)
                ->where('type_profil', '!=', 'vacataire')
                ->get();

            if ($reservations->isEmpty()) {
                return response()->json([
                    'message' => 'Aucune réservation validée trouvée pour cette semaine'
                ], 422);
            }

            $montantTotal = $reservations->sum('montant_retenue');

            $detailParPersonne = [];
            foreach ($reservations as $r) {
                $key = $r->nom . ' ' . $r->prenom;
                if (!isset($detailParPersonne[$key])) {
                    $detailParPersonne[$key] = [
                        'nom'            => $r->nom,
                        'prenom'         => $r->prenom,
                        'ufr'            => $r->ufr,
                        'categorie'      => $r->categorie,
                        'type_profil'    => $r->type_profil,
                        'nombre_trajets' => 0,
                        'montant_total'  => 0,
                        'trajets'        => [],
                    ];
                }
                $detailParPersonne[$key]['nombre_trajets']++;
$detailParPersonne[$key]['montant_total'] += $r->montant_retenue;
$detailParPersonne[$key]['trajets'][] = [
    'date'        => $r->date_reservation,
    'trajet'      => $r->ville_depart . ' -> ' . $r->ville_arrivee,
    'type_trajet' => $r->type_trajet,
    'montant'     => $r->montant_retenue,
];
            }

            $recap = RecapitulatifHebdo::create([
                'sg_vr_id'        => auth()->id(),
                'semaine_debut'   => $debut,
                'semaine_fin'     => $fin,
                'montant_total'   => $montantTotal,
                'statut'          => 'brouillon',
                'date_generation' => now(),
            ]);

            return response()->json([
                'message'             => 'Récapitulatif généré avec succès',
                'recap'               => $recap,
                'montant_total'       => $montantTotal,
                'detail_par_personne' => array_values($detailParPersonne),
                'nombre_reservations' => $reservations->count(),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur génération récapitulatif: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur serveur',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $recaps = RecapitulatifHebdo::with('sgVr')
            ->latest()
            ->get();

        return response()->json($recaps);
    }

    public function show($id)
    {
        $recap = RecapitulatifHebdo::with('sgVr')->findOrFail($id);

        $debut = Carbon::parse($recap->semaine_debut)->startOfDay();
        $fin   = Carbon::parse($recap->semaine_fin)->endOfDay();

        $reservations = Reservation::whereBetween('date_reservation', [$debut, $fin])
            ->where('validee_montee', true)
            ->where('type_profil', '!=', 'vacataire')
            ->get();

        $detailParPersonne = [];
        foreach ($reservations as $r) {
            $key = $r->nom . ' ' . $r->prenom;
            if (!isset($detailParPersonne[$key])) {
                $detailParPersonne[$key] = [
                    'nom'            => $r->nom,
                    'prenom'         => $r->prenom,
                    'ufr'            => $r->ufr,
                    'categorie'      => $r->categorie,
                    'type_profil'    => $r->type_profil,
                    'nombre_trajets' => 0,
                    'montant_total'  => 0,
                    'trajets'        => [],
                ];
            }
           $detailParPersonne[$key]['nombre_trajets']++;
$detailParPersonne[$key]['montant_total'] += $r->montant_retenue;
$detailParPersonne[$key]['trajets'][] = [
    'date'        => $r->date_reservation,
    'trajet'      => $r->ville_depart . ' -> ' . $r->ville_arrivee,
    'type_trajet' => $r->type_trajet,
    'montant'     => $r->montant_retenue,
];
        }

        return response()->json([
            'recap'               => $recap,
            'detail_par_personne' => array_values($detailParPersonne),
            'nombre_reservations' => $reservations->count(),
        ]);
    }

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
            'recap'   => $recap
        ]);
    }

    public function supprimerSelection(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $deleted = RecapitulatifHebdo::whereIn('id', $request->ids)->delete();

        return response()->json([
            'message' => $deleted . ' récapitulatif(s) supprimé(s) avec succès'
        ]);
    }
    public function signer(Request $request, $id)
{
    $request->validate([
        'signature' => 'required|string',
    ]);

    $recap = RecapitulatifHebdo::findOrFail($id);

    if ($recap->sg_vr_id !== auth()->id()) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    if ($recap->signature_sg_vr) {
        return response()->json(['message' => 'Ce récapitulatif est déjà signé'], 422);
    }

    $recap->update([
        'signature_sg_vr' => $request->signature,
    ]);

    return response()->json([
        'message' => 'Signature enregistrée avec succès',
        'recapitulatif' => $recap,
    ]);
}
}