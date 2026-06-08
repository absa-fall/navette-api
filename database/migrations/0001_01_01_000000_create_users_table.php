<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('tel')->nullable();
            $table->string('matricule')->unique()->nullable();
            $table->enum('type_profil', ['PER', 'PATS', 'ATR'])->nullable();
            $table->enum('statut', ['permanent', 'non_permanent', 'contractuel', 'vacataire'])->nullable();
            $table->enum('ufr', ['SATIC', 'SDD', 'ECOMIJ', 'ISFAR'])->nullable();
            $table->enum('role', ['ddl', 'drh', 'sg_drh', 'chauffeur', 'sg_vr', 'vice_recteur', 'admin', 'enseignant' ]);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};