<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('rapports_voyage', function (Blueprint $table) {
            $table->string('destination_libre')->nullable()->after('voyage_id');
            $table->date('date_debut_libre')->nullable()->after('destination_libre');
            $table->date('date_fin_libre')->nullable()->after('date_debut_libre');
        });
    }

    public function down()
    {
        Schema::table('rapports_voyage', function (Blueprint $table) {
            $table->dropColumn(['destination_libre', 'date_debut_libre', 'date_fin_libre']);
        });
    }
};