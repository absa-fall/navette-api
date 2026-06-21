<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
        $table->boolean('masque_chef_departement')->default(false);
        $table->boolean('masque_directeur_ufr')->default(false);
        $table->boolean('masque_recteur')->default(false);
        $table->boolean('masque_vice_recteur')->default(false);
        $table->boolean('masque_commission')->default(false);
    });
}

public function down(): void
{
    Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
        $table->dropColumn([
            'masque_chef_departement',
            'masque_directeur_ufr',
            'masque_recteur',
            'masque_vice_recteur',
            'masque_commission',
        ]);
    });
}
};
