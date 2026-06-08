<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports_voyage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voyage_id')->constrained('voyages_etudes')->onDelete('cascade');
            $table->foreignId('enseignant_id')->constrained('users')->onDelete('cascade');
            $table->text('contenu');
            $table->string('fichier_pdf')->nullable();
            $table->date('date_depot');
            $table->enum('statut', ['soumis', 'valide', 'rejete'])->default('soumis');
            $table->text('commentaire_vr')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_voyage');
    }
};