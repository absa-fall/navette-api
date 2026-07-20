<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('rapports_voyage', function (Blueprint $table) {
            $table->foreignId('voyage_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('rapports_voyage', function (Blueprint $table) {
            $table->foreignId('voyage_id')->nullable(false)->change();
        });
    }
};