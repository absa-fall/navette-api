<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('bloque_prochain_voyage')->default(false)->after('email');
            $table->date('date_blocage')->nullable()->after('bloque_prochain_voyage');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bloque_prochain_voyage', 'date_blocage']);
        });
    }
};