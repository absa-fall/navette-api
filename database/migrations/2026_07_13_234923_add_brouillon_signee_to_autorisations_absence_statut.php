<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // On ajoute 'brouillon' et 'signee' a la liste des valeurs autorisees,
        // et on change la valeur par defaut de 'soumise' a 'brouillon'
        // (une demande commence toujours en brouillon avant d'etre signee puis soumise).
        DB::statement("ALTER TABLE autorisations_absence MODIFY COLUMN statut ENUM(
            'brouillon',
            'signee',
            'soumise',
            'avis_chef_departement',
            'avis_directeur_ufr',
            'signee_recteur',
            'transmise',
            'rejetee'
        ) NOT NULL DEFAULT 'brouillon'");
    }

    public function down(): void
    {
        
        DB::statement("ALTER TABLE autorisations_absence MODIFY COLUMN statut ENUM(
            'soumise',
            'avis_chef_departement',
            'avis_directeur_ufr',
            'signee_recteur',
            'transmise',
            'rejetee'
        ) NOT NULL DEFAULT 'soumise'");
    }
};