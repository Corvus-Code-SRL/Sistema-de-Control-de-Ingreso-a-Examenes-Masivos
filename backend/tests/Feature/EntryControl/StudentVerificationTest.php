<?php

namespace Tests\Feature\EntryControl;

use App\Jobs\OpenEntryControlJob;
use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class StudentVerificationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private Exam $exam;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        $this->exam = $this->createExam([], [$this->aulaId, $this->otraAulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);

        $this->student = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->select('estudiante.*')
            ->firstOrFail();

        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
        $this->actingAs(User::query()->findOrFail($this->docenteId));
    }

    private function rebuildSnapshot(): void
    {
        app(RoomAssignmentService::class)->prepare($this->exam->id_examen);
    }

    private function url(string $action): string
    {
        return "/api/control-ingreso/examenes/{$this->exam->id_examen}/{$action}";
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'cod_sis' => $this->student->cod_sis,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
        ], $overrides);
    }

    public function test_verifica_sin_crear_ingreso_y_busca_por_nombre(): void
    {
        $this->getJson($this->url('buscar') . '?nombre=Estudiante')
            ->assertOk()
            ->assertJsonPath('data.0.id_estudiante', $this->student->id_estudiante);

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.antecedentes.total', 0)
            ->assertJsonPath('data.antecedentes.latest', null);

        $this->assertSame(0, DB::table('examen_estudiante')->count());
        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function test_sis_de_auxiliar_del_examen_muestra_su_ficha_sin_registrar_intento_ni_ingreso(): void
    {
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->otraAulaId,
        ]);
        $assistant = User::query()->findOrFail($this->otroDocenteId);
        $this->rebuildSnapshot();
        $this->getJson($this->url('buscar') . '?sis=' . substr($assistant->cod_sis, 0, 4))
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $before = $this->getJson($this->url('estado'))->assertOk()->json('data');

        $this->postJson($this->url('verificar'), [
            'cod_sis' => $assistant->cod_sis,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'AUXILIAR_EXAMEN')
            ->assertJsonPath('data.autorizado', false)
            ->assertJsonPath('data.estudiante', null)
            ->assertJsonPath('data.auxiliar.cod_sis', $assistant->cod_sis)
            ->assertJsonPath('data.auxiliar.nombre_completo', $assistant->nombre_completo)
            ->assertJsonPath('data.auxiliar.ambiente_asignado.nro_aula', '692B');

        $after = $this->getJson($this->url('estado'))->assertOk()->json('data');
        $this->assertSame($before['version'], $after['version']);
        $this->assertSame($before['ingresados'], $after['ingresados']);
        $this->assertSame($before['pendientes'], $after['pendientes']);
        $this->assertSame(0, DB::table('examen_estudiante')->count());
        $this->assertSame(0, DB::table('intento_ingreso')->count());
        $this->assertSame(0, DB::table('log')->where('tabla_afectada', 'intento_ingreso')->count());
    }

    public function test_sis_de_auxiliar_de_otro_examen_no_revela_su_ficha(): void
    {
        $otherExam = $this->createExam([], [$this->aulaId]);
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $otherExam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->aulaId,
        ]);
        $assistant = User::query()->findOrFail($this->otroDocenteId);

        $this->postJson($this->url('verificar'), [
            'cod_sis' => $assistant->cod_sis,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_ENCONTRADO')
            ->assertJsonPath('data.auxiliar', null);
    }

    public function test_estudiante_tiene_prioridad_si_comparte_sis_con_auxiliar(): void
    {
        $assistant = User::query()->findOrFail($this->otroDocenteId);
        $assistant->update(['cod_sis' => $this->student->cod_sis]);
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->otraAulaId,
        ]);

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.id_estudiante', $this->student->id_estudiante)
            ->assertJsonPath('data.auxiliar', null);
    }

    public function test_busca_nombres_sin_distinguir_tildes_ni_mayusculas(): void
    {
        $this->student->update([
            'nombre' => 'José',
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'Ñúñez',
        ]);
        $this->rebuildSnapshot();

        foreach (['jose perez', 'JOSÉ PÉREZ', 'nunez', 'ÑÚÑEZ'] as $name) {
            $this->getJson($this->url('buscar') . '?' . http_build_query(['nombre' => $name]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id_estudiante', $this->student->id_estudiante)
                ->assertJsonPath('data.0.nombre_completo', 'José Pérez Ñúñez');
        }
    }

    public function test_busca_por_nombre_con_porcentaje_guion_bajo_y_barra_invertida_literales(): void
    {
        $this->student->update(['nombre' => 'José%_\\']);
        $this->rebuildSnapshot();

        $this->getJson($this->url('buscar') . '?' . http_build_query(['nombre' => '%_\\']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_estudiante', $this->student->id_estudiante);

        $this->getJson($this->url('buscar') . '?' . http_build_query(['nombre' => '%_otro']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sugerencias_solo_incluyen_la_nomina_y_sis_manual_muestra_al_ajeno(): void
    {
        $outsideId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '199912543',
            'ci' => '0012345',
            'nombre' => 'María',
            'apellido_paterno' => 'Fuera',
            'apellido_materno' => 'Nómina',
            'foto_url' => 'https://example.test/foto-ajena.jpg',
            'estado' => 'ACTIVO',
        ], 'id_estudiante');
        $this->rebuildSnapshot();

        $cache = Cache::store(config('sciem.entry_control_cache_store', 'redis'));
        $token = $cache->get("entry-control:{$this->exam->id_examen}:active");
        $prefix = "entry-control:{$this->exam->id_examen}:{$token}";
        $this->assertFalse($cache->has("{$prefix}:student:{$outsideId}"));
        $this->assertFalse($cache->has($prefix . ':sis:' . sha1('199912543')));
        $this->assertFalse($cache->has($prefix . ':ci:' . sha1('0012345')));

        foreach (['sis' => '1999', 'ci' => '0012', 'nombre' => 'María Fuera'] as $field => $prefix) {
            $this->getJson($this->url('buscar') . '?' . http_build_query([$field => $prefix]))
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }

        foreach (['sis' => substr($this->student->cod_sis, 0, 4), 'ci' => substr($this->student->ci, 0, 4)] as $field => $prefix) {
            $matches = $this->getJson($this->url('buscar') . '?' . http_build_query([$field => $prefix]))
                ->assertOk()
                ->json('data');
            $this->assertContains($this->student->id_estudiante, array_column($matches, 'id_estudiante'));
            foreach ($matches as $match) {
                if ($field === 'ci') {
                    $this->assertStringStartsWith($prefix, $match['ci']);
                    if ($match['id_estudiante'] === $this->student->id_estudiante) {
                        $this->assertSame($this->student->ci, $match['ci']);
                    }
                } else {
                    $this->assertArrayNotHasKey('ci', $match);
                }
            }
        }

        $this->postJson($this->url('verificar'), $this->input(['cod_sis' => '199912543', 'ci' => '9999999']))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_PERTENECE')
            ->assertJsonPath('data.motivo', 'No pertenece a los grupos de este examen')
            ->assertJsonPath('data.estudiante.id_estudiante', $outsideId)
            ->assertJsonPath('data.estudiante.nombre_completo', 'María Fuera Nómina')
            ->assertJsonPath('data.estudiante.ci', '0012345')
            ->assertJsonPath('data.estudiante.foto_url', 'https://example.test/foto-ajena.jpg');

        $this->assertFalse($cache->has("{$prefix}:student:{$outsideId}"));
        $this->assertFalse($cache->has($prefix . ':sis:' . sha1('199912543')));
        $this->assertFalse($cache->has($prefix . ':ci:' . sha1('0012345')));
    }

    public function test_sugerencias_excluyen_a_estudiante_de_un_grupo_ajeno(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);
        $outside = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoAjenoId)
            ->select('estudiante.*')
            ->firstOrFail();
        $this->rebuildSnapshot();

        foreach (['sis' => $outside->cod_sis, 'ci' => $outside->ci] as $field => $prefix) {
            $matches = $this->getJson($this->url('buscar') . '?' . http_build_query([$field => $prefix]))
                ->assertOk()
                ->json('data');
            $this->assertNotContains($outside->id_estudiante, array_column($matches, 'id_estudiante'));
        }

        $this->postJson($this->url('verificar'), $this->input(['cod_sis' => $outside->cod_sis, 'ci' => $outside->ci]))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_PERTENECE')
            ->assertJsonPath('data.estudiante.id_estudiante', $outside->id_estudiante);
    }

    public function test_sugerencias_no_duplican_alumno_inscrito_en_dos_grupos_del_examen(): void
    {
        DB::table('grupo_examen')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_grupo' => $this->grupoAjenoId,
        ]);
        DB::table('grupo_estudiante')->insert([
            'id_grupo' => $this->grupoAjenoId,
            'id_estudiante' => $this->student->id_estudiante,
            'fecha_inscripcion' => '2026-02-15',
            'estado' => 'ACTIVO',
        ]);
        $this->rebuildSnapshot();

        foreach (['sis' => $this->student->cod_sis, 'ci' => $this->student->ci] as $field => $value) {
            $this->getJson($this->url('buscar') . '?' . http_build_query([$field => $value]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id_estudiante', $this->student->id_estudiante);
        }
    }

    public function test_busqueda_por_identificador_exige_un_solo_prefijo_numerico(): void
    {
        foreach ([
            ['sis' => '12'],
            ['sis' => '123x'],
            ['ci' => '123'],
            ['ci' => '123a'],
            ['sis' => '123', 'ci' => '1234'],
        ] as $query) {
            $this->getJson($this->url('buscar') . '?' . http_build_query($query))
                ->assertUnprocessable();
        }

        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->getJson($this->url('buscar') . '?sis=200')->assertForbidden();
        $this->getJson($this->url('buscar') . '?ci=1234')->assertForbidden();
    }

    public function test_busqueda_por_sis_y_ci_limita_los_resultados_a_cinco(): void
    {
        $this->enrollStudents($this->grupoPropioId, 9);
        $this->rebuildSnapshot();

        foreach (['sis' => $this->student->cod_sis, 'ci' => $this->student->ci] as $field => $value) {
            $prefix = substr($value, 0, 4);
            $this->getJson($this->url('buscar') . '?' . http_build_query([$field => $prefix]))
                ->assertOk()
                ->assertJsonCount(5, 'data');
        }
    }

    public function test_busqueda_por_nombre_limita_los_resultados_a_cinco(): void
    {
        $this->enrollStudents($this->grupoPropioId, 9);
        $this->rebuildSnapshot();

        $this->getJson($this->url('buscar') . '?nombre=Estudiante')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_verificacion_por_sis_devuelve_la_foto_opcional_del_estudiante(): void
    {
        $photoUrl = 'https://res.cloudinary.com/sciem/image/upload/estudiantes/9501.webp';
        $this->student->update(['foto_url' => $photoUrl]);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.cod_sis', $this->student->cod_sis)
            ->assertJsonPath('data.estudiante.foto_url', $photoUrl);
    }

    public function test_no_verifica_si_la_duracion_del_examen_ya_termino(): void
    {
        $timezone = config('sciem.zona_horaria');
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', $timezone));
        try {
            $this->exam->update([
                'fecha' => '2026-10-01',
                'hora_inicio' => '11:00',
                'hora_fin' => '12:30',
                'duracion' => 90,
            ]);
            $this->rebuildSnapshot();

            Carbon::setTestNow(Carbon::parse('2026-10-01 12:30:00', $timezone));
            $this->postJson($this->url('verificar'), $this->input())
                ->assertStatus(409)
                ->assertJsonPath('message', 'El tiempo de control de ingreso del examen ya terminó.');

            $this->assertSame(0, DB::table('examen_estudiante')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_verificacion_autorizada_no_consulta_postgresql(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        foreach ([
            $this->input(),
            ['ci' => $this->student->ci, 'id_ambiente' => $this->aulaId],
        ] as $input) {
            $this->postJson($this->url('verificar'), $input)
                ->assertOk()
                ->assertJsonPath('data.veredicto', 'AUTORIZADO');
        }
        $this->getJson($this->url('buscar') . '?ci=' . $this->student->ci)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame([], $queries);
    }

    public function test_snapshot_anterior_sin_antecedentes_no_rompe_la_verificacion(): void
    {
        $cache = Cache::store(config('sciem.entry_control_cache_store', 'redis'));
        $token = $cache->get("entry-control:{$this->exam->id_examen}:active");
        $key = "entry-control:{$this->exam->id_examen}:{$token}:student:{$this->student->id_estudiante}";
        $record = $cache->get($key);
        unset($record['antecedentes']);
        $cache->put($key, $record, now()->addHour());

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.antecedentes.total', 0)
            ->assertJsonPath('data.antecedentes.latest', null);

        $record['antecedentes'] = [
            'cantidad' => 1,
            'detalles' => [['tipo' => 'Suplantacion', 'fecha' => '2026-10-13']],
        ];
        $cache->put($key, $record, now()->addHour());
        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.antecedentes.total', 1)
            ->assertJsonPath('data.antecedentes.latest.tipo_falta', 'Suplantacion');
    }

    public function test_ci_diferente_bloquea_la_verificacion_y_registra_el_intento(): void
    {
        $originalCi = $this->student->ci;
        $before = $this->getJson($this->url('estado'))->assertOk()->json('data');

        $this->postJson($this->url('verificar'), $this->input(['ci' => '999999999']))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'CI_NO_COINCIDE')
            ->assertJsonPath('data.motivo', 'El CI no coincide con el registrado')
            ->assertJsonPath('data.autorizado', false)
            ->assertJsonPath('data.estudiante.ci', $originalCi);

        $this->assertDatabaseHas('intento_ingreso', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'ci_presentado' => '999999999',
            'motivo' => 'CI_NO_COINCIDE',
        ]);
        $this->assertSame('CREAR', DB::table('log')
            ->join('accion', 'accion.id_accion', '=', 'log.id_accion')
            ->where('log.tabla_afectada', 'intento_ingreso')
            ->where('log.id_usuario', $this->docenteId)
            ->value('accion.operacion'));
        $this->assertSame($originalCi, $this->student->fresh()->ci);
        $this->assertSame(0, DB::table('examen_estudiante')->count());
        $after = $this->getJson($this->url('estado'))->assertOk()->json('data');
        $this->assertSame($before['version'], $after['version']);
        $this->assertSame($before['ingresados'], $after['ingresados']);
        $this->assertSame($before['pendientes'], $after['pendientes']);
    }

    public function test_ci_coincidente_conserva_ceros_iniciales_y_autoriza(): void
    {
        $this->student->update(['ci' => '0012345']);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), $this->input(['ci' => ' 0012345 ']))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.autorizado', true);

        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function test_ci_presentado_no_bloquea_si_la_ficha_no_tiene_ci(): void
    {
        $this->student->update(['ci' => null]);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), $this->input(['ci' => '999999999']))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.ci_pendiente', true);

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => '999999999',
            'id_ambiente' => $this->aulaId,
        ])->assertCreated()->assertJsonPath('data.veredicto', 'INGRESO_REGISTRADO');
    }

    public function test_confirmacion_directa_rechaza_ci_diferente_sin_registrar_ingreso(): void
    {
        $before = $this->getJson($this->url('estado'))->assertOk()->json('data');

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => '999999999',
            'id_ambiente' => $this->aulaId,
        ])->assertStatus(422)
            ->assertJsonPath('veredicto', 'CI_NO_COINCIDE');

        $this->assertSame(0, DB::table('examen_estudiante')->count());
        $after = $this->getJson($this->url('estado'))->assertOk()->json('data');
        $this->assertSame($before['version'], $after['version']);
        $this->assertSame($before['ingresados'], $after['ingresados']);
    }

    public function test_solo_sis_permite_verificar_y_confirmar(): void
    {
        $this->postJson($this->url('verificar'), [
            'cod_sis' => $this->student->cod_sis,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.foto_url', null);

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated()->assertJsonPath('data.veredicto', 'INGRESO_REGISTRADO');

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'estado_ingreso' => 'INGRESO',
        ]);
    }

    public function test_solo_ci_permite_verificar_y_confirmar_sin_conocer_el_sis(): void
    {
        $this->student->update(['ci' => '0012345']);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), [
            'ci' => '0012345',
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.id_estudiante', $this->student->id_estudiante)
            ->assertJsonPath('data.estudiante.ci', '0012345');

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => '0012345',
            'id_ambiente' => $this->aulaId,
        ])->assertCreated()->assertJsonPath('data.veredicto', 'INGRESO_REGISTRADO');

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'estado_ingreso' => 'INGRESO',
        ]);
    }

    public function test_ci_manual_de_estudiante_ajeno_muestra_ficha_pero_no_autoriza(): void
    {
        $outsideId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '199912543',
            'ci' => '0098765',
            'nombre' => 'María',
            'apellido_paterno' => 'Fuera',
            'apellido_materno' => 'Nómina',
            'estado' => 'ACTIVO',
        ], 'id_estudiante');
        $this->rebuildSnapshot();

        $this->getJson($this->url('buscar') . '?ci=0098')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($this->url('verificar'), [
            'ci' => '0098765',
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_PERTENECE')
            ->assertJsonPath('data.autorizado', false)
            ->assertJsonPath('data.estudiante.id_estudiante', $outsideId);

        $this->assertDatabaseHas('intento_ingreso', [
            'id_estudiante' => $outsideId,
            'cod_sis' => '199912543',
            'ci_presentado' => '0098765',
            'motivo' => 'NO_PERTENECE',
        ]);
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_ci_desconocido_no_revela_ficha_y_registra_intento_sin_sis(): void
    {
        $this->postJson($this->url('verificar'), [
            'ci' => '9876543210',
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_ENCONTRADO')
            ->assertJsonPath('data.motivo', 'Estudiante no encontrado')
            ->assertJsonPath('data.estudiante', null);

        $this->assertDatabaseHas('intento_ingreso', [
            'id_estudiante' => null,
            'cod_sis' => null,
            'ci_presentado' => '9876543210',
            'motivo' => 'NO_ENCONTRADO',
        ]);
    }

    public function test_ci_parcial_no_identifica_a_un_estudiante(): void
    {
        $this->postJson($this->url('verificar'), [
            'ci' => substr($this->student->ci, 0, 4),
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_ENCONTRADO')
            ->assertJsonPath('data.estudiante', null);
    }

    public function test_sis_con_ci_de_otra_ficha_no_cambia_al_estudiante_identificado(): void
    {
        $other = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->where('estudiante.id_estudiante', '!=', $this->student->id_estudiante)
            ->select('estudiante.*')
            ->firstOrFail();

        $this->postJson($this->url('verificar'), [
            'cod_sis' => $this->student->cod_sis,
            'ci' => $other->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'CI_NO_COINCIDE')
            ->assertJsonPath('data.estudiante.id_estudiante', $this->student->id_estudiante)
            ->assertJsonPath('data.autorizado', false);
    }

    public function test_estudiante_de_otro_grupo_no_puede_ingresar(): void
    {
        $this->enrollStudents($this->grupoAjenoId, 1);
        $other = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoAjenoId)
            ->select('estudiante.*')
            ->firstOrFail();
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), [
            'cod_sis' => $other->cod_sis,
            'ci' => $other->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()->assertJsonPath('data.veredicto', 'NO_PERTENECE');
    }

    public function test_aula_incorrecta_se_distingue_del_duplicado(): void
    {
        $this->postJson($this->url('verificar'), $this->input(['id_ambiente' => $this->otraAulaId]))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AULA_INCORRECTA')
            ->assertJsonPath('data.motivo', 'Corresponde al ambiente 691A')
            ->assertJsonPath('data.ambiente_asignado.id_ambiente', $this->aulaId);
    }

    public function test_confirma_una_vez_y_actualiza_estado_incluso_en_el_mismo_segundo(): void
    {
        $before = $this->getJson($this->url('estado'))->assertOk()->json('data');
        $this->assertSame(2, $before['total']);
        $this->assertSame(0, $before['ingresados']);

        $input = [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
        ];
        $this->postJson($this->url('confirmar-ingreso'), $input)
            ->assertCreated()
            ->assertJsonPath('data.veredicto', 'INGRESO_REGISTRADO');
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->docenteId,
            'estado_ingreso' => 'INGRESO',
        ]);
        $this->assertNotNull(DB::table('examen_estudiante')
            ->where('id_examen', $this->exam->id_examen)
            ->where('id_estudiante', $this->student->id_estudiante)
            ->value('registrado_en'));

        $after = $this->getJson($this->url('estado') . '?desde=' . $before['version'])
            ->assertOk()
            ->json('data');
        $this->assertSame(1, $after['ingresados']);
        $this->assertSame(1, $after['pendientes']);
        $this->assertNotSame($before['version'], $after['version']);
        $this->assertCount(1, $after['ultimos_ingresos']);

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'DUPLICADO')
            ->assertJsonPath(
                'data.motivo',
                'Ya ingresó a las ' . substr((string) DB::table('examen_estudiante')
                    ->where('id_examen', $this->exam->id_examen)
                    ->where('id_estudiante', $this->student->id_estudiante)
                    ->value('hora_ingreso'), 0, 5) . ' por el 691A'
            );

        $this->postJson($this->url('confirmar-ingreso'), $input)
            ->assertStatus(409)
            ->assertJsonPath('veredicto', 'DUPLICADO');
        $this->assertSame(1, DB::table('examen_estudiante')->count());
    }

    public function test_dos_ingresos_en_el_mismo_segundo_generan_versiones_distintas(): void
    {
        $students = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->select('estudiante.*')
            ->get();

        $first = $students[0];
        $second = $students[1];
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $first->id_estudiante,
            'ci' => $first->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
        $version = $this->getJson($this->url('estado'))->json('data.version');

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $second->id_estudiante,
            'ci' => $second->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
        $after = $this->getJson($this->url('estado') . '?desde=' . $version)->json('data');
        $this->assertNotSame($version, $after['version']);
        $this->assertSame(2, $after['ingresados']);
        $this->assertSame(0, $after['pendientes']);
    }

    public function test_codigo_desconocido_no_revela_datos(): void
    {
        $this->postJson($this->url('verificar'), [
            'cod_sis' => '999999999',
            'ci' => '99999999',
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_ENCONTRADO')
            ->assertJsonPath('data.estudiante', null);
    }

    public function test_sis_desconocido_sin_ci_registra_intento(): void
    {
        $this->postJson($this->url('verificar'), [
            'cod_sis' => '999999999',
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_ENCONTRADO');

        $this->assertDatabaseHas('intento_ingreso', [
            'id_examen' => $this->exam->id_examen,
            'cod_sis' => '999999999',
            'ci_presentado' => null,
            'motivo' => 'NO_ENCONTRADO',
        ]);
    }

    public function test_no_habilitado_no_puede_confirmar(): void
    {
        DB::table('examen_estudiante')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_grupo' => $this->grupoPropioId,
            'estado_habilitacion' => 'NO_HABILITADO',
            'estado_ingreso' => 'NO_INGRESO',
            'hora_ingreso' => null,
        ]);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), $this->input(['ci' => '9999999']))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'NO_HABILITADO')
            ->assertJsonPath('data.estudiante.id_estudiante', $this->student->id_estudiante);
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
        ])->assertUnprocessable()->assertJsonPath('veredicto', 'NO_HABILITADO');

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'estado_ingreso' => 'NO_INGRESO',
        ]);
    }

    public function test_examen_cerrado_no_se_puede_verificar(): void
    {
        $this->exam->update(['estado' => Exam::EN_CURSO]);
        app(\App\Services\EntryControl\EntryControlSnapshotService::class)
            ->deactivate($this->exam->id_examen);
        $this->postJson($this->url('verificar'), $this->input())->assertStatus(409);
        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function test_ci_pendiente_no_se_captura_al_confirmar(): void
    {
        $this->student->update(['ci' => null]);
        $this->rebuildSnapshot();
        $input = [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => '87654321',
            'id_ambiente' => $this->aulaId,
        ];

        $this->postJson($this->url('verificar'), [
            'cod_sis' => $this->student->cod_sis,
            'id_ambiente' => $this->aulaId,
        ])->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.estudiante.ci', null)
            ->assertJsonPath('data.estudiante.ci_pendiente', true);

        $this->postJson($this->url('confirmar-ingreso'), $input)->assertCreated();
        $this->assertNull($this->student->fresh()->ci);
    }

    public function test_rechazo_sin_ci_guarda_el_intento_sin_ci_presentado(): void
    {
        $this->postJson($this->url('rechazar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'motivo' => 'IDENTIDAD_DUDOSA',
        ])->assertCreated();

        $this->assertDatabaseHas('intento_ingreso', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'ci_presentado' => null,
        ]);
    }

    public function test_rechazo_humano_no_registra_ingreso(): void
    {
        $this->postJson($this->url('rechazar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
            'motivo' => 'IDENTIDAD_DUDOSA',
            'observacion' => 'Foto no coincide.',
        ])->assertCreated()->assertJsonPath('data.registrado', true);

        $this->assertSame(1, DB::table('intento_ingreso')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_alerta_de_riesgo_no_bloquea_el_ingreso(): void
    {
        $reasonId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Suplantacion'], 'id_falta');
        $reportId = DB::table('reporte_estudiante')->insertGetId([
            'id_estudiante' => $this->student->id_estudiante,
            'id_examen' => $this->exam->id_examen,
            'id_tipo_falta' => $reasonId,
            'id_usuario_reportante' => $this->docenteId,
            'evidencia_url' => 'https://example.test/evidencia',
            'evidencia_public_id' => 'test',
            'estado' => 'APROBADO',
            'fecha_revision' => now(),
            'id_usuario_revisor' => $this->docenteId,
        ], 'id_reporte_est');
        DB::table('central_riesgo')->insert([
            'id_reporte_est' => $reportId,
            'id_estudiante' => $this->student->id_estudiante,
            'fecha_registro' => '2026-10-13 09:15:00',
        ]);
        $this->rebuildSnapshot();

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.antecedentes.total', 1)
            ->assertJsonPath('data.antecedentes.latest.tipo_falta', 'Suplantacion')
            ->assertJsonPath('data.antecedentes.latest.fecha_registro', '2026-10-13');

        $secondReportId = DB::table('reporte_estudiante')->insertGetId([
            'id_estudiante' => $this->student->id_estudiante,
            'id_examen' => $this->exam->id_examen,
            'id_tipo_falta' => $reasonId,
            'id_usuario_reportante' => $this->docenteId,
            'evidencia_url' => 'https://example.test/segunda-evidencia',
            'evidencia_public_id' => 'test-segundo',
            'estado' => 'APROBADO',
            'fecha_revision' => now(),
            'id_usuario_revisor' => $this->docenteId,
        ], 'id_reporte_est');
        DB::table('central_riesgo')->insert([
            'id_reporte_est' => $secondReportId,
            'id_estudiante' => $this->student->id_estudiante,
            'fecha_registro' => '2026-10-14 09:15:00',
        ]);

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.antecedentes.total', 1);

        $this->rebuildSnapshot();
        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.antecedentes.total', 2)
            ->assertJsonPath('data.antecedentes.latest.fecha_registro', '2026-10-14');

        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
    }

    public function test_alumno_ajeno_con_riesgo_muestra_alerta_al_docente_y_auxiliar_sin_autorizar(): void
    {
        $outsideId = DB::table('estudiante')->insertGetId([
            'cod_sis' => '199912543',
            'ci' => '0012345',
            'nombre' => 'María',
            'apellido_paterno' => 'Fuera',
            'estado' => 'ACTIVO',
        ], 'id_estudiante');
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->aulaId,
        ]);
        $this->rebuildSnapshot();

        $reasonId = DB::table('tipo_falta')->insertGetId(['nombre' => 'Suplantacion'], 'id_falta');
        $reportId = DB::table('reporte_estudiante')->insertGetId([
            'id_estudiante' => $outsideId,
            'id_examen' => $this->exam->id_examen,
            'id_tipo_falta' => $reasonId,
            'id_usuario_reportante' => $this->docenteId,
            'evidencia_url' => 'https://example.test/evidencia',
            'evidencia_public_id' => 'test-ajeno',
            'estado' => 'APROBADO',
            'fecha_revision' => now(),
            'id_usuario_revisor' => $this->docenteId,
        ], 'id_reporte_est');
        DB::table('central_riesgo')->insert([
            'id_reporte_est' => $reportId,
            'id_estudiante' => $outsideId,
            'fecha_registro' => '2026-10-13 09:15:00',
        ]);
        $this->rebuildSnapshot();

        foreach ([$this->docenteId, $this->otroDocenteId] as $controllerId) {
            $this->actingAs(User::query()->findOrFail($controllerId));
            $this->postJson($this->url('verificar'), [
                'cod_sis' => '199912543',
                'id_ambiente' => $this->aulaId,
            ])->assertOk()
                ->assertJsonPath('data.veredicto', 'NO_PERTENECE')
                ->assertJsonPath('data.autorizado', false)
                ->assertJsonPath('data.estudiante.id_estudiante', $outsideId)
                ->assertJsonPath('data.antecedentes.total', 1)
                ->assertJsonPath('data.antecedentes.latest.tipo_falta', 'Suplantacion')
                ->assertJsonPath('data.antecedentes.latest.fecha_registro', '2026-10-13');
        }
    }

    public function test_auxiliar_solo_puede_controlar_su_ambiente(): void
    {
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $this->aulaId,
        ]);
        $this->rebuildSnapshot();
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->postJson($this->url('verificar'), $this->input())
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO');
        $this->postJson($this->url('verificar'), $this->input(['id_ambiente' => $this->otraAulaId]))
            ->assertForbidden();
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->otraAulaId,
        ])->assertForbidden();
        $this->postJson($this->url('confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();
        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $this->exam->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->otroDocenteId,
        ]);
    }

    public function test_auxiliar_sin_ambiente_asignado_no_puede_controlar(): void
    {
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $this->exam->id_examen,
            'id_usuario' => $this->otroDocenteId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => null,
        ]);
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->postJson($this->url('verificar'), $this->input())->assertForbidden();
        $this->getJson($this->url('buscar') . '?nombre=Estudiante')->assertForbidden();
    }

    public function test_sis_de_nueve_digitos_admite_anio_1999_y_mes_no_valido(): void
    {
        foreach (['199912543', '202150001'] as $code) {
            $this->student->update(['cod_sis' => $code]);
            $this->rebuildSnapshot();

            $this->postJson($this->url('verificar'), $this->input(['cod_sis' => $code]))
                ->assertOk()
                ->assertJsonPath('data.veredicto', 'AUTORIZADO');
        }
    }

    public function test_sis_y_ci_deben_contener_solo_digitos_con_las_longitudes_permitidas(): void
    {
        foreach (['12345678', '1234567890', '2023A9201', '20230920_'] as $code) {
            $this->postJson($this->url('verificar'), $this->input(['cod_sis' => $code]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('cod_sis')
                ->assertJsonPath('errors.cod_sis.0', 'El código SIS debe tener exactamente 9 dígitos.');
        }

        foreach (['12A45', '12345678901'] as $ci) {
            $this->postJson($this->url('verificar'), $this->input(['ci' => $ci]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('ci')
                ->assertJsonPath('errors.ci.0', 'El CI solo puede contener hasta 10 dígitos.');

            foreach (['confirmar-ingreso', 'rechazar-ingreso'] as $action) {
                $this->postJson($this->url($action), [
                    'id_estudiante' => $this->student->id_estudiante,
                    'id_ambiente' => $this->aulaId,
                    'ci' => $ci,
                    'motivo' => 'OTRO',
                ])->assertUnprocessable()->assertJsonValidationErrors('ci');
            }
        }

        $this->assertSame(0, DB::table('intento_ingreso')->count());
        $this->assertSame(0, DB::table('examen_estudiante')->count());
    }

    public function test_solicitud_mal_formada_no_crea_intento(): void
    {
        $this->postJson($this->url('verificar'), $this->input([
            'id_estudiante' => $this->student->id_estudiante,
        ]))->assertUnprocessable()->assertJsonValidationErrors('id_estudiante');

        $this->postJson($this->url('verificar'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertUnprocessable()->assertJsonValidationErrors(['cod_sis', 'ci']);

        $this->assertSame(0, DB::table('intento_ingreso')->count());
    }

    public function test_apertura_falla_sin_capacidad_y_conserva_programado(): void
    {
        $other = $this->createExam(['nombre_examen' => 'Sin capacidad'], [$this->aulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $other->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => 1]);

        try {
            app(RoomAssignmentService::class)->prepare($other->id_examen);
            $this->fail('La apertura debía fallar.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('Capacidad', $exception->getMessage());
        }

        $this->assertSame(Exam::PROGRAMADO, $other->fresh()->estado);
        $this->assertFalse(Schema::hasTable('examen_estudiante_ambiente'));
    }

    public function test_apertura_automatica_respeta_treinta_minutos_configurados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00', config('sciem.zona_horaria')));
        try {
            $exam = $this->createExam([
                'fecha' => '2026-09-29',
                'hora_inicio' => '12:31',
                'hora_fin' => '14:01',
            ], [$this->aulaId]);
            DB::table('grupo_examen')->insert([
                'id_examen' => $exam->id_examen,
                'id_grupo' => $this->grupoPropioId,
            ]);
            $this->assertSame(10, $exam->fresh()->minutos_apertura);
            DB::table('examen')->where('id_examen', $exam->id_examen)
                ->update(['minutos_apertura' => 30]);

            $this->assertSame(30, $exam->fresh()->minutos_apertura);
            app()->call([app(OpenEntryControlJob::class), 'handle']);
            $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);

            Carbon::setTestNow(Carbon::parse('2026-09-29 12:01:00', config('sciem.zona_horaria')));
            app()->call([app(OpenEntryControlJob::class), 'handle']);
            $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_job_abre_en_la_ventana_configurada_y_calcula_el_aula_estable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:05:00', config('sciem.zona_horaria')));
        try {
            $other = $this->createExam([
                'nombre_examen' => 'Apertura automatica',
                'fecha' => '2026-09-29',
                'hora_inicio' => '12:10',
                'hora_fin' => '13:40',
            ], [$this->aulaId]);
            DB::table('grupo_examen')->insert([
                'id_examen' => $other->id_examen,
                'id_grupo' => $this->grupoPropioId,
            ]);

            app()->call([app(OpenEntryControlJob::class), 'handle']);
            $this->assertSame(Exam::EN_INGRESO, $other->fresh()->estado);
            $firstRoom = app(RoomAssignmentService::class)->assignedRoomFor(
                $other->id_examen,
                $this->student->id_estudiante
            );
            $this->assertSame($this->aulaId, (int) $firstRoom->id_ambiente);

            app(RoomAssignmentService::class)->prepare($other->id_examen);
            $this->assertSame(
                $firstRoom->id_ambiente,
                app(RoomAssignmentService::class)
                    ->assignedRoomFor($other->id_examen, $this->student->id_estudiante)
                    ->id_ambiente
            );
            $this->assertFalse(Schema::hasTable('examen_estudiante_ambiente'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_aulas_derivadas_respetan_capacidades_y_orden_de_estudiantes(): void
    {
        $other = $this->createExam(['nombre_examen' => 'Dos ambientes'], [$this->aulaId, $this->otraAulaId]);
        DB::table('grupo_examen')->insert([
            'id_examen' => $other->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => 1]);
        app(RoomAssignmentService::class)->prepare($other->id_examen);

        $ids = DB::table('grupo_estudiante')
            ->where('id_grupo', $this->grupoPropioId)
            ->orderBy('id_estudiante')
            ->pluck('id_estudiante');
        $rooms = app(RoomAssignmentService::class);

        $this->assertSame($this->aulaId, (int) $rooms->assignedRoomFor($other->id_examen, $ids[0])->id_ambiente);
        $this->assertSame($this->otraAulaId, (int) $rooms->assignedRoomFor($other->id_examen, $ids[1])->id_ambiente);
    }

    public function test_otro_docente_no_tiene_acceso(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));

        $this->postJson($this->url('verificar'), $this->input())->assertForbidden();
        $this->getJson($this->url('buscar') . '?nombre=Estudiante')->assertForbidden();
        $this->getJson($this->url('estado'))->assertForbidden();
    }
}
