<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;


return new class extends Migration
{
    public function up(): void
    {
        // S'assurer que la colonne existe (elle existe déjà d'après le modèle)
        if (!Schema::hasColumn('users', 'qr_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('qr_code')->nullable()->unique()->after('matricule');
            });
        }

        // ✅ Générer un QR code pour chaque user qui n'en a pas
        User::whereNull('qr_code')->each(function ($user) {
            $user->update([
                'qr_code' => 'USR-' . strtoupper(Str::random(8))
            ]);
        });
    }

    public function down(): void
    {
        // On ne supprime pas le champ qr_code des users
    }
};