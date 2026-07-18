<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('rapports_voyage', function (Blueprint $table) {
            $table->longText('signature_enseignant')->nullable()->after('fichier_pdf');
        });
    }

    public function down()
    {
        Schema::table('rapports_voyage', function (Blueprint $table) {
            $table->dropColumn('signature_enseignant');
        });
    }
};