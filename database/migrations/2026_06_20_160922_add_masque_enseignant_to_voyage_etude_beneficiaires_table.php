<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->boolean('masque_enseignant')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->dropColumn('masque_enseignant');
        });
    }
};