<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReservationController extends Controller
{
    // Tarifs selon le CDC
    private $tarifs = [
        'Dakar - Bambey' => 2000,
        'Bambey - Dakar' => 2000,
        'Thies - Bambey' => 1000,
        'Bambey - Thies' => 1000,
        'Bambey - Ngouniane' => 500,
        'Ngouniane - Bambey' => 500,
    ];

    // Créer une réservation
    public function store(Request $request)
    {
        $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'categorie'        => 'required|string|in:PER,PATS,ATR,Vacataire',
            'type_profil'      => 'required|string|in:permanent,non_permanent,contractuel,vacataire',
            'ufr'              => 'required|string|max:100',
            'type_trajet'      => 'required|in:aller,retour,aller_retour',
            'ville_depart'     => 'required|string|max:100',
            'ville_arrivee'    => 'required|string|max:100',
            'date_reservation' => 'required|date',
            'heure_reservation'=> 'required',
        ]);

        $qrCode = strtoupper(Str::random(11));

        $trajet        = $request->ville_depart . ' - ' . $request->ville_arrivee;
        $trajetInverse = $request->ville_arrivee . ' - ' . $request->ville_depart;
        $montant       = $this->tarifs[$trajet] ?? $this->tarifs[$trajetInverse] ?? 0;

        if ($request->type_profil === 'vacataire') {
            $montant = 0;
        }

        if ($request->type_trajet === 'aller_retour') {
            $montant *= 2;
        }

        $reservation = Reservation::create([
            'nom'              => $request->nom,
            'prenom'           => $request->prenom,
            'categorie'        => $request->categorie,
            'type_profil'      => $request->type_profil,
            'ufr'              => $request->ufr,
            'type_trajet'      => $request->type_trajet,
            'ville_depart'     => $request->ville_depart,
            'ville_arrivee'    => $request->ville_arrivee,
            'date_reservation' => $request->date_reservation,
            'heure_reservation'=> $request->heure_reservation,
            'qr_code'          => $qrCode,
            'statut'           => 'en_attente_confirmation',
            'montant_retenue'  => $montant,
        ]);

        return response()->json([
            'message'     => 'Réservation envoyée ! En attente de confirmation du chauffeur.',
            'reservation' => $reservation,
            'qr_code'     => $qrCode,
            'montant'     => $montant,
        ]);
    }

    // Chauffeur confirme une réservation
    public function confirmer(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        if ($reservation->statut !== 'en_attente_confirmation') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $reservation->update([
            'statut'      => 'confirmee',
            'chauffeur_id'=> auth()->id(),
        ]);

        // Notification au passager
        $passager = User::where('nom', $reservation->nom)
            ->where('prenom', $reservation->prenom)
            ->where('role', 'usager')
            ->first();

        if ($passager) {
            Notification::create([
                'user_id' => $passager->id,
                'type'    => 'reservation_confirmee',
                'titre'   => '✅ Réservation confirmée !',
                'message' => 'Votre réservation du ' . Carbon::parse($reservation->date_reservation)->format('d/m/Y') . ' (' . $reservation->ville_depart . ' → ' . $reservation->ville_arrivee . ') a été confirmée. Montez dans le bus et scannez le QR code.',
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message'     => 'Réservation confirmée',
            'reservation' => $reservation
        ]);
    }

    // Chauffeur refuse une réservation
    public function refuser(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        if ($reservation->statut !== 'en_attente_confirmation') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $reservation->update(['statut' => 'refusee']);

        // Notification au passager
        $passager = User::where('nom', $reservation->nom)
            ->where('prenom', $reservation->prenom)
            ->where('role', 'usager')
            ->first();

        if ($passager) {
            Notification::create([
                'user_id' => $passager->id,
                'type'    => 'reservation_refusee',
                'titre'   => '❌ Réservation refusée',
                'message' => 'Votre réservation du ' . Carbon::parse($reservation->date_reservation)->format('d/m/Y') . ' (' . $reservation->ville_depart . ' → ' . $reservation->ville_arrivee . ') a été refusée par le chauffeur.',
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message'     => 'Réservation refusée',
            'reservation' => $reservation
        ]);
    }

    // Passager scanne le QR du bus
    public function scannerBus(Request $request)
    {
        $request->validate([
            'qr_code_bus' => 'required|string'
        ]);

        $vehicule = \App\Models\Vehicule::where('qr_code', $request->qr_code_bus)->first();

        if (!$vehicule) {
            return response()->json([
                'message' => 'QR code du bus invalide'
            ], 404);
        }

        $user = auth()->user();
        $reservation = Reservation::where('statut', 'confirmee')
            ->where('nom', $user->nom)
            ->where('prenom', $user->prenom)
            ->whereDate('date_reservation', today())
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'Aucune réservation confirmée trouvée pour aujourd\'hui'
            ], 404);
        }

       
