<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('ufr');
            $table->string('ville_depart');
            $table->string('ville_arrivee');
            $table->date('date_reservation');
            $table->time('heure_reservation');
            $table->string('qr_code')->unique();
            $table->enum('statut', ['en_attente', 'confirmee', 'en_cours', 'terminee', 'annulee'])->default('en_attente');
            $table->foreignId('chauffeur_id')->nullable()->constrained('users');
            $table->boolean('validee_montee')->default(false);
            $table->boolean('validee_descente')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};