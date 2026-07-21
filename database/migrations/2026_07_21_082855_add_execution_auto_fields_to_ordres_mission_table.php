<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->boolean('execute_automatiquement')->default(false)->after('statut');
            $table->timestamp('rappel_envoye_at')->nullable()->after('execute_automatiquement');
        });
    }

    public function down(): void
    {
        Schema::table('ordres_mission', function (Blueprint $table) {
            $table->dropColumn(['execute_automatiquement', 'rappel_envoye_at']);
        });
    }
};