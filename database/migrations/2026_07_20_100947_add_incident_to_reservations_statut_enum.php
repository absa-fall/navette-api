<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reservations MODIFY statut ENUM(
            'en_attente_confirmation',
            'confirmee',
            'refusee',
            'en_cours',
            'terminee',
            'annulee',
            'retour_non_effectue',
            'incident'
        ) DEFAULT 'en_attente_confirmation'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reservations MODIFY statut ENUM(
            'en_attente_confirmation',
            'confirmee',
            'refusee',
            'en_cours',
            'terminee',
            'annulee',
            'retour_non_effectue'
        ) DEFAULT 'en_attente_confirmation'");
    }
};