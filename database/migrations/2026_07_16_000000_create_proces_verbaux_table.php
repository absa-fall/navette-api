<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proces_verbaux', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique(); // ex: 2026, un seul PV par année
            $table->longText('contenu')->nullable();          // le corps du PV rédigé par VR + Commission
            $table->enum('statut', ['brouillon', 'finalise'])->default('brouillon');

            // Traçabilité : qui a fait la dernière modif, qui a finalisé
            $table->foreignId('derniere_modif_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalise_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalise_le')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proces_verbaux');
    }
};