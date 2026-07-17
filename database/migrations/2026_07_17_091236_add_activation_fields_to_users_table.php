<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('compte_actif')->default(true)->after('is_active');
            $table->string('code_activation')->nullable()->after('compte_actif');
            $table->timestamp('code_activation_expire_at')->nullable()->after('code_activation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['compte_actif', 'code_activation', 'code_activation_expire_at']);
        });
    }
};