<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Vehicule;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->string('qr_code')->unique()->nullable()->after('immatriculation');
        });

        // Générer un QR code pour chaque véhicule existant
        foreach (Vehicule::all() as $vehicule) {
            $vehicule->update([
                'qr_code' => 'BUS-' . strtoupper(Str::random(8))
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });
    }
};