$reservation->update([
    'validee_montee'  => true,
    'validee_descente' => true,
    'vehicule_id'     => $vehicule->id,
    'statut'          => 'terminee'
]);

        return response()->json([
            'message'     => 'Montée validée avec succès ! Bon voyage.',
            'reservation' => $reservation
        ]);
    }

    // Chauffeur scanne le QR du passager
    public function scannerPassager(Request $request)
    {
        $request->validate([
            'qr_code_passager' => 'required|string'
        ]);

        $passager = User::where('qr_code', $request->qr_code_passager)->first();

        if (!$passager) {
            return response()->json([
                'message' => 'QR code passager invalide'
            ], 404);
        }

        $reservation = Reservation::where('statut', 'confirmee')
            ->where('nom', $passager->nom)
            ->where('prenom', $passager->prenom)
            ->whereDate('date_reservation', today())
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'Aucune réservation confirmée pour ce passager aujourd\'hui'
            ], 404);
        }

       
$reservation->update([
    'validee_montee'  => true,
    'validee_descente' => true,
    'chauffeur_id'    => auth()->id(),
    'statut'          => 'terminee'
]);
        return response()->json([
            'message'     => 'Passager validé avec succès',
            'passager'    => $passager->prenom . ' ' . $passager->nom,
            'reservation' => $reservation
        ]);
    }

    // Valider la montée (scan QR ancien système)
    public function validerMontee(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string'
        ]);

        $reservation = Reservation::where('qr_code', $request->qr_code)
            ->where('validee_montee', false)
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'QR code invalide ou déjà validé'
            ], 400);
        }

        $reservation->update([
            'validee_montee' => true,
            'statut'         => 'en_cours'
        ]);

        return response()->json([
            'message'     => 'Montée validée avec succès',
            'reservation' => $reservation,
            'passager'    => $reservation->nom . ' ' . $reservation->prenom,
            'categorie'   => $reservation->categorie,
            'type_profil' => $reservation->type_profil,
        ]);
    }

    // Valider la descente
    public function validerDescente(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string'
        ]);

        $reservation = Reservation::where('qr_code', $request->qr_code)
            ->where('validee_montee', true)
            ->where('validee_descente', false)
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'QR code invalide ou déjà validé'
            ], 400);
        }

        $reservation->update([
            'validee_descente' => true,
            'statut'           => 'terminee'
        ]);

        return response()->json([
            'message'     => 'Descente validée avec succès',
            'reservation' => $reservation
        ]);
    }

    // Liste des réservations pour le chauffeur
    public function pourChauffeur(Request $request)
    {
        $reservations = Reservation::whereIn('statut', [
            'en_attente_confirmation', 'confirmee', 'en_cours'
        ])
        ->orderBy('date_reservation')
        ->orderBy('heure_reservation')
        ->get();

        return response()->json($reservations);
    }

    // Liste pour le SG VR
    public function pourSGVR()
    {
        try {
            $reservations = Reservation::select([
                'id', 'nom', 'prenom', 'categorie', 'type_profil',
                'ufr', 'ville_depart', 'ville_arrivee', 'date_reservation',
                'heure_reservation', 'statut', 'validee_montee',
                'validee_descente', 'montant_retenue', 'created_at'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json($reservations);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chargement des réservations',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer
    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->delete();
        return response()->json(['message' => 'Réservation supprimée']);
    }

    // Vérifier un QR code
    public function verifierQR($qrCode)
    {
        $reservation = Reservation::where('qr_code', $qrCode)->first();

        if (!$reservation) {
            return response()->json(['message' => 'QR code invalide'], 404);
        }

        return response()->json(['reservation' => $reservation]);
    }

    // Mettre à jour le montant
    public function updateMontant(Request $request, $id)
    {
        $request->validate(['montant_retenue' => 'required|numeric|min:0']);
        $reservation = Reservation::findOrFail($id);
        $reservation->update(['montant_retenue' => $request->montant_retenue]);
        return response()->json(['message' => 'Montant mis à jour', 'reservation' => $reservation]);
    }

    // Récapitulatif hebdomadaire
    public function recapitulatifHebdomadaire(Request $request)
    {
        $debutSemaine = $request->input('debut_semaine', now()->startOfWeek());
        $finSemaine   = $request->input('fin_semaine', now()->endOfWeek());

        $reservations = Reservation::whereBetween('date_reservation', [$debutSemaine, $finSemaine])
            ->where('validee_montee', true)
            ->where('type_profil', '!=', 'vacataire')
            ->get();

        $recapitulatif = [];
        foreach ($reservations as $r) {
            $key = $r->nom . ' ' . $r->prenom;
            if (!isset($recapitulatif[$key])) {
                $recapitulatif[$key] = [
                    'nom'          => $r->nom,
                    'prenom'       => $r->prenom,
                    'categorie'    => $r->categorie,
                    'type_profil'  => $r->type_profil,
                    'total_trajets'=> 0,
                    'montant_total'=> 0,
                    'trajets'      => []
                ];
            }
            $recapitulatif[$key]['total_trajets']++;
            $recapitulatif[$key]['montant_total'] += $r->montant_retenue;
            $recapitulatif[$key]['trajets'][] = [
                'date'    => $r->date_reservation,
                'trajet'  => $r->ville_depart . ' → ' . $r->ville_arrivee,
                'montant' => $r->montant_retenue
            ];
        }

        return response()->json([
            'periode' => [
                'debut' => $debutSemaine,
                'fin'   => $finSemaine
            ],
            'recapitulatif' => array_values($recapitulatif),
            'total_general' => array_sum(array_column($recapitulatif, 'montant_total'))
        ]);
    }
}