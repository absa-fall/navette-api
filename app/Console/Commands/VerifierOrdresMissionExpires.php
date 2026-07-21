<?php

namespace App\Console\Commands;

use App\Models\OrdreMission;
use App\Models\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class VerifierOrdresMissionExpires extends Command
{
    protected $signature = 'ordres-mission:verifier-expires';

    protected $description = "Rappelle au chauffeur de clôturer sa mission 24h après la date de retour, "
        . "puis clôture automatiquement l'ordre 48h après le rappel si aucune confirmation n'a été reçue.";

    public function handle()
    {
        $maintenant = now();
        $totalRappels = 0;
        $totalClotures = 0;

        // ── Étape 1 : rappel au chauffeur, 24h après la date de retour, si pas déjà envoyé ──
        $aRappeler = OrdreMission::where('statut', 'transmis_chauffeur')
            ->whereNotNull('date_retour')
            ->where('date_retour', '<=', $maintenant->copy()->subDay())
            ->whereNull('rappel_envoye_at')
            ->get();

        foreach ($aRappeler as $ordre) {
            if ($ordre->chauffeur_id) {
                Notification::create([
                    'user_id' => $ordre->chauffeur_id,
                    'type'    => 'ordre_mission_a_cloturer',
                    'titre'   => 'Mission à clôturer',
                    'message' => 'Votre mission vers ' . ($ordre->destination ?? '')
                        . ' devait se terminer le ' . Carbon::parse($ordre->date_retour)->format('d/m/Y')
                        . '. Merci de confirmer si elle a bien été exécutée en cliquant sur "Marquer comme exécuté".',
                    'lu' => false,
                ]);
            }

            $ordre->update(['rappel_envoye_at' => $maintenant]);
            $totalRappels++;
        }

        // ── Étape 2 : clôture automatique 48h après le rappel, si toujours pas confirmé ──
        $aCloturer = OrdreMission::where('statut', 'transmis_chauffeur')
            ->whereNotNull('rappel_envoye_at')
            ->where('rappel_envoye_at', '<=', $maintenant->copy()->subHours(48))
            ->get();

        foreach ($aCloturer as $ordre) {
            $ordre->update([
                'statut' => 'execute',
                'execute_automatiquement' => true,
            ]);

            if ($ordre->ddl_id) {
                Notification::create([
                    'user_id' => $ordre->ddl_id,
                    'type'    => 'ordre_mission_execute_auto',
                    'titre'   => 'Ordre clôturé automatiquement',
                    'message' => "L'ordre de mission vers " . ($ordre->destination ?? '')
                        . " a été marqué comme exécuté automatiquement (aucune confirmation reçue du chauffeur).",
                    'lu' => false,
                ]);
            }

            $totalClotures++;
        }

        $this->info("Terminé : {$totalRappels} rappel(s) envoyé(s), {$totalClotures} ordre(s) clôturé(s) automatiquement.");
    }
}