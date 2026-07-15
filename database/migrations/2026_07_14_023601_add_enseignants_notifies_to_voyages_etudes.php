<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyages_etudes', function (Blueprint $table) {
            $table->boolean('enseignants_notifies')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('voyages_etudes', function (Blueprint $table) {
            $table->dropColumn('enseignants_notifies');
        });
    }
};