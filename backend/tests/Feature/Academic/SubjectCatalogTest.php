<?php

namespace Tests\Feature\Academic;

use App\Models\Subject;
use App\Models\SubjectCareer;
use App\Models\User;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class SubjectCatalogTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    public function test_catalogo_vacio_responde_con_mensaje(): void
    {
        $this->createPeriod(2026, 1, true);
        $this->actingAs(User::factory()->create());

        $response = $this->getJson('/api/materias');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.total_mias', 0)
            ->assertJsonPath('mensaje', 'No hay materias disponibles en el catálogo institucional.');
    }

    public function test_diferencia_mis_materias_del_resto_del_catalogo(): void
    {
        $this->seedAcademicCatalog();

        $response = $this->getJson('/api/materias');

        $response->assertOk()
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.total_mias', 1)
            ->assertJsonPath('meta.id_periodo_activo', $this->periodoActivoId)
            ->assertJsonPath('meta.nombre_periodo_activo', '1/2026');

        $propia = $this->findPair($response->json('data'), $this->sistemasId, $this->calculoId);
        $ajena = $this->findPair($response->json('data'), $this->sistemasId, $this->basesDatosId);

        $this->assertTrue($propia['es_mia']);
        $this->assertSame(2, $propia['cantidad_grupos']);

        $this->assertFalse($ajena['es_mia']);
        $this->assertSame(0, $ajena['cantidad_grupos']);
    }

    public function test_la_misma_materia_en_dos_carreras_son_dos_entradas_distintas(): void
    {
        $this->seedAcademicCatalog();

        $data = $this->getJson('/api/materias')->assertOk()->json('data');

        $calculo = array_values(array_filter(
            $data,
            fn (array $pair): bool => $pair['id_materia'] === $this->calculoId
        ));

        $this->assertCount(2, $calculo);
        $this->assertNotSame($calculo[0]['id_carrera'], $calculo[1]['id_carrera']);
        $this->assertSame('Calculo II', $calculo[0]['nombre']);
        $this->assertSame('Calculo II', $calculo[1]['nombre']);

        $carreras = array_column(array_column($calculo, 'carrera'), 'nombre');
        sort($carreras);
        $this->assertSame(['Ingenieria Informatica', 'Ingenieria de Sistemas'], $carreras);
    }

    public function test_los_grupos_de_otro_periodo_no_cuentan_como_materia_propia(): void
    {
        $this->seedAcademicCatalog();

        $data = $this->getJson('/api/materias')->assertOk()->json('data');

        // El docente dicta este par, pero en el periodo anterior.
        $pair = $this->findPair($data, $this->informaticaId, $this->calculoId);

        $this->assertFalse($pair['es_mia']);
        $this->assertSame(0, $pair['cantidad_grupos']);
    }

    public function test_una_materia_sin_oferta_en_el_periodo_activo_no_aparece(): void
    {
        $this->seedAcademicCatalog();

        $subjectId = Subject::create([
            'nombre' => 'Electiva de verano',
            'codigo' => 'ELE-1',
            'estado' => RecordStatus::ACTIVE,
        ])->id_materia;
        SubjectCareer::create([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $subjectId,
            'estado' => RecordStatus::ACTIVE,
        ]);

        // Sin oferta alguna: sigue en el catálogo permanente, pero el docente no la ve.
        $data = $this->getJson('/api/materias')->assertOk()->assertJsonPath('meta.total', 5)->json('data');
        $this->assertFalse($this->hasPair($data, $this->sistemasId, $subjectId));

        // Ofertada solo en el período anterior: tampoco aparece en el activo.
        $this->offerPair($this->sistemasId, $subjectId, $this->periodoAnteriorId);
        $data = $this->getJson('/api/materias')->assertOk()->assertJsonPath('meta.total', 5)->json('data');
        $this->assertFalse($this->hasPair($data, $this->sistemasId, $subjectId));

        // Ofertada en el activo: aparece.
        $this->offerPair($this->sistemasId, $subjectId, $this->periodoActivoId);
        $data = $this->getJson('/api/materias')->assertOk()->assertJsonPath('meta.total', 6)->json('data');
        $this->assertTrue($this->hasPair($data, $this->sistemasId, $subjectId));
    }

    public function test_una_oferta_inactiva_no_cuenta(): void
    {
        $this->seedAcademicCatalog();

        DB::table('oferta_academica')
            ->where('id_carrera', $this->sistemasId)
            ->where('id_materia', $this->materiaInactivaId)
            ->where('id_gestion_periodo', $this->periodoActivoId)
            ->update(['estado' => RecordStatus::INACTIVE]);

        $data = $this->getJson('/api/materias')->assertOk()->assertJsonPath('meta.total', 4)->json('data');

        $this->assertFalse($this->hasPair($data, $this->sistemasId, $this->materiaInactivaId));
    }

    public function test_un_docente_sin_grupos_igual_puede_consultar_el_catalogo(): void
    {
        $this->seedAcademicCatalog();
        $this->actingAs(User::factory()->create(['id_usuario' => '33333333-3333-4333-8333-333333333333']));

        $response = $this->getJson('/api/materias');

        $response->assertOk()
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.total_mias', 0);

        $this->assertArrayNotHasKey('mensaje', $response->json());

        foreach ($response->json('data') as $pair) {
            $this->assertFalse($pair['es_mia']);
        }
    }

    public function test_marca_como_inactivo_el_par_cuya_materia_o_relacion_esta_inactiva(): void
    {
        $this->seedAcademicCatalog();

        $data = $this->getJson('/api/materias')->assertOk()->json('data');

        $materiaInactiva = $this->findPair($data, $this->sistemasId, $this->materiaInactivaId);
        $parInactivo = $this->findPair($data, $this->informaticaId, $this->basesDatosId);
        $activa = $this->findPair($data, $this->sistemasId, $this->calculoId);

        $this->assertFalse($materiaInactiva['activa']);
        $this->assertFalse($parInactivo['activa']);
        $this->assertTrue($activa['activa']);
    }

    public function test_cada_entrada_expone_la_carrera_para_distinguir_duplicados(): void
    {
        $this->seedAcademicCatalog();

        $this->getJson('/api/materias')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_materia',
                        'id_carrera',
                        'nombre',
                        'codigo',
                        'nivel_semestre',
                        'obligatoria',
                        'activa',
                        'es_mia',
                        'cantidad_grupos',
                        'carrera' => ['id_carrera', 'nombre', 'codigo', 'id_facultad'],
                    ],
                ],
                'meta' => ['total', 'total_mias', 'id_periodo_activo', 'nombre_periodo_activo'],
            ]);
    }

    public function test_el_catalogo_no_lanza_una_consulta_por_cada_materia(): void
    {
        $this->seedAcademicCatalog();

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/materias')->assertOk();
        $consultasIniciales = count(DB::getQueryLog());

        $this->seedExtraPairs(20);

        DB::flushQueryLog();
        $this->getJson('/api/materias')->assertOk()->assertJsonPath('meta.total', 25);
        $consultasConMasMaterias = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame($consultasIniciales, $consultasConMasMaterias);
    }

    private function hasPair(array $data, int $careerId, int $subjectId): bool
    {
        foreach ($data as $pair) {
            if ($pair['id_carrera'] === $careerId && $pair['id_materia'] === $subjectId) {
                return true;
            }
        }

        return false;
    }

    private function findPair(array $data, int $careerId, int $subjectId): array
    {
        foreach ($data as $pair) {
            if ($pair['id_carrera'] === $careerId && $pair['id_materia'] === $subjectId) {
                return $pair;
            }
        }

        $this->fail("No se encontró el par carrera {$careerId} / materia {$subjectId}.");
    }
}
