<?php

namespace Tests\Feature\RiskCenter\Academic\Rectification;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-062: GET /api/rectificacion-riesgo/estudiantes?q=, la búsqueda por código SIS, carnet o nombre.
 */
class SearchRectifiableStudentsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use SeedsSecurityAccounts;

    private const URL = '/api/rectificacion-riesgo/estudiantes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
    }

    private function search(string $term): \Illuminate\Testing\TestResponse
    {
        return $this->getJson(self::URL . '?q=' . urlencode($term));
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson(self::URL . '?q=202411111')->assertUnauthorized();
    }

    /** @return array<string, array{0: string}> */
    public function deniedRoles(): array
    {
        return ['docente' => [Role::DOCENTE], 'auxiliar' => [Role::AUXILIAR], 'invitado' => [Role::INVITADO]];
    }

    /** @dataProvider deniedRoles */
    public function test_quien_no_es_administrador_recibe_403(string $role): void
    {
        $this->actAs($this->createAccount($role));

        $this->search('202411111')->assertForbidden();
    }

    public function test_una_cuenta_inactiva_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::ADMINISTRADOR, User::ESTADO_INACTIVO));

        $this->search('202411111')->assertForbidden();
    }

    public function test_encuentra_por_codigo_sis_exacto(): void
    {
        $id = $this->insertStudent('Ana', 'Quispe', 'Mamani', '202411111');
        $this->insertStudent('Luis', 'Quispe', null, '202411112');

        $this->search('202411111')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_estudiante', $id)
            ->assertJsonPath('data.0.nombre_completo', 'Ana Quispe Mamani')
            ->assertJsonPath('data.0.cod_sis', '202411111')
            ->assertJsonPath('meta.hay_mas', false);
    }

    public function test_el_codigo_sis_parcial_no_coincide(): void
    {
        $this->insertStudent('Ana', 'Quispe', null, '202411111');

        $this->search('2024111')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_encuentra_por_carnet_de_identidad_exacto(): void
    {
        $id = $this->insertStudent('Rosa', 'Tapia', null, '202422222');
        DB::table('estudiante')->where('id_estudiante', $id)->update(['ci' => '7654321']);

        $this->search('7654321')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_estudiante', $id)
            ->assertJsonPath('data.0.ci', '7654321');
    }

    public function test_un_dato_numerico_sin_coincidencia_exacta_no_busca_por_nombre(): void
    {
        $this->insertStudent('Rosa', 'Tapia', null, '202433333');

        $this->search('20243')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_nombre_no_distingue_mayusculas_ni_tildes_y_admite_palabras_parciales(): void
    {
        $withAccent = $this->insertStudent('José Luis', 'Pérez', 'Rojas', '202444441');
        $without = $this->insertStudent('María', 'Perez', 'Soliz', '202444442');
        $other = $this->insertStudent('Carlos', 'Vargas', null, '202444443');

        foreach (['perez', 'PÉREZ', 'per', 'Pérez'] as $term) {
            $ids = $this->search($term)->assertOk()->json('data.*.id_estudiante');

            $this->assertContains($withAccent, $ids, $term);
            $this->assertContains($without, $ids, $term);
            $this->assertNotContains($other, $ids, $term);
        }

        $this->search('jose perez')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_estudiante', $withAccent);

        $this->search('mari per')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_estudiante', $without);
    }

    public function test_el_nombre_exige_al_menos_tres_caracteres(): void
    {
        $this->insertStudent('Ana', 'Quispe', null, '202455551');

        $this->search('an')
            ->assertStatus(422)
            ->assertJsonValidationErrors('q');
        $this->search('ana')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_sin_dato_de_busqueda_responde_422(): void
    {
        $this->getJson(self::URL)->assertStatus(422)->assertJsonValidationErrors('q');
        $this->search('   ')->assertStatus(422)->assertJsonValidationErrors('q');
    }

    public function test_sin_coincidencias_devuelve_la_lista_vacia(): void
    {
        $this->search('Zzxqwerty')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.hay_mas', false);
    }

    public function test_los_comodines_se_buscan_como_texto(): void
    {
        $this->insertStudent('Ana', 'Quispe', null, '202466661');

        $this->search('%%%')->assertOk()->assertJsonCount(0, 'data');
        $this->search('a_a')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_acota_la_lista_e_indica_que_hay_mas_coincidencias(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->insertStudent('Estudiante' . sprintf('%02d', $i), 'Zzlimite', null, '2024777' . sprintf('%02d', $i));
        }

        $response = $this->search('zzlimite')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.limite', 10)
            ->assertJsonPath('meta.hay_mas', true);

        $this->assertSame('Estudiante01', explode(' ', $response->json('data.0.nombre_completo'))[0]);
    }

    public function test_encuentra_estudiantes_sin_registros_en_la_central(): void
    {
        $id = $this->insertStudent('Sin', 'Registros', null, '202488881');

        $this->search('202488881')->assertOk()->assertJsonPath('data.0.id_estudiante', $id);
    }
}
