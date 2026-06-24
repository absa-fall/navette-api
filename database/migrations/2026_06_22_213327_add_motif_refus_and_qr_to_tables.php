<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -- Table reservations : ajout motif_refus --
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('motif_refus')->nullable()->after('statut');
        });

        // -- Table notifications : ajout qr_code --
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('qr_code')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('motif_refus');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });
    }
};