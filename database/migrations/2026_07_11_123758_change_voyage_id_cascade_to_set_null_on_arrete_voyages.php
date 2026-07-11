<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arrete_voyages', function (Blueprint $table) {
            $table->dropForeign(['voyage_id']);
        });

        Schema::table('arrete_voyages', function (Blueprint $table) {
            $table->unsignedBigInteger('voyage_id')->nullable()->change();
            $table->foreign('voyage_id')
                  ->references('id')->on('voyages_etudes')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('arrete_voyages', function (Blueprint $table) {
            $table->dropForeign(['voyage_id']);
        });

        Schema::table('arrete_voyages', function (Blueprint $table) {
            $table->unsignedBigInteger('voyage_id')->nullable(false)->change();
            $table->foreign('voyage_id')
                  ->references('id')->on('voyages_etudes')
                  ->onDelete('cascade');
        });
    }
};