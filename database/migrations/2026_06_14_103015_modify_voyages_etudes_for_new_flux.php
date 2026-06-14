<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modifier la table principale
        Schema::table('voyages_etudes', function (Blueprint $table) {
            // Supprimer les anciennes colonnes
            $table->dropForeign(['enseignant_id']);
            $table->dropColumn([
                'enseignant_id',
                'objet',
                'statut',
                'commentaire_vr',
                'ordre_mission_genere',
                'date_limite_rapport'
            ]);

            // Ajouter les nouvelles colonnes
            $table->text('description')->nullable()->after('destination');
            $table->enum('statut_liste', ['brouillon', 'publiee', 'definitive'])
                  ->default('brouillon')->after('description');
            $table->boolean('arrete_recteur')->default(false)->after('statut_liste');
        });

        // Créer la table des bénéficiaires
        Schema::create('voyage_etude_beneficiaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages_etudes')->onDelete('cascade');
            $table->foreignId('enseignant_id')->constrained('users')->onDelete('cascade');

            // Justificatifs
            $table->string('justificatif_pdf')->nullable();
            $table->enum('statut_justificatif', [
                'en_attente',
                'soumis',
                'valide',
                'incomplet'
            ])->default('en_attente');

            // Liste définitive
            $table->boolean('dans_liste_definitive')->default(false);

            // Autorisation absence
            $table->enum('statut_autorisation', [
                'non_demande',
                'demande_chef_dept',
                'autorisation_sortie_chef',
                'envoye_directeur_ufr',
                'envoye_vr',
                'approuve'
            ])->default('non_demande');

            $table->string('autorisation_pdf')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyage_etude_beneficiaires');
    }
};