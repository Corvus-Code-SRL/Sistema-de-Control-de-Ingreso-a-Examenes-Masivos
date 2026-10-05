<?php

namespace Tests\Feature\Academic;

use App\Models\Group;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-18: registrar un grupo dentro de un par materia-carrera.
 *
 * num_grupo es varchar(5): los valores de prueba nunca superan 5 caracteres, salvo el
 * que comprueba justamente el rechazo de ese límite. SeedsAssistantManagement aporta el
 * catálogo académico y la creación de cuentas Auxiliar.
 */
class StoreGroupTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'NUEVO',
        ], $overrides);
    }

    /** CA 1, 5 y 6: queda asociado al par, con el docente que actúa, y la respuesta lo confirma. */
    public function test_registra_un_grupo_dentro_del_par_materia_carrera(): void
    {
        $response = $this->postJson('/api/grupos', $this->payload());

        $response->assertStatus(201);
        $response->assertJsonPath('mensaje', 'Grupo registrado correctamente.');
        $response->assertJsonPath('data.grupo.num_grupo', 'NUEVO');
        $response->assertJsonPath('data.grupo.es_mio', true);
        $response->assertJsonPath('data.grupo.docente.nombre_completo', 'Ana Rojas');
        $response->assertJsonPath('data.grupo.cantidad_estudiantes', 0);
        $response->assertJsonPath('data.grupo.periodo.id_periodo', $this->periodoActivoId);
        $response->assertJsonPath('data.materia.id_carrera', $this->sistemasId);
        $response->assertJsonPath('data.materia.id_materia', $this->calculoId);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => 'NUEVO',
            'gestion' => '2026',
            'id_periodo' => $this->periodoActivoId,
            'id_usuario_docente' => $this->docenteId,
        ]);
    }

    /**
     * CA 2: el formulario muestra el docente como dato de solo lectura. Lo entrega el backend
     * y sale siempre del docente que actúa, aunque el par no tenga grupos suyos ni de nadie.
     */
    public function test_el_listado_del_par_entrega_el_docente_que_actua(): void
    {
        $this->getJson($this->emptyPairGroupsUrl())
            ->assertOk()
            ->assertJsonPath('meta.docente.nombre_completo', 'Ana Rojas');
    }

    public function test_el_docente_del_listado_cambia_con_el_docente_que_actua(): void
    {
        $this->actAsTeacher($this->otroDocenteId);

        $this->getJson($this->emptyPairGroupsUrl())
            ->assertOk()
            ->assertJsonPath('meta.docente.nombre_completo', 'Luis Vargas');
    }

    private function emptyPairGroupsUrl(): string
    {
        return "/api/carreras/{$this->sistemasId}/materias/{$this->emptyPairSubjectId()}/grupos";
    }

    /** Un par activo sin ningún grupo registrado todavía. */
    private function emptyPairSubjectId(): int
    {
        $this->seedExtraPairs(1);

        return (int) DB::table('materia')->where('codigo', 'REL-1')->value('id_materia');
    }

    /** CA 3 y 8. */
    public function test_rechaza_un_grupo_duplicado_en_el_mismo_par_gestion_y_periodo(): void
    {
        $antes = Group::count();

        $response = $this->postJson('/api/grupos', $this->payload(['num_grupo' => '1']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('num_grupo');
        $this->assertSame($antes, Group::count());
    }

    /**
     * La duplicidad es la quíntupla completa: el mismo número en la misma materia
     * pero en otra carrera es válido. Calculo II existe en Sistemas e Informática.
     */
    public function test_permite_el_mismo_numero_de_grupo_en_otra_carrera(): void
    {
        $this->postJson('/api/grupos', $this->payload([
            'id_carrera' => $this->informaticaId,
            'num_grupo' => '1',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '1',
            'id_periodo' => $this->periodoActivoId,
        ]);
    }

    /** CA 3: el mismo número en otro período no es duplicado. */
    public function test_permite_el_mismo_numero_de_grupo_en_otro_periodo(): void
    {
        $this->postJson('/api/grupos', $this->payload([
            'num_grupo' => '1',
            'id_periodo' => $this->periodoAnteriorId,
        ]))->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
            'num_grupo' => '1',
            'id_periodo' => $this->periodoAnteriorId,
            'gestion' => '2025',
        ]);
    }

    /** CA 4. */
    public function test_rechaza_el_registro_sin_campos_obligatorios(): void
    {
        $this->postJson('/api/grupos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_carrera', 'id_materia', 'num_grupo']);
    }

    public function test_rechaza_un_numero_de_grupo_vacio(): void
    {
        foreach (['', null] as $empty) {
            $this->postJson('/api/grupos', $this->payload(['num_grupo' => $empty]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('num_grupo');
        }
    }

    public function test_rechaza_un_numero_de_grupo_de_mas_de_cinco_caracteres(): void
    {
        $this->postJson('/api/grupos', $this->payload(['num_grupo' => 'EXCESO']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('num_grupo');
    }

    /** CA 7. */
    public function test_rechaza_el_registro_en_un_par_materia_carrera_inexistente(): void
    {
        $this->postJson('/api/grupos', $this->payload([
            'id_carrera' => 9999,
            'id_materia' => 9999,
            'num_grupo' => 'A',
        ]))->assertStatus(404);
    }

    /** CA 7: par ACTIVO, pero materia.estado = INACTIVO. */
    public function test_rechaza_el_registro_cuando_la_materia_esta_inactiva(): void
    {
        $this->postJson('/api/grupos', $this->payload([
            'id_materia' => $this->materiaInactivaId,
            'num_grupo' => 'A',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertDatabaseMissing('grupo', [
            'id_materia' => $this->materiaInactivaId,
            'num_grupo' => 'A',
        ]);
    }

    /** CA 7: materia ACTIVA, pero materia_carrera.estado = INACTIVO en Informática. */
    public function test_rechaza_el_registro_cuando_el_par_esta_inactivo(): void
    {
        $this->postJson('/api/grupos', $this->payload([
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_materia');

        $this->assertDatabaseMissing('grupo', [
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
        ]);
    }

    /**
     * CA 7: la única condición es que el par exista y esté activo. El esquema no tiene
     * vínculo docente-materia, así que no se exige ningún grupo previo: registrar el
     * primero es lo que incorpora la materia a "Mis materias".
     */
    public function test_un_docente_sin_grupos_previos_puede_crear_el_primero_en_un_par_activo(): void
    {
        $this->assertSame(
            0,
            Group::query()
                ->where('id_carrera', $this->sistemasId)
                ->where('id_materia', $this->basesDatosId)
                ->where('id_usuario_docente', $this->docenteId)
                ->count()
        );
        $antes = $this->getJson('/api/materias')->json('meta.total_mias');

        $this->postJson('/api/grupos', $this->payload([
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->basesDatosId,
            'num_grupo' => 'A',
            'id_usuario_docente' => $this->docenteId,
        ]);
        $this->assertSame($antes + 1, $this->getJson('/api/materias')->json('meta.total_mias'));
    }

    /** CA 11. */
    public function test_asigna_el_periodo_activo_por_defecto(): void
    {
        $this->postJson('/api/grupos', $this->payload(['num_grupo' => 'SP']))->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'num_grupo' => 'SP',
            'id_periodo' => $this->periodoActivoId,
        ]);
    }

    /** CA 11: el período activo es visible antes de confirmar y se puede cambiar. */
    public function test_el_listado_de_periodos_indica_cual_es_el_activo(): void
    {
        $this->getJson('/api/periodos')
            ->assertOk()
            ->assertJsonPath('meta.id_periodo_activo', $this->periodoActivoId)
            ->assertJsonCount(2, 'data');
    }

    public function test_permite_verificar_y_elegir_otro_periodo_al_registrar(): void
    {
        $this->postJson('/api/grupos', $this->payload([
            'num_grupo' => 'PA',
            'id_periodo' => $this->periodoAnteriorId,
        ]))->assertStatus(201);

        $this->assertDatabaseHas('grupo', [
            'num_grupo' => 'PA',
            'id_periodo' => $this->periodoAnteriorId,
            'gestion' => '2025',
        ]);
    }

    public function test_rechaza_un_periodo_inexistente(): void
    {
        $this->postJson('/api/grupos', $this->payload(['id_periodo' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_periodo');
    }

    /** Un Auxiliar no registra grupos, aunque el par sea válido. */
    public function test_un_auxiliar_recibe_403_al_registrar_un_grupo(): void
    {
        $this->actAsUserId($this->createAssistant('Beto', '202400010'));
        $antes = Group::count();

        $this->postJson('/api/grupos', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Un auxiliar no puede gestionar grupos.');

        $this->assertSame($antes, Group::count());
    }

    /**
     * Un identificador no numérico, menor que 1 o por encima del integer de PostgreSQL
     * responde 422 y no llega a la base (donde fallaría con 500).
     *
     * @dataProvider invalidIdentifiers
     * @param mixed $value
     */
    public function test_rechaza_identificadores_invalidos(string $field, $value): void
    {
        $antes = Group::count();

        $this->postJson('/api/grupos', $this->payload([$field => $value]))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->assertSame($antes, Group::count());
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public function invalidIdentifiers(): array
    {
        $cases = [];

        foreach (['id_carrera', 'id_materia', 'id_periodo'] as $field) {
            foreach (['no numérico' => 'abc', 'cero' => 0, 'negativo' => -1, 'decimal' => 1.5,
                'sobre el integer' => 2147483648, 'enorme' => 99999999999] as $label => $value) {
                $cases["{$field} {$label}"] = [$field, $value];
            }
        }

        return $cases;
    }
}
