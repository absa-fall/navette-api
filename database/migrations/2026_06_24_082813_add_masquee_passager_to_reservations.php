<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // ✅ Masquer la réservation pour le passager sans la supprimer en DB
            // Le SGVR et le chauffeur continuent de la voir
            $table->boolean('masquee_passager')->default(false)->after('notification_envoyee');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('masquee_passager');
        });
    }
};