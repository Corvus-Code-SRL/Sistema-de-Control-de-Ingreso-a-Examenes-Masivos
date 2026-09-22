<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-24 — criterios 1 a 9: creación del examen, validaciones y advertencias.
 */
class CreateExamTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private const URL = '/api/examenes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
    }

    public function test_crea_el_examen_programado_asociado_al_par_y_al_docente_creador(): void
    {
        $response = $this->postJson(self::URL, $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('mensaje', 'Examen creado en estado Programado.')
            ->assertJsonPath('data.estado', Exam::PROGRAMADO)
            ->assertJsonPath('data.id_carrera', $this->sistemasId)
            ->assertJsonPath('data.id_materia', $this->calculoId)
            ->assertJsonPath('data.id_usuario_docente', $this->docenteId)
            ->assertJsonPath('data.carrera.nombre', 'Ingenieria de Sistemas')
            ->assertJsonPath('data.materia.nombre', 'Calculo II')
            ->assertJsonPath('data.hora_inicio', '08:00')
            ->assertJsonPath('data.hora_fin', '09:30')
            ->assertJsonPath('data.tipo_examen.categoria', 'REGULAR')
            ->assertJsonPath('data.ambientes.0.id_ambiente', $this->aulaId);

        $this->assertDatabaseHas('examen', [
            'id_examen'          => $response->json('data.id_examen'),
            'estado'             => Exam::PROGRAMADO,
            'id_carrera'         => $this->sistemasId,
            'id_materia'         => $this->calculoId,
            'id_usuario_docente' => $this->docenteId,
            'id_tipo_examen'     => $this->examTypeId('REGULAR'),
        ]);
        $this->assertDatabaseHas('examen_ambiente', [
            'id_examen'   => $response->json('data.id_examen'),
            'id_ambiente' => $this->aulaId,
        ]);
    }

    public function test_la_categoria_final_no_crea_tipos_de_examen_falsos(): void
    {
        $typesBefore = DB::table('tipo_examen')->count();

        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Final de Calculo II',
            'categoria'     => 'FINAL',
        ]))->assertUnprocessable()->assertJsonValidationErrors('categoria');

        // Una categoría válida reutiliza el catálogo en lugar de crear un tipo nuevo.
        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Final de Calculo II',
            'categoria'     => 'MESA',
        ]))->assertCreated()->assertJsonPath('data.tipo_examen.nombre', 'Mesa');

        $this->assertSame($typesBefore, DB::table('tipo_examen')->count());
        $this->assertDatabaseMissing('tipo_examen', ['nombre' => 'Final de Calculo II']);
        $this->assertSame(1, Exam::count());
    }

    public function test_sin_tipo_registrado_para_la_categoria_no_crea_nada(): void
    {
        DB::table('tipo_examen')->where('categoria', 'ADMISION')->delete();
        $typesBefore = DB::table('tipo_examen')->count();

        $this->postJson(self::URL, $this->validPayload(['categoria' => 'ADMISION']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categoria');

        $this->assertSame($typesBefore, DB::table('tipo_examen')->count());
        $this->assertSame(0, Exam::count());
    }

    public function test_criterio_1_exige_los_datos_obligatorios(): void
    {
        $this->postJson(self::URL, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nombre_examen',
                'id_carrera',
                'id_materia',
                'fecha',
                'hora_inicio',
                'duracion',
                'ambientes',
                'grupos',
            ]);

        $this->assertSame(0, Exam::count());
    }

    public function test_criterio_2_rechaza_fecha_y_hora_pasadas(): void
    {
        $now = Carbon::now(config('sciem.zona_horaria'));

        $this->postJson(self::URL, $this->validPayload([
            'fecha' => $now->copy()->subDay()->toDateString(),
        ]))->assertUnprocessable()->assertJsonValidationErrors('fecha');

        // Hoy, pero una hora que ya pasó en la hora local del campus.
        $oneMinuteAgo = $now->copy()->subMinute();
        $this->postJson(self::URL, $this->validPayload([
            'fecha'       => $oneMinuteAgo->toDateString(),
            'hora_inicio' => $oneMinuteAgo->format('H:i'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('fecha');

        $this->assertSame(0, Exam::count());
    }

    public function test_criterio_3_rechaza_duraciones_invalidas(): void
    {
        foreach ([0, -30, 'noventa', 1.5, 1440] as $duration) {
            $this->postJson(self::URL, $this->validPayload(['duracion' => $duration]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('duracion');
        }

        $this->assertSame(0, Exam::count());
    }

    public function test_criterio_4_solo_acepta_ambientes_activos_del_catalogo(): void
    {
        $invalid = [
            [],
            [999999],
            ['Aula nueva'],
            [$this->aulaInactivaId],
        ];

        foreach ($invalid as $classrooms) {
            $this->postJson(self::URL, $this->validPayload(['ambientes' => $classrooms]))
                ->assertUnprocessable();
        }

        $this->postJson(self::URL, $this->validPayload(['ambientes' => 'Aula nueva']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ambientes');

        $this->assertSame(0, Exam::count());
    }

    public function test_criterio_5_la_materia_debe_existir_en_la_carrera(): void
    {
        $this->postJson(self::URL, $this->validPayload(['id_materia' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_materia');

        // La materia existe, pero no dentro de esa carrera.
        $this->postJson(self::URL, $this->validPayload([
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->materiaInactivaId,
        ]))->assertUnprocessable()->assertJsonValidationErrors('id_materia');

        // La materia sola no alcanza: sin carrera el par es ambiguo.
        $payload = $this->validPayload();
        unset($payload['id_carrera']);
        $this->postJson(self::URL, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id_carrera');

        $this->assertSame(0, Exam::count());
    }

    public function test_criterio_5_rechaza_un_par_inactivo(): void
    {
        // Materia ACTIVA con par INACTIVO.
        $this->postJson(self::URL, $this->validPayload([
            'id_carrera' => $this->informaticaId,
            'id_materia' => $this->basesDatosId,
        ]))->assertUnprocessable()->assertJsonValidationErrors('id_materia');

        // Materia INACTIVA con par ACTIVO.
        $this->postJson(self::URL, $this->validPayload([
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->materiaInactivaId,
        ]))->assertUnprocessable()->assertJsonValidationErrors('id_materia');

        $this->assertSame(0, Exam::count());
    }

    public function test_criterio_6_advierte_un_nombre_duplicado_en_la_misma_fecha(): void
    {
        $this->createExam(['nombre_examen' => 'Primer parcial', 'hora_inicio' => '14:00']);

        $response = $this->postJson(self::URL, $this->validPayload(['nombre_examen' => 'PRIMER PARCIAL']))
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'errors' => ['nombre_duplicado']]);

        $this->assertArrayNotHasKey('superposicion_horario', $response->json('errors'));

        $this->assertSame(1, Exam::count());

        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen'          => 'PRIMER PARCIAL',
            'confirmar_advertencias' => true,
        ]))->assertCreated();

        $this->assertSame(2, Exam::count());
    }

    public function test_criterio_6_el_mismo_nombre_de_otro_docente_no_es_duplicado(): void
    {
        $this->createExam([
            'nombre_examen'      => 'Primer parcial',
            'hora_inicio'        => '14:00',
            'id_usuario_docente' => $this->otroDocenteId,
        ]);

        $this->postJson(self::URL, $this->validPayload())->assertCreated();
    }

    public function test_criterio_8_advierte_superposicion_con_otro_examen_del_docente(): void
    {
        // 08:00 a 09:30, en otro ambiente.
        $this->createExam(['nombre_examen' => 'Examen de las 8'], [$this->otraAulaId]);

        $response = $this->postJson(self::URL, $this->validPayload(['hora_inicio' => '09:00']))
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.superposicion_horario.0',
                'Ya tiene el examen «Examen de las 8» en un horario que se superpone.'
            );

        $this->assertArrayNotHasKey('superposicion_ambiente', $response->json('errors'));

        // Empieza justo cuando termina el otro: no se superpone.
        $this->postJson(self::URL, $this->validPayload(['hora_inicio' => '09:30']))
            ->assertCreated();
    }

    public function test_criterio_9_advierte_un_ambiente_ya_reservado(): void
    {
        $this->createExam([
            'nombre_examen'      => 'Examen ajeno',
            'id_usuario_docente' => $this->otroDocenteId,
        ], [$this->aulaId]);

        $response = $this->postJson(self::URL, $this->validPayload(['hora_inicio' => '09:00']))
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.superposicion_ambiente.0',
                'El ambiente 691A ya está reservado para «Examen ajeno» en ese horario.'
            );

        $this->assertArrayNotHasKey('superposicion_horario', $response->json('errors'));

        $this->postJson(self::URL, $this->validPayload([
            'hora_inicio' => '09:00',
            'ambientes'   => [$this->otraAulaId],
        ]))->assertCreated();
    }

    public function test_un_examen_cancelado_no_ocupa_horario_ni_ambiente(): void
    {
        $this->createExam(['nombre_examen' => 'Cancelado', 'estado' => Exam::CANCELADO], [$this->aulaId]);

        $this->postJson(self::URL, $this->validPayload())->assertCreated();
    }

    public function test_un_examen_que_cruza_la_medianoche_se_compara_con_el_dia_siguiente(): void
    {
        $day = $this->futureDate();
        $nextDay = Carbon::parse($day)->addDay()->toDateString();

        // 23:00 a 01:00 del día siguiente.
        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Nocturno',
            'fecha'         => $day,
            'hora_inicio'   => '23:00',
            'duracion'      => 120,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.fecha', $day)
            ->assertJsonPath('data.hora_fin', '01:00');

        // 00:30 del día siguiente, mismo docente y mismo ambiente: choca con ambos.
        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Madrugada',
            'fecha'         => $nextDay,
            'hora_inicio'   => '00:30',
            'duracion'      => 60,
        ]))
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.superposicion_horario.0',
                'Ya tiene el examen «Nocturno» en un horario que se superpone.'
            )
            ->assertJsonPath(
                'errors.superposicion_ambiente.0',
                'El ambiente 691A ya está reservado para «Nocturno» en ese horario.'
            );

        // La misma hora del mismo día (00:30 antes de las 23:00) no choca.
        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Mañana previa',
            'fecha'         => $day,
            'hora_inicio'   => '00:30',
            'duracion'      => 60,
        ]))->assertCreated();

        // Empieza a la 01:00 del día siguiente, cuando el nocturno ya terminó.
        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Después',
            'fecha'         => $nextDay,
            'hora_inicio'   => '01:00',
            'duracion'      => 60,
        ]))->assertCreated();

        $this->assertSame(3, Exam::count());
    }

    public function test_un_examen_nuevo_que_cruza_la_medianoche_detecta_el_del_dia_siguiente(): void
    {
        $day = $this->futureDate();
        $nextDay = Carbon::parse($day)->addDay()->toDateString();

        // Existe un examen a las 00:30 del día siguiente, de otro docente, en el mismo ambiente.
        $this->createExam([
            'nombre_examen'      => 'Madrugada ajena',
            'fecha'              => $nextDay,
            'hora_inicio'        => '00:30',
            'duracion'           => 60,
            'id_usuario_docente' => $this->otroDocenteId,
        ], [$this->aulaId]);

        // Existe otro del mismo docente a las 00:15 del día siguiente, en otro ambiente.
        $this->createExam([
            'nombre_examen' => 'Madrugada propia',
            'fecha'         => $nextDay,
            'hora_inicio'   => '00:15',
            'duracion'      => 30,
        ], [$this->otraAulaId]);

        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Nocturno',
            'fecha'         => $day,
            'hora_inicio'   => '23:30',
            'duracion'      => 90,
        ]))
            ->assertStatus(409)
            ->assertJsonPath(
                'errors.superposicion_horario.0',
                'Ya tiene el examen «Madrugada propia» en un horario que se superpone.'
            )
            ->assertJsonPath(
                'errors.superposicion_ambiente.0',
                'El ambiente 691A ya está reservado para «Madrugada ajena» en ese horario.'
            );

        // Si termina a las 00:15 ya no alcanza a ninguno de los dos.
        $this->postJson(self::URL, $this->validPayload([
            'nombre_examen' => 'Nocturno',
            'fecha'         => $day,
            'hora_inicio'   => '23:30',
            'duracion'      => 45,
        ]))->assertCreated()->assertJsonPath('data.hora_fin', '00:15');
    }
}
