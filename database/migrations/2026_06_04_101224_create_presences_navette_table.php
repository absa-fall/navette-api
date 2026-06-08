<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_navettes', function (Blueprint $table) {
            $table->id();
            $table->string('qr_code')->nullable();
            $table->timestamp('heure_enregistrement')->nullable();
            $table->timestamp('heure_montee')->nullable();
            $table->timestamp('heure_descente')->nullable();
            $table->decimal('latitude_montee', 10, 8)->nullable();
            $table->decimal('longitude_montee', 11, 8)->nullable();
            $table->decimal('latitude_descente', 10, 8)->nullable();
            $table->decimal('longitude_descente', 11, 8)->nullable();
            $table->boolean('validee_montee')->default(false);
            $table->boolean('validee_descente')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_navettes');
    }
};