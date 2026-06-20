<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autorisations_absence', function (Blueprint $table) {
            $table->id();

            // Lien vers le bénéficiaire du voyage d'études
            $table->foreignId('beneficiaire_id')->constrained('voyage_etude_beneficiaires')->onDelete('cascade');
            $table->foreignId('enseignant_id')->constrained('users');

            // Données du formulaire (PAR / FONCTION / UFR / MOTIF / LIEU / PERIODE / ORGANISME)
            $table->string('numero')->nullable();
            $table->date('date_presentation');
            $table->string('nom_demandeur');
            $table->string('fonction')->default('Enseignant-Chercheur');
            $table->string('ufr_departement');
            $table->string('motif_mission');
            $table->string('lieu_deplacement');
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->text('organisme_charge');

            // Signature enseignant
            $table->boolean('signature_enseignant')->default(false);

            // Avis Chef de Département
            $table->foreignId('chef_departement_id')->nullable()->constrained('users');
            $table->enum('avis_chef_departement', ['favorable', 'defavorable'])->nullable();
            $table->text('commentaire_chef_departement')->nullable();
            $table->timestamp('date_avis_chef_departement')->nullable();

            // Avis Directeur UFR
            $table->foreignId('directeur_ufr_id')->nullable()->constrained('users');
            $table->enum('avis_directeur_ufr', ['favorable', 'defavorable'])->nullable();
            $table->text('commentaire_directeur_ufr')->nullable();
            $table->timestamp('date_avis_directeur_ufr')->nullable();

            // Signature Recteur
            $table->foreignId('recteur_id')->nullable()->constrained('users');
            $table->timestamp('date_signature_recteur')->nullable();

            // Transmission VR -> Enseignant
            $table->foreignId('vr_id')->nullable()->constrained('users');
            $table->timestamp('date_transmission_vr')->nullable();

            // Statut global du circuit
            $table->enum('statut', [
                'soumise',                  // créée par l'enseignant, envoyée au chef dept
                'avis_chef_departement',    // chef dept a donné son avis, envoyée au directeur UFR
                'avis_directeur_ufr',       // directeur UFR a donné son avis, envoyée au recteur
                'signee_recteur',           // recteur a signé, envoyée au VR
                'transmise',                // VR a transmis à l'enseignant (terminé)
                'rejetee',                  // rejetée à une étape quelconque
            ])->default('soumise');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorisations_absence');
    }
};