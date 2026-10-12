<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-001: una cuenta registrada nace con una contraseña temporal que el Administrador entrega y el
 * usuario debe cambiar en su primer ingreso. El indicador vive en la cuenta; las filas existentes
 * quedan en false, así que ninguna cuenta anterior se ve afectada.
 */
class AddDebeCambiarContraseniaToUsuario extends Migration
{
    public function up()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->boolean('debe_cambiar_contrasenia')->default(false);
        });
    }

    public function down()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_contrasenia');
        });
    }
}
