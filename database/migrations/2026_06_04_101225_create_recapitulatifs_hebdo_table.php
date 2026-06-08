<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recapitulatifs_hebdo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sg_vr_id')->constrained('users')->onDelete('cascade');
            $table->date('semaine_debut');
            $table->date('semaine_fin');
            $table->decimal('montant_total', 10, 2)->default(0);
            $table->enum('statut', ['brouillon', 'valide'])->default('brouillon');
            $table->timestamp('date_generation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recapitulatifs_hebdo');
    }
};