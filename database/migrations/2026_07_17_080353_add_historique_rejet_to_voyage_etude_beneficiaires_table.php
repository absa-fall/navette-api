<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->boolean('deja_rejete')->default(false)->after('date_limite_soumission');
            $table->text('dernier_motif_rejet')->nullable()->after('deja_rejete');
        });
    }

    public function down(): void
    {
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->dropColumn(['deja_rejete', 'dernier_motif_rejet']);
        });
    }
};