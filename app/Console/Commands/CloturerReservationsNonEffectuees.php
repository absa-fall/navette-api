<?php

namespace App\Console\Commands;

use App\Models\OrdreMission;
use App\Models\Reservation;
use App\Models\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CloturerReservationsNonEffectuees extends Command
{
    protected $signature = 'reservations:cloturer-non-effectuees';

    protected $description = 'Clôture automatiquement les réservations non honorées (retour non effectué / annulée) 24h après la date pertinente, indépendamment du clic "Exécuter" du chauffeur. Ignore les navettes en incident.';

    public function handle()
    {
        $seuil = now()->subDay();

        $totalRetour = 0;
        $totalAnnulees = 0;

        // Cas 1 — Aller-retour, retour jamais validé, 24h après la date de retour
        // de l'ordre de mission (indépendant du clic "Exécuter" du chauffeur).
        $ordresRetourEcheance = OrdreMission::where('statut', '!=', 'incident')
            ->whereNotNull('date_retour')
            ->where('date_retour', '<=', $seuil)
            ->get();

        foreach ($ordresRetourEcheance as $ordre) {
            $totalRetour += Reservation::where('navette_id', $ordre->id)
                ->where('type_trajet', 'aller_retour')
                ->where('trajet_sens', 'retour')
                ->where('validee_montee', false)
                ->whereNotIn('statut', ['terminee', 'annulee', 'refusee', 'incident'])
                ->update(['statut' => 'retour_non_effectue']);
        }

        // Cas 2 — Réservation simple (aller seul ou retour seul), jamais validée,
        // 24h après la date de réservation PROPRE à chaque passager (pas la date
        // de départ de l'ordre de mission — deux passagers du même ordre peuvent
        // avoir des date_reservation différentes).
        $reservations = Reservation::where('type_trajet', '!=', 'aller_retour')
            ->where('validee_montee', false)
            ->where('date_reservation', '<=', $seuil)
            ->whereNotIn('statut', ['terminee', 'annulee', 'refusee', 'incident'])
            ->where(function ($q) {
                // On exclut les navettes en incident, mais on garde les
                // réservations sans navette rattachée (navette_id null),
                // pour lesquelles il n'y a pas d'ordre de mission à vérifier.
                $q->whereNull('navette_id')
                  ->orWhereHas('navette', function ($q2) {
                      $q2->where('statut', '!=', 'incident');
                  });
            })
            ->get();

        foreach ($reservations as $r) {
            $r->update(['statut' => 'annulee']);
            $totalAnnulees++;

            Notification::create([
                'user_id' => $r->user_id,
                'type'    => 'reservation_annulee_absence',
                'titre'   => 'Réservation annulée',
                'message' => 'Votre réservation du '
                    . Carbon::parse($r->date_reservation)->format('d/m/Y')
                    . ' (' . $r->ville_depart . ' → ' . $r->ville_arrivee
                    . ') a été annulée automatiquement car vous ne vous êtes pas présenté(e).',
                'lu'      => false,
            ]);
        }

        $this->info("Terminé : {$totalRetour} retour(s) non effectué(s), {$totalAnnulees} réservation(s) annulée(s).");
    }
}