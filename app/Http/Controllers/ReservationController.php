<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReservationController extends Controller
{
    private $tarifs = [
        'Dakar - Bambey'     => 2000,
        'Bambey - Dakar'     => 2000,
        'Thies - Bambey'     => 1000,
        'Bambey - Thies'     => 1000,
        'Bambey - Ngouniane' => 500,
        'Ngouniane - Bambey' => 500,
        'Thies - Ngouniane'  => 1000,
        'Ngouniane - Thies'  => 1000,
    ];

    // ============================================================
    // STORE : Créer une réservation (1 ou 2 lignes si aller_retour)
    // ============================================================
    public function store(Request $request)
    {
        $request->validate([
            'nom'               => 'required|string|max:100',
            'prenom'            => 'required|string|max:100',
            'categorie'         => 'required|string|in:PER,PATS,ATR,Vacataire',
            'type_profil'       => 'required|string|in:permanent,non_permanent,contractuel,vacataire',
            'ufr'               => 'required|string|max:100',
            'type_trajet'       => 'required|in:aller,retour,aller_retour',
            'ville_depart'      => 'required|string|max:100',
            'ville_arrivee'     => 'required|string|max:100',
            'date_reservation'  => 'required|date',
            'heure_reservation' => 'required',
        ]);

        $user = auth()->user();
        if (!$user || !$user->qr_code) {
            $user->update(['qr_code' => 'USR-' . strtoupper(Str::random(8))]);
            $user->refresh();
        }

        $montantUnite = $this->tarifs[$request->ville_depart . ' - ' . $request->ville_arrivee]
                     ?? $this->tarifs[$request->ville_arrivee . ' - ' . $request->ville_depart]
                     ?? 0;

        if ($request->type_profil === 'vacataire') {
            $montantUnite = 0;
        }

        $baseData = [
            'user_id'          => $user->id,
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
            'statut'           => 'en_attente_confirmation',
            'montant_retenue'  => $montantUnite,
        ];

        $reservations = [];

        if ($request->type_trajet === 'aller_retour') {
            $groupeId = strtoupper(Str::random(10));

            $aller = Reservation::create(array_merge($baseData, [
                'groupe_id'   => $groupeId,
                'trajet_sens' => 'aller',
            ]));

            $retour = Reservation::create(array_merge($baseData, [
                'groupe_id'    => $groupeId,
                'trajet_sens'  => 'retour',
                'ville_depart' => $request->ville_arrivee,
                'ville_arrivee'=> $request->ville_depart,
            ]));

            $reservations = [$aller, $retour];

        } else {
            $res = Reservation::create(array_merge($baseData, [
                'trajet_sens' => $request->type_trajet,
            ]));
            $reservations = [$res];
        }

        return response()->json([
            'message'      => 'Réservation envoyée ! En attente de confirmation du chauffeur.',
            'reservations' => $reservations,
            'type_trajet'  => $request->type_trajet,
        ]);
    }

    // ============================================================
    // STATUT : Polling passager
    // ============================================================
    public function statut($id)
    {
        $user        = auth()->user();
        $reservation = Reservation::findOrFail($id);

        if ($reservation->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $data = [
            'statut'      => $reservation->statut,
            'reservation' => $reservation,
        ];

        if ($reservation->groupe_id) {
            $liee = Reservation::where('groupe_id', $reservation->groupe_id)
                               ->where('id', '!=', $reservation->id)
                               ->first();
            $data['reservation_liee'] = $liee;
        }

        return response()->json($data);
    }

    // ============================================================
    // CONFIRMER : Chauffeur confirme
    // ============================================================
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

        $passager = User::find($reservation->user_id);
        if ($passager) {
            $sensLabel = $reservation->trajet_sens === 'retour' ? '(Retour)' : '(Aller)';
            Notification::create([
                'user_id' => $passager->id,
                'type'    => 'reservation_confirmee',
                'titre'   => 'Réservation confirmée !',
                'message' => 'Votre réservation ' . $sensLabel . ' du '
                    . Carbon::parse($reservation->date_reservation)->format('d/m/Y')
                    . ' (' . $reservation->ville_depart . ' → ' . $reservation->ville_arrivee
                    . ') est confirmée. Utilisez votre QR code personnel pour monter dans le bus.',
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message'     => 'Réservation confirmée',
            'reservation' => $reservation,
        ]);
    }

    // ============================================================
    // REFUSER : Chauffeur refuse avec motif
    // ============================================================
    public function refuser(Request $request, $id)
    {
        $request->validate([
            'motif' => 'required|string|max:255',
        ]);

        $reservation = Reservation::findOrFail($id);

        if ($reservation->statut !== 'en_attente_confirmation') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $reservation->update([
            'statut'      => 'refusee',
            'motif_refus' => $request->motif,
        ]);

        $passager = User::find($reservation->user_id);
        if ($passager) {
            $sensLabel = $reservation->trajet_sens === 'retour' ? '(Retour)' : '(Aller)';
            Notification::create([
                'user_id' => $passager->id,
                'type'    => 'reservation_refusee',
                'titre'   => 'Réservation refusée',
                'message' => 'Votre réservation ' . $sensLabel . ' du '
                    . Carbon::parse($reservation->date_reservation)->format('d/m/Y')
                    . ' (' . $reservation->ville_depart . ' → ' . $reservation->ville_arrivee
                    . ') a été refusée. Motif : ' . $request->motif,
                'lu'      => false,
            ]);
        }

        return response()->json([
            'message'     => 'Réservation refusée',
            'reservation' => $reservation,
        ]);
    }

    // ============================================================
    // ANNULER : Passager annule + notif chauffeur ET sgvr
    // ============================================================
    public function annuler(Request $request, $id)
    {
        $user        = auth()->user();
        $reservation = Reservation::findOrFail($id);

        if ($reservation->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($reservation->statut !== 'confirmee') {
            return response()->json(['message' => 'Annulation impossible pour ce statut'], 403);
        }

        $reservation->update(['statut' => 'annulee']);

        $sensLabel = $reservation->trajet_sens === 'retour' ? '(Retour)' : '(Aller)';
        $message   = $reservation->prenom . ' ' . $reservation->nom
            . ' a annulé sa réservation ' . $sensLabel . ' du '
            . Carbon::parse($reservation->date_reservation)->format('d/m/Y')
            . ' (' . $reservation->ville_depart . ' → ' . $reservation->ville_arrivee . ').';

        // Notifier le chauffeur
        if ($reservation->chauffeur_id) {
            Notification::create([
                'user_id' => $reservation->chauffeur_id,
                'type'    => 'reservation_annulee',
                'titre'   => 'Réservation annulée par le passager',
                'message' => $message,
                'lu'      => false,
            ]);
        }

        // ✅ NOUVEAU : Notifier tous les SGVR
        $sgvrs = User::where('role', 'sg_vr')->get();
        foreach ($sgvrs as $sgvr) {
            Notification::create([
                'user_id' => $sgvr->id,
                'type'    => 'reservation_annulee',
                'titre'   => 'Réservation annulée',
                'message' => $message,
                'lu'      => false,
            ]);
        }

        return response()->json(['message' => 'Réservation annulée avec succès']);
    }

    // ============================================================
    // SCANNER PASSAGER : Chauffeur scanne le QR fixe du passager
    // ============================================================
    public function scannerPassager(Request $request)
    {
        $request->validate([
            'qr_code_passager' => 'required|string',
        ]);

        $passager = User::where('qr_code', $request->qr_code_passager)->first();

        if (!$passager) {
            return response()->json(['message' => 'QR code invalide'], 404);
        }

        $reservation = Reservation::where('user_id', $passager->id)
            ->where('statut', 'confirmee')
            ->where('validee_montee', false)
            ->whereDate('date_reservation', today())
            ->orderBy('heure_reservation')
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'Aucune réservation confirmée trouvée pour ce passager aujourd\'hui',
            ], 404);
        }

        $reservation->update([
            'validee_montee'   => true,
            'validee_descente' => true,
            'chauffeur_id'     => auth()->id(),
            'statut'           => 'terminee',
            'heure_presence'   => now(),
        ]);

        return response()->json([
            'message'     => 'Présence validée avec succès !',
            'passager'    => $passager->prenom . ' ' . $passager->nom,
            'trajet'      => $reservation->ville_depart . ' → ' . $reservation->ville_arrivee,
            'sens'        => $reservation->trajet_sens,
            'categorie'   => $reservation->categorie,
            'type_profil' => $reservation->type_profil,
            'montant'     => $reservation->montant_retenue,
            'reservation' => $reservation,
        ]);
    }

    // ============================================================
    // SCANNER BUS : Passager scanne le QR du bus
    // ============================================================
    public function scannerBus(Request $request)
    {
        $request->validate(['qr_code_bus' => 'required|string']);

        $vehicule = \App\Models\Vehicule::where('qr_code', $request->qr_code_bus)->first();
        if (!$vehicule) {
            return response()->json(['message' => 'QR code du bus invalide'], 404);
        }

        $user = auth()->user();

        $reservation = Reservation::where('user_id', $user->id)
            ->where('statut', 'confirmee')
            ->whereDate('date_reservation', today())
            ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Aucune réservation confirmée trouvée pour aujourd\'hui'], 404);
        }

        $reservation->update([
            'validee_montee'   => true,
            'validee_descente' => true,
            'vehicule_id'      => $vehicule->id,
            'statut'           => 'terminee',
        ]);

        return response()->json([
            'message'     => 'Montée validée avec succès ! Bon voyage.',
            'reservation' => $reservation,
        ]);
    }

    
    public function mesReservations()
    {
        $user = auth()->user();

        $reservations = Reservation::where('user_id', $user->id)
            ->where('masquee_passager', false) 
            ->orderBy('date_reservation', 'desc')
            ->orderBy('trajet_sens')
            ->get();

        return response()->json([
            'reservations' => $reservations,
            'qr_code_user' => $user->qr_code,
        ]);
    }

    // ============================================================
    // EXPORTER PDF
    // ============================================================
   public function exporterPdf()
{
    $user = auth()->user();

    // Selon le role, on charge les reservations appropriees
    if (in_array($user->role, ['sg_vr', 'drh', 'sg_drh', 'ddl', 'admin'])) {
        // Ces roles voient toutes les reservations
        $reservations = Reservation::orderBy('date_reservation', 'desc')->get();
    } elseif ($user->role === 'chauffeur') {
        // Le chauffeur voit uniquement les reservations qu'il a confirmees
        $reservations = Reservation::where('chauffeur_id', $user->id)
            ->orderBy('date_reservation', 'desc')
            ->get();
    } else {
        // Usager, enseignant : uniquement ses propres reservations
        $reservations = Reservation::where('user_id', $user->id)
            ->orderBy('date_reservation', 'desc')
            ->orderBy('trajet_sens')
            ->get();
    }

    $pdf = Pdf::loadView('pdfs.mes-reservations', [
        'user'         => $user,
        'reservations' => $reservations,
    ])->setPaper('a4', 'portrait');

    return $pdf->download('reservations-' . $user->nom . '.pdf');
}

    // ============================================================
    // POUR CHAUFFEUR
    // ============================================================
    public function pourChauffeur(Request $request)
    {
        $chauffeurId = auth()->id();

        $ordreMission = \App\Models\OrdreMission::where('chauffeur_id', $chauffeurId)
            ->whereIn('statut', ['transmis_chauffeur', 'execute'])
            ->where('masque_chauffeur', false)
            ->latest()
            ->first();

        if (!$ordreMission) {
            return response()->json([]);
        }

        $reservations = Reservation::whereIn('statut', [
            'en_attente_confirmation', 'confirmee', 'en_cours', 'terminee', 'annulee'
        ])
        ->whereDate('date_reservation', $ordreMission->date_depart)
        ->orderBy('heure_reservation')
        ->get();

        return response()->json($reservations);
    }
    public function annulerChauffeur(Request $request, $id)
{
    $request->validate([
        'motif' => 'required|string|max:255',
    ]);
 
    $reservation = Reservation::findOrFail($id);
 
    if ($reservation->statut !== 'confirmee') {
        return response()->json(['message' => 'Annulation impossible pour ce statut'], 403);
    }
 
    $reservation->update([
        'statut'                    => 'annulee',
        'motif_annulation_chauffeur'=> $request->motif,
    ]);
 
    $sensLabel = $reservation->trajet_sens === 'retour' ? '(Retour)' : '(Aller)';
 
    // ✅ Notifier le passager
    $passager = User::find($reservation->user_id);
    if ($passager) {
        Notification::create([
            'user_id' => $passager->id,
            'type'    => 'reservation_annulee_chauffeur',
            'titre'   => 'Réservation annulée par le chauffeur',
            'message' => 'Votre réservation ' . $sensLabel . ' du '
                . Carbon::parse($reservation->date_reservation)->format('d/m/Y')
                . ' (' . $reservation->ville_depart . ' → ' . $reservation->ville_arrivee
                . ') a été annulée par le chauffeur. Motif : ' . $request->motif,
            'lu'      => false,
        ]);
    }
 
    // ✅ Notifier les SGVR
    $sgvrs = User::where('role', 'sg_vr')->get();
    foreach ($sgvrs as $sgvr) {
        Notification::create([
            'user_id' => $sgvr->id,
            'type'    => 'reservation_annulee_chauffeur',
            'titre'   => 'Réservation annulée par le chauffeur',
            'message' => 'Le chauffeur a annulé la réservation ' . $sensLabel . ' de '
                . $reservation->prenom . ' ' . $reservation->nom . ' du '
                . Carbon::parse($reservation->date_reservation)->format('d/m/Y')
                . '. Motif : ' . $request->motif,
            'lu'      => false,
        ]);
    }
 
    return response()->json(['message' => 'Réservation annulée avec succès']);
}
 
