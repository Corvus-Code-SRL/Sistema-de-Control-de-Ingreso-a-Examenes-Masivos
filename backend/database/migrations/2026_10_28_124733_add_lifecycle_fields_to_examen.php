<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLifecycleFieldsToExamen extends Migration
{
    public function up()
    {
        Schema::table('examen', function (Blueprint $table) {
            $table->timestampTz('inicio_real_en')->nullable();
            $table->timestampTz('fin_real_en')->nullable();

            $table->boolean('inicio_automatico')->default(false);
            $table->boolean('fin_automatico')->default(false);
        });
    }

    public function down()
    {
        Schema::table('examen', function (Blueprint $table) {
            $table->dropColumn([
                'inicio_real_en',
                'fin_real_en',
                'inicio_automatico',
                'fin_automatico',
            ]);
        });
    }
}
