<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role
            ENUM(
                'admin','enseignant','chef_departement','directeur_ufr',
                'vice_recteur','recteur','commission','drh','sg_drh',
                'ddl','chauffeur','sg_vr','usager'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE users
            MODIFY COLUMN role
            ENUM(
                'admin','enseignant','chef_departement','directeur_ufr',
                'vice_recteur','recteur','drh','sg_drh',
                'ddl','chauffeur','sg_vr','usager'
            ) NOT NULL
        ");
    }
};