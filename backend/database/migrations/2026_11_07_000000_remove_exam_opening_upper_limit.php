<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RemoveExamOpeningUpperLimit extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE examen DROP CONSTRAINT chk_examen_minutos_apertura');
        DB::statement('ALTER TABLE examen ADD CONSTRAINT chk_examen_minutos_apertura CHECK (minutos_apertura >= 0)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE examen DROP CONSTRAINT chk_examen_minutos_apertura');
        DB::statement('ALTER TABLE examen ADD CONSTRAINT chk_examen_minutos_apertura CHECK (minutos_apertura BETWEEN 0 AND 30)');
    }
}
