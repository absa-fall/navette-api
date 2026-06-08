<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordres_mission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ddl_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('drh_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('sg_drh_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('chauffeur_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules')->onDelete('set null');
            $table->date('date_depart');
            $table->enum('trajet', ['dakar_bambey', 'thies_bambey', 'bambey_ngouniane', 'autres']);
            $table->string('trajet_autre')->nullable();
            $table->decimal('montant_trajet', 10, 2)->default(0);
            $table->string('motif');
            $table->enum('statut', [
                'en_attente_drh',
                'approuve_drh',
                'signe_sg',
                'transmis_chauffeur',
                'execute',
                'rejete'
            ])->default('en_attente_drh');
            $table->boolean('signature_sg_drh')->default(false);
            $table->timestamp('date_signature')->nullable();
            $table->text('commentaire_rejet')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordres_mission');
    }
};