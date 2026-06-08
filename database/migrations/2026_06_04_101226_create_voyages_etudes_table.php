<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyages_etudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vice_recteur_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('destination');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->text('objet');
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->text('commentaire_vr')->nullable();
            $table->boolean('ordre_mission_genere')->default(false);
            $table->date('date_limite_rapport')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyages_etudes');
    }
};