<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Type de trajet
            if (!Schema::hasColumn('reservations', 'type_trajet')) {
                $table->enum('type_trajet', ['aller', 'retour', 'aller_retour'])->default('aller')->after('ville_arrivee');
            }
            if (!Schema::hasColumn('reservations', 'type_profil')) {
                $table->string('type_profil')->nullable()->after('categorie');
            }
            if (!Schema::hasColumn('reservations', 'montant_retenue')) {
                $table->decimal('montant_retenue', 10, 2)->default(0)->after('type_profil');
            }
            if (!Schema::hasColumn('reservations', 'present')) {
                $table->boolean('present')->default(false)->after('validee_descente');
            }
            if (!Schema::hasColumn('reservations', 'heure_presence')) {
                $table->timestamp('heure_presence')->nullable()->after('present');
            }
            if (!Schema::hasColumn('reservations', 'notification_envoyee')) {
                $table->boolean('notification_envoyee')->default(false)->after('heure_presence');
            }
        });

        // Modifier l'enum statut séparément
        \DB::statement("ALTER TABLE reservations MODIFY COLUMN statut ENUM('en_attente_confirmation','confirmee','refusee','en_cours','terminee','annulee') DEFAULT 'en_attente_confirmation'");
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['type_trajet', 'present', 'heure_presence', 'notification_envoyee']);
        });
    }
};