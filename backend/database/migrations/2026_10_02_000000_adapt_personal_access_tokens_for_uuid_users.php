<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adapta personal_access_tokens a SCIEM:
 *
 * - tokenable_id pasa de bigint (morphs) a uuid, porque usuario.id_usuario es uuid. Los tokens
 *   anteriores no podían apuntar a ningún usuario y se descartan.
 * - password_confirmado_en guarda cuándo confirmó la contraseña la sesión (el token) por última
 *   vez, al iniciar sesión y en POST /api/auth/confirmar-password. RNF-06 lo usará para exigir la
 *   contraseña de nuevo al entrar a la Central de Riesgo.
 */
class AdaptPersonalAccessTokensForUuidUsers extends Migration
{
    public function up()
    {
        DB::table('personal_access_tokens')->delete();

        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE uuid USING NULL');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('password_confirmado_en')->nullable();
        });
    }

    public function down()
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('password_confirmado_en');
        });

        DB::table('personal_access_tokens')->delete();

        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE bigint USING NULL');
    }
}
