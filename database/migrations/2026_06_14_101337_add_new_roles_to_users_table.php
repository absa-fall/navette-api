<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'ddl','drh','sg_drh','chauffeur','sg_vr',
            'vice_recteur','admin','enseignant','usager',
            'chef_departement','directeur_ufr','recteur'
        )");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'ddl','drh','sg_drh','chauffeur','sg_vr',
            'vice_recteur','admin','enseignant','usager'
        )");
    }
};