<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyages_etudes', function (Blueprint $table) {
            $table->date('date_publication')->nullable()->after('vice_recteur_id');
            $table->string('motif')->nullable()->after('date_publication');
        });
    }

    public function down(): void
    {
        Schema::table('voyages_etudes', function (Blueprint $table) {
            $table->dropColumn(['date_publication', 'motif']);
        });
    }
};