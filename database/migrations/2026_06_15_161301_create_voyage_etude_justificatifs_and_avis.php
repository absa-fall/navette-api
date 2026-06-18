<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================
        // Table des justificatifs multiples (1 a 5+)
        // ============================================
        Schema::create('voyage_etude_justificatifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiaire_id')
                  ->constrained('voyage_etude_beneficiaires')
                  ->onDelete('cascade');
            $table->string('fichier_pdf');
            $table->string('nom_original')->nullable();
            $table->timestamps();
        });

        // ============================================
        // Avis individuels VR + Commission sur un dossier
        // ============================================
        Schema::create('voyage_etude_avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiaire_id')
                  ->constrained('voyage_etude_beneficiaires')
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->enum('avis', ['valide', 'rejete'])->default('valide');
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // Un seul avis par (beneficiaire, votant)
            $table->unique(['beneficiaire_id', 'user_id']);
        });

        // ============================================
        // Retirer l'ancienne colonne justificatif_pdf
        // (remplacee par la table voyage_etude_justificatifs)
        // ============================================
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->dropColumn('justificatif_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->string('justificatif_pdf')->nullable();
        });

        Schema::dropIfExists('voyage_etude_avis');
        Schema::dropIfExists('voyage_etude_justificatifs');
    }
};