<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Notification;
use App\Models\VoyageEtudeBeneficiaire;
use Illuminate\Console\Command;

class VerifierDelaisVoyages extends Command
{
    protected $signature = 'voyages:verifier-delais';

    protected $description = 'Verifie les delais de soumission de justificatifs et de rapports de voyage, envoie les rappels et applique les blocages';

    public function handle(): void
    {
        $this->verifierDelaiSoumissionIncomplete();
        $this->verifierDelaiRapport();

        $this->info('Verification des delais terminee.');
    }

    /**
     * Rappel a J-3 pour les dossiers "incomplet" dont la date limite approche.
     */
    private function verifierDelaiSoumissionIncomplete(): void
    {
        $dansTroisJours = now()->addDays(3)->toDateString();

        $beneficiaires = VoyageEtudeBeneficiaire::with('voyage')
            ->where('statut_justificatif', 'incomplet')
            ->where('alerte_delai_envoyee', false)
            ->whereDate('date_limite_soumission', $dansTroisJours)
            ->get();

        foreach ($beneficiaires as $b) {
            try {
                Notification::create([
                    'user_id' => $b->enseignant_id,
                    'type'    => 'rappel_delai_justificatifs',
                    'titre'   => 'Rappel : delai de soumission bientot expire',
                    'message' => 'Il vous reste 3 jours pour renvoyer vos justificatifs corriges pour le voyage a '
                        . ($b->voyage->destination ?? '') . '. Date limite : '
                        . $b->date_limite_soumission->format('d/m/Y') . '.',
                    'lu'      => false,
                ]);
                $b->update(['alerte_delai_envoyee' => true]);
            } catch (\Exception $e) {
                \Log::error('Rappel delai justificatifs: ' . $e->getMessage());
            }
        }

        $this->info($beneficiaires->count() . ' rappel(s) de delai justificatifs envoye(s).');
    }

    /**
     * Rappel a J-3 avant le 31 mars, puis blocage automatique du prochain
     * voyage si la date limite du rapport est depassee sans soumission.
     */
    private function verifierDelaiRapport(): void
    {
        $dansTroisJours = now()->addDays(3)->toDateString();

        // Rappel a J-3
        $aRappeler = VoyageEtudeBeneficiaire::with('voyage')
            ->whereNotNull('date_limite_rapport')
            ->where('alerte_rapport_envoyee', false)
            ->whereDate('date_limite_rapport', $dansTroisJours)
            ->get();

        foreach ($aRappeler as $b) {
            try {
                Notification::create([
                    'user_id' => $b->enseignant_id,
                    'type'    => 'rappel_delai_rapport',
                    'titre'   => 'Rappel : rapport et justificatifs de voyage a soumettre',
                    'message' => 'Il vous reste 3 jours pour soumettre votre rapport et vos justificatifs du voyage a '
                        . ($b->voyage->destination ?? '') . '. Date limite : '
                        . $b->date_limite_rapport->format('d/m/Y')
                        . '. Passe ce delai, vous ne pourrez plus les envoyer et vous ne serez pas eligible au prochain voyage.',
                    'lu'      => false,
                ]);
                $b->update(['alerte_rapport_envoyee' => true]);
            } catch (\Exception $e) {
                \Log::error('Rappel delai rapport: ' . $e->getMessage());
            }
        }

        $this->info($aRappeler->count() . ' rappel(s) de delai rapport envoye(s).');

        // Blocage automatique si la date limite est depassee sans soumission
        $enRetard = VoyageEtudeBeneficiaire::with(['voyage', 'enseignant'])
            ->whereNotNull('date_limite_rapport')
            ->where('date_limite_rapport', '<', now()->toDateString())
            ->whereNotIn('statut_justificatif', ['soumis', 'transmis_vr', 'valide'])
            ->get();

        $bloques = 0;
        foreach ($enRetard as $b) {
            $enseignant = $b->enseignant;
            if (!$enseignant || $enseignant->bloque_prochain_voyage) {
                continue;
            }

            $enseignant->update([
                'bloque_prochain_voyage' => true,
                'date_blocage'           => now(),
            ]);

            try {
                Notification::create([
                    'user_id' => $enseignant->id,
                    'type'    => 'blocage_prochain_voyage',
                    'titre'   => 'Non-eligibilite au prochain voyage',
                    'message' => 'Vous n\'avez pas soumis votre rapport et vos justificatifs pour le voyage a '
                        . ($b->voyage->destination ?? '') . ' avant la date limite du '
                        . $b->date_limite_rapport->format('d/m/Y')
                        . '. Vous n\'etes plus eligible a un prochain voyage d\'etudes. Contactez le Vice-Recteur pour regulariser votre situation.',
                    'lu'      => false,
                ]);
            } catch (\Exception $e) {
                \Log::error('Notification blocage voyage: ' . $e->getMessage());
            }

            $bloques++;
        }

        $this->info($bloques . ' enseignant(s) bloque(s) pour non-soumission.');
    }
}