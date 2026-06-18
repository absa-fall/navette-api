<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mettre à jour statut_justificatif — pas de données invalides normalement
        DB::statement("
            ALTER TABLE voyage_etude_beneficiaires
            MODIFY COLUMN statut_justificatif
            ENUM('en_attente', 'soumis', 'transmis_vr', 'valide', 'incomplet')
            NOT NULL DEFAULT 'en_attente'
        ");

        // 2. Mettre à jour les données existantes vers les nouvelles valeurs
        DB::statement("UPDATE voyage_etude_beneficiaires SET statut_autorisation = 'non_demande' WHERE statut_autorisation NOT IN ('non_demande', 'demande_chef_dept', 'envoye_directeur_ufr')");

        // 3. Modifier l'enum statut_autorisation
        DB::statement("
            ALTER TABLE voyage_etude_beneficiaires
            MODIFY COLUMN statut_autorisation
            ENUM('non_demande', 'demande_chef_dept', 'envoye_directeur_ufr', 'envoye_recteur', 'approuve_recteur')
            NOT NULL DEFAULT 'non_demande'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE voyage_etude_beneficiaires
            MODIFY COLUMN statut_justificatif
            ENUM('en_attente', 'soumis', 'valide', 'incomplet')
            NOT NULL DEFAULT 'en_attente'
        ");

        DB::statement("
            ALTER TABLE voyage_etude_beneficiaires
            MODIFY COLUMN statut_autorisation
            ENUM('non_demande', 'demande_chef_dept', 'autorisation_sortie_chef', 'envoye_directeur_ufr', 'envoye_vr', 'approuve')
            NOT NULL DEFAULT 'non_demande'
        ");
    }
};