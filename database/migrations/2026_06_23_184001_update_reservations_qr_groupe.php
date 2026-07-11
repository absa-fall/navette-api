<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        //  ÉTAPE 1 : Ajouter les nouvelles colonnes
        Schema::table('reservations', function (Blueprint $table) {

            if (!Schema::hasColumn('reservations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('reservations', 'groupe_id')) {
                $table->string('groupe_id')->nullable()->after('user_id')
                    ->comment('Identifiant commun pour lier aller et retour');
            }

            if (!Schema::hasColumn('reservations', 'categorie')) {
                $table->string('categorie')->nullable()->after('prenom');
            }

            if (!Schema::hasColumn('reservations', 'trajet_sens')) {
                $table->enum('trajet_sens', ['aller', 'retour'])->default('aller')->after('type_trajet');
            }

            if (!Schema::hasColumn('reservations', 'motif_refus')) {
                $table->string('motif_refus')->nullable()->after('statut');
            }
        });

        // ✅ ÉTAPE 2 : Supprimer l'index unique sur qr_code AVANT de supprimer la colonne
        if (Schema::hasColumn('reservations', 'qr_code')) {
            try {
                // Supprimer l'index unique s'il existe
                Schema::table('reservations', function (Blueprint $table) {
                    $table->dropUnique(['qr_code']);
                });
            } catch (\Exception $e) {
                // L'index n'existe pas ou déjà supprimé, on continue
            }

            // Supprimer la colonne
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('qr_code');
            });
        }

        // ✅ ÉTAPE 3 : Modifier les enums (séparé pour éviter les conflits)
        \DB::statement("ALTER TABLE reservations MODIFY COLUMN statut ENUM(
            'en_attente_confirmation',
            'confirmee',
            'refusee',
            'en_cours',
            'terminee',
            'annulee'
        ) DEFAULT 'en_attente_confirmation'");

        \DB::statement("ALTER TABLE reservations MODIFY COLUMN type_trajet ENUM(
            'aller', 'retour', 'aller_retour'
        ) DEFAULT 'aller'");
    }

    public function down(): void
    {
        // ✅ Remettre qr_code
        if (!Schema::hasColumn('reservations', 'qr_code')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->string('qr_code')->unique()->nullable()->after('heure_reservation');
            });
        }

        // ✅ Supprimer les colonnes ajoutées
        Schema::table('reservations', function (Blueprint $table) {
            $columns = ['groupe_id', 'trajet_sens', 'motif_refus'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('reservations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};