<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** URL estable de la fotografía; admite Cloudinary o una ruta servida desde MinIO. */
class AddFotoUrlToEstudiante extends Migration
{
    public function up()
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->text('foto_url')->nullable();
        });
    }

    public function down()
    {
        Schema::table('estudiante', function (Blueprint $table) {
            $table->dropColumn('foto_url');
        });
    }
}