// ============================================================
// RÉACTIVER : Chauffeur réactive une résa annulée → confirmée
// ============================================================
public function reactiver(Request $request, $id)
{
    $request->validate([
        'motif' => 'required|string|max:255',
    ]);
 
    $reservation = Reservation::findOrFail($id);
 
    if ($reservation->statut !== 'annulee') {
        return response()->json(['message' => 'Réactivation impossible pour ce statut'], 403);
    }
 
    $reservation->update([
        'statut'                    => 'confirmee',
        'chauffeur_id'              => auth()->id(),
        'motif_annulation_chauffeur'=> null, // reset
    ]);
 
    $sensLabel = $reservation->trajet_sens === 'retour' ? '(Retour)' : '(Aller)';
 
    //  Notifier le passager
    $passager = User::find($reservation->user_id);
    if ($passager) {
        Notification::create([
            'user_id' => $passager->id,
            'type'    => 'reservation_reactivee',
            'titre'   => 'Bonne nouvelle ! Réservation réactivée',
            'message' => 'Votre réservation ' . $sensLabel . ' du '
                . Carbon::parse($reservation->date_reservation)->format('d/m/Y')
                . ' (' . $reservation->ville_depart . ' → ' . $reservation->ville_arrivee
                . ') a été réactivée par le chauffeur. Motif : ' . $request->motif
                . '. Utilisez votre QR code personnel pour monter dans le bus.',
            'lu'      => false,
        ]);
    }
 
    return response()->json([
        'message'     => 'Réservation réactivée avec succès',
        'reservation' => $reservation,
    ]);
}

    // ============================================================
    // POUR SGVR
    // ============================================================
    public function pourSGVR()
    {
        try {
            $reservations = Reservation::with('user:id,qr_code,email')
                ->select([
                    'id', 'user_id', 'groupe_id', 'trajet_sens',
                    'nom', 'prenom', 'categorie', 'type_profil',
                    'ufr', 'ville_depart', 'ville_arrivee', 'type_trajet',
                    'date_reservation', 'heure_reservation', 'statut',
                    'validee_montee', 'validee_descente',
                    'montant_retenue', 'motif_refus', 'created_at',
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($reservations);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du chargement des réservations',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // SUPPRIMER MA RÉSERVATION
    // ✅ CORRECTION : masquer au lieu de supprimer en DB
    // ============================================================
    public function supprimerMaReservation($id)
    {
        $user        = auth()->user();
        $reservation = Reservation::findOrFail($id);

        if ($reservation->user_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // ✅ On masque pour le passager, le SGVR continue de voir la réservation
        $reservation->update(['masquee_passager' => true]);

        return response()->json(['message' => 'Réservation supprimée de votre liste']);
    }

    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->delete();
        return response()->json(['message' => 'Réservation supprimée']);
    }

    public function verifierQR($qrCode)
    {
        $user = User::where('qr_code', $qrCode)->first();
        if ($user) {
            $reservation = Reservation::where('user_id', $user->id)
                ->where('statut', 'confirmee')
                ->whereDate('date_reservation', today())
                ->first();
            return response()->json([
                'type'        => 'user',
                'passager'    => $user,
                'reservation' => $reservation,
            ]);
        }

        return response()->json(['message' => 'QR code invalide'], 404);
    }

    public function updateMontant(Request $request, $id)
    {
        $request->validate(['montant_retenue' => 'required|numeric|min:0']);
        $reservation = Reservation::findOrFail($id);
        $reservation->update(['montant_retenue' => $request->montant_retenue]);
        return response()->json(['message' => 'Montant mis à jour', 'reservation' => $reservation]);
    }

    public function validerMontee(Request $request)
    {
        $request->validate(['qr_code' => 'required|string']);

        $passager = User::where('qr_code', $request->qr_code)->first();
        if (!$passager) {
            return response()->json(['message' => 'QR code invalide'], 400);
        }

        $reservation = Reservation::where('user_id', $passager->id)
            ->where('validee_montee', false)
            ->whereDate('date_reservation', today())
            ->where('statut', 'confirmee')
            ->first();

        if (!$reservation) {
            return response()->json(['message' => 'QR code invalide ou déjà validé'], 400);
        }

        $reservation->update([
            'validee_montee' => true,
            'statut'         => 'en_cours',
        ]);

        return response()->json([
            'message'     => 'Montée validée avec succès',
            'reservation' => $reservation,
            'passager'    => $passager->nom . ' ' . $passager->prenom,
            'categorie'   => $reservation->categorie,
            'type_profil' => $reservation->type_profil,
        ]);
    }

    public function validerDescente(Request $request)
    {
        $request->validate(['qr_code' => 'required|string']);

        $passager = User::where('qr_code', $request->qr_code)->first();
        if (!$passager) {
            return response()->json(['message' => 'QR code invalide'], 400);
        }

        $reservation = Reservation::where('user_id', $passager->id)
            ->where('validee_montee', true)
            ->where('validee_descente', false)
            ->whereDate('date_reservation', today())
            ->first();

        if (!$reservation) {
            return response()->json(['message' => 'QR code invalide ou déjà validé'], 400);
        }

        $reservation->update([
            'validee_descente' => true,
            'statut'           => 'terminee',
        ]);

        return response()->json([
            'message'     => 'Descente validée avec succès',
            'reservation' => $reservation,
        ]);
    }

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
                    'trajets'      => [],
                ];
            }
            $recapitulatif[$key]['total_trajets']++;
            $recapitulatif[$key]['montant_total'] += $r->montant_retenue;
            $recapitulatif[$key]['trajets'][] = [
                'date'    => $r->date_reservation,
                'trajet'  => $r->ville_depart . ' → ' . $r->ville_arrivee,
                'sens'    => $r->trajet_sens,
                'montant' => $r->montant_retenue,
            ];
        }

        return response()->json([
            'periode'       => ['debut' => $debutSemaine, 'fin' => $finSemaine],
            'recapitulatif' => array_values($recapitulatif),
            'total_general' => array_sum(array_column($recapitulatif, 'montant_total')),
        ]);
    }
}