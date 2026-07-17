<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proces_verbaux', function (Blueprint $table) {
            // Signature VR
            $table->longText('signature_vr')->nullable()->after('finalise_le');
            $table->foreignId('signe_vr_par')->nullable()->after('signature_vr')->constrained('users')->nullOnDelete();
            $table->timestamp('signe_vr_le')->nullable()->after('signe_vr_par');

            // Signature Commission
            $table->longText('signature_commission')->nullable()->after('signe_vr_le');
            $table->foreignId('signe_commission_par')->nullable()->after('signature_commission')->constrained('users')->nullOnDelete();
            $table->timestamp('signe_commission_le')->nullable()->after('signe_commission_par');

            // Transmission au Recteur (action manuelle du VR)
            $table->foreignId('transmis_par')->nullable()->after('signe_commission_le')->constrained('users')->nullOnDelete();
            $table->timestamp('transmis_le')->nullable()->after('transmis_par');

            // Signature Recteur
            $table->longText('signature_recteur')->nullable()->after('transmis_le');
            $table->foreignId('signe_recteur_par')->nullable()->after('signature_recteur')->constrained('users')->nullOnDelete();
            $table->timestamp('signe_recteur_le')->nullable()->after('signe_recteur_par');
        });
    }

    public function down(): void
    {
        Schema::table('proces_verbaux', function (Blueprint $table) {
            $table->dropForeign(['signe_vr_par']);
            $table->dropForeign(['signe_commission_par']);
            $table->dropForeign(['transmis_par']);
            $table->dropForeign(['signe_recteur_par']);
            $table->dropColumn([
                'signature_vr', 'signe_vr_par', 'signe_vr_le',
                'signature_commission', 'signe_commission_par', 'signe_commission_le',
                'transmis_par', 'transmis_le',
                'signature_recteur', 'signe_recteur_par', 'signe_recteur_le',
            ]);
        });
    }
};