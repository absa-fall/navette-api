<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proces_verbal_historique_masquages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historique_id')->constrained('proces_verbal_historiques')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['historique_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proces_verbal_historique_masquages');
    }
};