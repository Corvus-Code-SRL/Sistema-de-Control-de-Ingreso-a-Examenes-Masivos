<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEntryControlChoiceToExams extends Migration
{
    public function up()
    {
        Schema::table('examen', function (Blueprint $table): void {
            $table->boolean('control_ingreso')->default(true);
        });
    }

    public function down()
    {
        Schema::table('examen', function (Blueprint $table): void {
            $table->dropColumn('control_ingreso');
        });
    }
}
