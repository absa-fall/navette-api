<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qr_code')->unique()->nullable()->after('matricule');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('ddl','drh','sg_drh','chauffeur','sg_vr','vice_recteur','admin','enseignant','usager')");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('ddl','drh','sg_drh','chauffeur','sg_vr','vice_recteur','admin','enseignant')");
    }
};