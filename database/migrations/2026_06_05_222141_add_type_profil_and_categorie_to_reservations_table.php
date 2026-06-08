<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('categorie')->nullable()->after('prenom'); // PER, PATS, ATR
            $table->string('type_profil')->nullable()->after('categorie'); // permanent, non_permanent, contractuel, vacataire
            $table->decimal('montant_retenue', 10, 2)->default(0)->after('ville_arrivee');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['categorie', 'type_profil', 'montant_retenue']);
        });
    }
};