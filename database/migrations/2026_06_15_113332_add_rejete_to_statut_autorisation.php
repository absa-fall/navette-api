<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE voyage_etude_beneficiaires MODIFY COLUMN statut_autorisation ENUM('non_demande','demande_chef_dept','autorisation_sortie_chef','envoye_directeur_ufr','envoye_vr','approuve','rejete') NOT NULL DEFAULT 'non_demande'");
    }

    public function down(): void {}
};