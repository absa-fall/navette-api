<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proces_verbaux', function (Blueprint $table) {
            $table->string('statut', 30)->default('brouillon')->change();
        });
    }

    public function down(): void
    {
        Schema::table('proces_verbaux', function (Blueprint $table) {
            $table->enum('statut', ['brouillon', 'finalise', 'signe'])->default('brouillon')->change();
        });
    }
};