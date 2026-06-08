<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registres_trajet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordre_mission_id')->constrained('ordres_mission')->onDelete('cascade');
            $table->foreignId('chauffeur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('sg_vr_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('date_trajet');
            $table->time('heure_depart')->nullable();
            $table->time('heure_arrivee')->nullable();
            $table->enum('statut', ['ouvert', 'cloture', 'transmis'])->default('ouvert');
            $table->timestamp('date_cloture')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registres_trajet');
    }
};