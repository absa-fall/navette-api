<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            // Colonnes de base qui manquent peut-être
            if (!Schema::hasColumn('ordres_mission', 'heure_depart')) {
                $table->string('heure_depart')->nullable()->after('date_depart');
            }
            if (!Schema::hasColumn('ordres_mission', 'nombre_passagers')) {
                $table->integer('nombre_passagers')->nullable()->after('heure_depart');
            }
            
            // Nouvelles colonnes ordre de mission
            if (!Schema::hasColumn('ordres_mission', 'chauffeur_nom')) {
                $table->string('chauffeur_nom')->nullable()->after('chauffeur_id');
            }
            if (!Schema::hasColumn('ordres_mission', 'chauffeur_prenom')) {
                $table->string('chauffeur_prenom')->nullable()->after('chauffeur_nom');
            }
            if (!Schema::hasColumn('ordres_mission', 'nationalite')) {
                $table->string('nationalite')->default('Sénégalaise')->after('chauffeur_prenom');
            }
            if (!Schema::hasColumn('ordres_mission', 'grade_fonction')) {
                $table->string('grade_fonction')->default('Chauffeur')->after('nationalite');
            }
            if (!Schema::hasColumn('ordres_mission', 'destination')) {
                $table->string('destination')->nullable()->after('grade_fonction');
            }
            if (!Schema::hasColumn('ordres_mission', 'objet_mission')) {
                $table->string('objet_mission')->default('conduit la navette de l\'UAD')->after('destination');
            }
            if (!Schema::hasColumn('ordres_mission', 'moyen_transport')) {
                $table->string('moyen_transport')->nullable()->after('objet_mission');
            }
            if (!Schema::hasColumn('ordres_mission', 'date_retour')) {
                $table->date('date_retour')->nullable()->after('heure_depart');
            }
            if (!Schema::hasColumn('ordres_mission', 'frais_transport')) {
                $table->string('frais_transport')->default('Appui en carburant')->after('date_retour');
            }
            if (!Schema::hasColumn('ordres_mission', 'indemnite_deplacement')) {
                $table->string('indemnite_deplacement')->default('Néant')->after('frais_transport');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $columns = [
                'heure_depart',
                'nombre_passagers',
                'chauffeur_nom',
                'chauffeur_prenom',
                'nationalite',
                'grade_fonction',
                'destination',
                'objet_mission',
                'moyen_transport',
                'date_retour',
                'frais_transport',
                'indemnite_deplacement',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('ordres_mission', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};