<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ordres_mission MODIFY COLUMN statut ENUM(
            'en_attente_drh', 
            'approuve_drh', 
            'rejete', 
            'transmis_chauffeur', 
            'refuse_chauffeur', 
            'execute'
        ) DEFAULT 'en_attente_drh'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ordres_mission MODIFY COLUMN statut ENUM(
            'en_attente_drh', 
            'approuve_drh', 
            'rejete', 
            'transmis_chauffeur', 
            'execute'
        ) DEFAULT 'en_attente_drh'");
    }
};