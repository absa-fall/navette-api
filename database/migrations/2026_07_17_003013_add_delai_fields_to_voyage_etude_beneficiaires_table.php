<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->date('date_limite_soumission')->nullable()->after('statut_justificatif');
            $table->boolean('alerte_delai_envoyee')->default(false)->after('date_limite_soumission');

            $table->date('date_limite_rapport')->nullable()->after('alerte_delai_envoyee');
            $table->boolean('alerte_rapport_envoyee')->default(false)->after('date_limite_rapport');
        });
    }

    public function down(): void
    {
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->dropColumn([
                'date_limite_soumission',
                'alerte_delai_envoyee',
                'date_limite_rapport',
                'alerte_rapport_envoyee',
            ]);
        });
    }
};