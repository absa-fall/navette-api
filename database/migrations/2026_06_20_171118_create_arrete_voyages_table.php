<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('arrete_voyages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages_etudes')->onDelete('cascade');
            $table->foreignId('recteur_id')->constrained('users');
            $table->string('numero');
            $table->date('date_arrete');
            $table->text('visas');
            $table->decimal('montant_billet', 12, 0);
            $table->decimal('montant_indemnite', 12, 0);
            $table->boolean('signe')->default(false);
            $table->timestamp('date_signature')->nullable();
            $table->timestamp('date_envoi_emails')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('arrete_voyages');
    }
};