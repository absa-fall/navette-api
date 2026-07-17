<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proces_verbal_historiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proces_verbal_id')->constrained('proces_verbaux')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // modification, finalisation, signature_vr, signature_commission, transmission, signature_recteur
            $table->longText('contenu_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proces_verbal_historiques');
    }
};