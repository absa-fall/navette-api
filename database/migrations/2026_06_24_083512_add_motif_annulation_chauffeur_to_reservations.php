<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // ✅ Motif quand le chauffeur annule une réservation confirmée
            if (!Schema::hasColumn('reservations', 'motif_annulation_chauffeur')) {
                $table->string('motif_annulation_chauffeur')->nullable()->after('motif_refus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('motif_annulation_chauffeur');
        });
    }
};