<?php

namespace Tests\Feature\Exams;

use App\Exceptions\Exams\ExamAssistantNotFoundException;
use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Models\Exam;
use App\Models\ExamAssistant;
use App\Services\Exams\AssistantClassroomService;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Asignación del ambiente de control a los auxiliares habilitados de un examen (HU-09).
 */
class AssistantClassroomServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private const MARIA = '33333333-3333-4333-8333-000000000001';
    private const JORGE = '33333333-3333-4333-8333-000000000002';
    private const DANIELA = '33333333-3333-4333-8333-000000000003';

    private AssistantClassroomService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamCatalog();
        $this->seedAssistants();

        $this->service = app(AssistantClassroomService::class);
    }

    public function test_asigna_ambientes_a_tres_auxiliares_y_reasigna_uno(): void
    {
        $exam = $this->examWithAssistants();

        $this->service->assign($exam, self::MARIA, $this->aulaId);
        $this->service->assign($exam, self::JORGE, $this->otraAulaId);
        $this->service->assign($exam, self::DANIELA, $this->aulaId);

        $reassigned = $this->service->assign($exam, self::DANIELA, $this->otraAulaId);

        $this->assertSame($this->otraAulaId, $reassigned->id_ambiente);
        $this->assertSame('692B', $reassigned->classroom->nro_aula);
        $this->assertAssignedTo($exam, self::MARIA, $this->aulaId);
        $this->assertAssignedTo($exam, self::JORGE, $this->otraAulaId);
        $this->assertAssignedTo($exam, self::DANIELA, $this->otraAulaId);
        $this->assertSame(4, DB::table('log')->where('tabla_afectada', 'examen_auxiliar')->count());
    }

    public function test_lista_los_auxiliares_con_su_ambiente_y_los_ambientes_del_examen(): void
    {
        $exam = $this->examWithAssistants();
        $this->service->assign($exam, self::JORGE, $this->otraAulaId);

        $result = $this->service->listForExam($exam);

        $this->assertTrue($result['editable']);
        $this->assertSame(['691A', '692B'], $result['classrooms']->pluck('nro_aula')->all());
        $this->assertSame(
            ['Daniela Ferrufino Soliz', 'Jorge Rocha Vidal', 'María López Arnez'],
            $result['assistants']->map(fn ($assistant) => $assistant->assistant->nombre_completo)->all()
        );
        $this->assertNull($result['assistants'][0]->classroom);
        $this->assertSame('692B', $result['assistants'][1]->classroom->nro_aula);
    }

    public function test_el_listado_no_crece_en_consultas_con_mas_auxiliares(): void
    {
        $exam = $this->examWithAssistants();

        DB::enableQueryLog();
        $this->service->listForExam($exam);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Auxiliares, sus usuarios, sus ambientes y los ambientes del examen.
        $this->assertLessThanOrEqual(4, $queries);
    }

    public function test_repetir_el_mismo_ambiente_no_registra_otra_vez_en_bitacora(): void
    {
        $exam = $this->examWithAssistants();

        $this->service->assign($exam, self::MARIA, $this->aulaId);
        $this->service->assign($exam, self::MARIA, $this->aulaId);

        $this->assertSame(1, DB::table('log')->where('tabla_afectada', 'examen_auxiliar')->count());
    }

    public function test_rechaza_un_ambiente_que_no_es_del_examen(): void
    {
        $exam = $this->examWithAssistants();
        $this->service->assign($exam, self::MARIA, $this->aulaId);

        try {
            $this->service->assign($exam, self::MARIA, $this->aulaInactivaId);
            $this->fail('Debió rechazar un ambiente ajeno al examen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id_ambiente', $e->errors());
        }

        $this->assertAssignedTo($exam, self::MARIA, $this->aulaId);
    }

    public function test_rechaza_un_auxiliar_no_habilitado_para_el_examen(): void
    {
        $exam = $this->createExam([], [$this->aulaId]);

        $this->expectException(ExamAssistantNotFoundException::class);

        $this->service->assign($exam, self::MARIA, $this->aulaId);
    }

    public function test_rechaza_el_examen_de_otro_docente(): void
    {
        $exam = $this->examWithAssistants(['id_usuario_docente' => $this->otroDocenteId]);

        try {
            $this->service->assign($exam, self::MARIA, $this->aulaId);
            $this->fail('Debió rechazar la asignación en el examen de otro docente.');
        } catch (ExamOwnershipException $e) {
            $this->assertAssignedTo($exam, self::MARIA, null);
        }

        $this->expectException(ExamOwnershipException::class);

        $this->service->listForExam($exam);
    }

    public function test_bloquea_los_cambios_desde_que_se_abre_el_control_de_ingreso(): void
    {
        foreach ([Exam::EN_INGRESO, Exam::EN_CURSO, Exam::FINALIZADO, Exam::CANCELADO] as $status) {
            $exam = $this->examWithAssistants(['estado' => $status], $this->aulaId);

            try {
                $this->service->assign($exam, self::MARIA, $this->otraAulaId);
                $this->fail("Debió bloquear el cambio en estado {$status}.");
            } catch (ExamStateException $e) {
                $this->assertAssignedTo($exam, self::MARIA, $this->aulaId);
            }

            $this->assertFalse($this->service->listForExam($exam)['editable']);
        }
    }

    public function test_el_auxiliar_ve_solo_sus_examenes_vigentes_con_su_ambiente(): void
    {
        $later = $this->examWithAssistants(['fecha' => $this->futureDate(10), 'nombre_examen' => 'Segundo parcial']);
        $sooner = $this->examWithAssistants(['fecha' => $this->futureDate(3), 'nombre_examen' => 'Primer parcial']);
        $cancelled = $this->examWithAssistants(['estado' => Exam::CANCELADO, 'nombre_examen' => 'Cancelado']);
        $this->service->assign($sooner, self::MARIA, $this->otraAulaId);

        $assignments = $this->service->listForAssistant(self::MARIA);

        $this->assertSame([$sooner->id_examen, $later->id_examen], $assignments->pluck('id_examen')->all());
        $this->assertSame('692B', $assignments[0]->classroom->nro_aula);
        $this->assertNull($assignments[1]->classroom);
        $this->assertNotContains($cancelled->id_examen, $assignments->pluck('id_examen')->all());
        $this->assertCount(0, $this->service->listForAssistant('33333333-3333-4333-8333-000000000099'));
    }

    /** Examen del docente con los dos ambientes y los tres auxiliares habilitados. */
    private function examWithAssistants(array $overrides = [], ?int $classroomId = null): Exam
    {
        $exam = $this->createExam($overrides, [$this->aulaId, $this->otraAulaId]);

        foreach ([self::MARIA, self::JORGE, self::DANIELA] as $userId) {
            ExamAssistant::create([
                'id_examen' => $exam->id_examen,
                'id_usuario' => $userId,
                'id_usuario_docente_habilita' => $exam->id_usuario_docente,
                'id_ambiente' => $classroomId,
            ]);
        }

        return $exam;
    }

    private function assertAssignedTo(Exam $exam, string $userId, ?int $classroomId): void
    {
        $this->assertSame(
            $classroomId,
            ExamAssistant::forExamAndAssistant($exam->id_examen, $userId)->firstOrFail()->id_ambiente
        );
    }

    private function seedAssistants(): void
    {
        $people = [
            [self::MARIA, 'María', 'López', 'Arnez', '201900233'],
            [self::JORGE, 'Jorge', 'Rocha', 'Vidal', '202000871'],
            [self::DANIELA, 'Daniela', 'Ferrufino', 'Soliz', '201800451'],
        ];

        foreach ($people as [$id, $name, $lastName, $secondLastName, $sis]) {
            DB::table('usuario')->insert([
                'id_usuario' => $id,
                'nombre' => $name,
                'apellido_paterno' => $lastName,
                'apellido_materno' => $secondLastName,
                'correo' => strtolower($name) . '.' . $sis . '@umss.edu',
                'contrasenia' => 'x',
                'cod_sis' => $sis,
                'estado' => RecordStatus::ACTIVE,
            ]);
        }
    }
}