<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->boolean('masque_ddl')->default(false);
            $table->boolean('masque_drh')->default(false);
            $table->boolean('masque_sg_drh')->default(false);
            $table->boolean('masque_chauffeur')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->dropColumn(['masque_ddl', 'masque_drh', 'masque_sg_drh', 'masque_chauffeur']);
        });
    }
};