<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyages_etudes', function (Blueprint $table) {
            $table->boolean('masque_chef_departement')->default(false)->after('masque_vr');
        });
    }

    public function down(): void
    {
        Schema::table('voyages_etudes', function (Blueprint $table) {
            $table->dropColumn('masque_chef_departement');
        });
    }
};