<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->enum('statut_chauffeur', [
                'en_attente',
                'accepte',
                'refuse'
            ])->default('en_attente')->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->dropColumn('statut_chauffeur');
        });
    }
};