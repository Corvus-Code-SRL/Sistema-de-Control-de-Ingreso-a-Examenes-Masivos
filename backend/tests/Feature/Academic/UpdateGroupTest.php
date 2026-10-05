<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-19: actualizar un grupo propio (número de grupo y período).
 *
 * num_grupo es varchar(5): los valores de prueba nunca superan 5 caracteres, salvo el
 * que comprueba justamente el rechazo de ese límite. SeedsAssistantManagement aporta el
 * catálogo académico y la creación de cuentas Auxiliar.
 */
class UpdateGroupTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    private function url(?int $groupId = null): string
    {
        return '/api/grupos/' . ($groupId ?? $this->grupoPropioId);
    }

    /** Crea un grupo del docente en el par indicado, en el período activo. */
    private function seedOwnGroup(int $careerId, int $subjectId, string $numGrupo): Group
    {
        return Group::create([
            'id_carrera' => $careerId,
            'id_materia' => $subjectId,
            'num_grupo' => $numGrupo,
            'gestion' => '2026',
            'estado' => RecordStatus::ACTIVE,
            'id_usuario_docente' => $this->docenteId,
            'id_periodo' => $this->periodoActivoId,
        ]);
    }

    /** CA 1, 2 y 3: el docente edita su grupo y la respuesta trae los datos vigentes. */
    public function test_actualiza_un_grupo_existente(): void
    {
        $this->enrollStudents($this->grupoPropioId, 4);

        $response = $this->putJson($this->url(), ['num_grupo' => 'REN']);

        $response->assertStatus(200);
        $response->assertJsonPath('mensaje', 'Grupo actualizado correctamente.');
        $response->assertJsonPath('data.grupo.id_grupo', $this->grupoPropioId);
        $response->assertJsonPath('data.grupo.num_grupo', 'REN');
        $response->assertJsonPath('data.grupo.docente.nombre_completo', 'Ana Rojas');
        $response->assertJsonPath('data.grupo.cantidad_estudiantes', 4);
        $response->assertJsonPath('data.grupo.es_mio', true);
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'num_grupo' => 'REN',
        ]);
    }

    /** CA 5: guardar sin cambiar el número no choca consigo mismo. */
    public function test_actualizar_sin_cambios_no_genera_falso_positivo_de_duplicidad(): void
    {
        $this->putJson($this->url(), ['num_grupo' => '1'])->assertStatus(200);
    }

    /** CA 1: solo se opera un grupo propio. */
    public function test_rechaza_la_actualizacion_de_un_grupo_de_otro_docente(): void
    {
        $this->putJson($this->url($this->grupoAjenoId), ['num_grupo' => 'B'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Solo el docente que dicta el grupo puede modificarlo.');

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoAjenoId,
            'num_grupo' => '3',
        ]);
    }

    public function test_la_propiedad_se_resuelve_con_el_docente_que_actua(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        $this->putJson($this->url($this->grupoAjenoId), ['num_grupo' => 'B'])->assertStatus(200);
        $this->putJson($this->url(), ['num_grupo' => 'C'])->assertStatus(403);
    }

    /** Un Auxiliar no actualiza grupos, ni siquiera uno que figura a nombre del docente. */
    public function test_un_auxiliar_recibe_403_al_actualizar_un_grupo(): void
    {
        $this->actAsUserId($this->createAssistant('Beto', '202400010'));

        $this->putJson($this->url(), ['num_grupo' => 'REN'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un docente puede gestionar grupos.');

        $this->assertDatabaseHas('grupo', ['id_grupo' => $this->grupoPropioId, 'num_grupo' => '1']);
    }

    public function test_rechaza_actualizar_un_grupo_inexistente(): void
    {
        $this->putJson('/api/grupos/999999', ['num_grupo' => 'B'])->assertStatus(404);
    }

    /** CA 5: el grupo propio "1" intenta pasar a "2", que ya existe en (Sistemas, Cálculo II). */
    public function test_rechaza_actualizar_a_un_numero_que_ya_usa_otro_grupo_del_par(): void
    {
        $this->putJson($this->url(), ['num_grupo' => '2'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('num_grupo');

        $this->assertDatabaseHas('grupo', ['id_grupo' => $this->grupoPropioId, 'num_grupo' => '1']);
    }

    /** El "3" es del otro docente, pero la quíntupla es la misma. */
    public function test_rechaza_actualizar_a_un_numero_usado_por_un_grupo_de_otro_docente(): void
    {
        $this->putJson($this->url(), ['num_grupo' => '3'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('num_grupo');
    }

    /** La duplicidad incluye la carrera: otra carrera de la misma materia no choca. */
    public function test_permite_actualizar_a_un_numero_que_solo_existe_en_otra_carrera(): void
    {
        $this->seedOwnGroup($this->informaticaId, $this->calculoId, 'X');

        $this->putJson($this->url(), ['num_grupo' => 'X'])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $this->sistemasId,
            'num_grupo' => 'X',
        ]);
    }

    /** CA 4. */
    public function test_rechaza_actualizar_con_el_numero_de_grupo_vacio(): void
    {
        foreach (['', null] as $empty) {
            $this->putJson($this->url(), ['num_grupo' => $empty])
                ->assertStatus(422)
                ->assertJsonValidationErrors('num_grupo');
        }

        $this->putJson($this->url(), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('num_grupo');

        $this->assertDatabaseHas('grupo', ['id_grupo' => $this->grupoPropioId, 'num_grupo' => '1']);
    }

    public function test_rechaza_un_numero_de_grupo_de_mas_de_cinco_caracteres(): void
    {
        $this->putJson($this->url(), ['num_grupo' => 'EXCESO'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('num_grupo');
    }

    /** CA 6: el grupo sigue en la misma materia y con el mismo docente tras guardar. */
    public function test_ignora_intentos_de_cambiar_la_carrera_la_materia_y_el_docente(): void
    {
        $response = $this->putJson($this->url(), [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
            'id_usuario_docente' => $this->otroDocenteId,
            'num_grupo' => 'REN',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.materia.id_carrera', $this->sistemasId);
        $response->assertJsonPath('data.materia.id_materia', $this->calculoId);
        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'id_usuario_docente' => $this->docenteId,
            'num_grupo' => 'REN',
        ]);
    }

    public function test_actualizar_el_periodo_recalcula_la_gestion(): void
    {
        $this->putJson($this->url(), [
            'num_grupo' => '1',
            'id_periodo' => $this->periodoAnteriorId,
        ])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_grupo' => $this->grupoPropioId,
            'id_periodo' => $this->periodoAnteriorId,
            'gestion' => '2025',
        ]);
    }

    public function test_rechaza_un_periodo_inexistente(): void
    {
        $this->putJson($this->url(), ['num_grupo' => '1', 'id_periodo' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');
    }

    /** CA 6: la nómina y los demás grupos no se tocan. */
    public function test_actualizar_un_grupo_no_afecta_a_otros_grupos_ni_estudiantes(): void
    {
        $this->enrollStudents($this->grupoPropioId, 4);

        $estudianteId = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->value('id_estudiante');

        $this->putJson($this->url(), ['num_grupo' => 'REN'])->assertStatus(200);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '2',
            'id_usuario_docente' => $this->docenteId,
        ]);
        $this->assertDatabaseHas('grupo_estudiante', [
            'id_grupo' => $this->grupoPropioId,
            'id_estudiante' => $estudianteId,
        ]);
        $this->assertSame(
            4,
            DB::table('grupo_estudiante')->where('id_grupo', $this->grupoPropioId)->count()
        );
    }

    /**
     * El id de la ruta y el período del cuerpo no numéricos, menores que 1 o por encima del
     * integer de PostgreSQL responden 422 y no llegan a la base (donde fallarían con 500).
     *
     * @dataProvider invalidRouteIdentifiers
     */
    public function test_rechaza_un_id_de_grupo_invalido_en_la_ruta(string $id): void
    {
        $this->putJson("/api/grupos/{$id}", ['num_grupo' => 'B'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_grupo');
    }

    /** @return array<string, array{0: string}> */
    public function invalidRouteIdentifiers(): array
    {
        return [
            'no numérico' => ['abc'],
            'cero' => ['0'],
            'negativo' => ['-1'],
            'decimal' => ['1.5'],
            'sobre el integer' => ['2147483648'],
            'enorme' => ['99999999999'],
        ];
    }

    /**
     * @dataProvider invalidPeriodIdentifiers
     * @param mixed $value
     */
    public function test_rechaza_un_periodo_con_identificador_invalido(string $label, $value): void
    {
        $this->putJson($this->url(), ['num_grupo' => 'B', 'id_periodo' => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');

        $this->assertDatabaseHas('grupo', ['id_grupo' => $this->grupoPropioId, 'num_grupo' => '1']);
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public function invalidPeriodIdentifiers(): array
    {
        return [
            'no numérico' => ['no numérico', 'abc'],
            'cero' => ['cero', 0],
            'negativo' => ['negativo', -1],
            'decimal' => ['decimal', 1.5],
            'sobre el integer' => ['sobre el integer', 2147483648],
            'enorme' => ['enorme', 99999999999],
        ];
    }
}
