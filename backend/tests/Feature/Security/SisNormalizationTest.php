<?php

namespace Tests\Feature\Security;

use App\Models\Student;
use App\Models\User;
use App\Services\Academic\Importers\StudentRosterRow;
use App\Services\Academic\Importers\StudentRosterStudentMapper;
use Database\Seeders\ActionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * usuario.cod_sis y estudiante.cod_sis son la misma clave de unión: ambos lados guardan y
 * comparan la forma canónica de App\Support\SisCode.
 */
class SisNormalizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([UserSeeder::class, ActionSeeder::class]);
    }

    public function test_la_cuenta_se_guarda_con_el_sis_canonico(): void
    {
        $this->postJson('/api/usuarios', [
            'cod_sis' => '  202312345 ',
            'nombre' => 'Juan Carlos',
            'apellido_paterno' => 'Perez',
            'correo' => 'juan.perez@est.umss.edu',
        ])->assertStatus(201)->assertJsonPath('data.cod_sis', '202312345');

        $this->assertDatabaseHas('usuario', ['cod_sis' => '202312345']);
    }

    public function test_el_duplicado_se_detecta_aunque_llegue_con_otro_formato(): void
    {
        User::factory()->create(['cod_sis' => 'ADM0001']);

        $this->postJson('/api/usuarios', [
            'cod_sis' => ' adm0001 ',
            'nombre' => 'Valeria',
            'apellido_paterno' => 'Montano',
            'correo' => 'valeria@sciem.test',
        ])->assertStatus(422)->assertJsonValidationErrors('cod_sis');
    }

    public function test_verificar_sis_normaliza_el_codigo_recibido(): void
    {
        $this->getJson('/api/sis/verificar/%20202312345%20')->assertOk();
    }

    public function test_el_modelo_normaliza_en_usuario_y_estudiante(): void
    {
        $user = User::factory()->create(['cod_sis' => ' adm  0001 ']);
        $student = Student::create(array_merge(
            (new StudentRosterStudentMapper())->map(new StudentRosterRow(2, '000123456', 'PEREZ', 'ANA')),
            ['cod_sis' => ' 000123456 ']
        ));

        $this->assertSame('ADM 0001', $user->fresh()->cod_sis);
        $this->assertSame('000123456', $student->fresh()->cod_sis);
    }

    public function test_una_cuenta_y_una_fila_de_nomina_con_el_mismo_sis_se_unen(): void
    {
        $user = User::factory()->create(['cod_sis' => ' 201800451']);
        $row = new StudentRosterRow(2, "201800451\u{00A0}", 'FERRUFINO SOLIZ', 'DANIELA');
        Student::create((new StudentRosterStudentMapper())->map($row));

        $joined = DB::table('usuario')
            ->join('estudiante', 'estudiante.cod_sis', '=', 'usuario.cod_sis')
            ->where('usuario.id_usuario', $user->id_usuario)
            ->count();

        $this->assertSame(1, $joined);
    }

    public function test_no_se_quitan_ceros_a_la_izquierda(): void
    {
        $row = new StudentRosterRow(2, '00123456', 'PEREZ', 'ANA');

        $this->assertSame('00123456', $row->sisCode());
    }
}
