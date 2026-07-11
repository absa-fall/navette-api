<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autorisations_absence', function (Blueprint $table) {
            $table->dropForeign(['beneficiaire_id']);
        });

        Schema::table('autorisations_absence', function (Blueprint $table) {
            $table->unsignedBigInteger('beneficiaire_id')->nullable()->change();
            $table->foreign('beneficiaire_id')
                  ->references('id')->on('voyage_etude_beneficiaires')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('autorisations_absence', function (Blueprint $table) {
            $table->dropForeign(['beneficiaire_id']);
        });

        Schema::table('autorisations_absence', function (Blueprint $table) {
            $table->unsignedBigInteger('beneficiaire_id')->nullable(false)->change();
            $table->foreign('beneficiaire_id')
                  ->references('id')->on('voyage_etude_beneficiaires')
                  ->onDelete('cascade');
        });
    }
};