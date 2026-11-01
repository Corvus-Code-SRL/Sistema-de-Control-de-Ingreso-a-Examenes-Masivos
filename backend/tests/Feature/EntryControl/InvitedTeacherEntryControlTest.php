<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\Role;
use App\Models\Student;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * El Invitado opera el control de ingreso de sus exámenes y ve, como un auxiliar, el aviso de
 * antecedentes de la Central de Riesgo (HU-032, criterio 13): lo que no puede es consultarla.
 */
class InvitedTeacherEntryControlTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;

    private Exam $exam;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()]);
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['cod_sis' => 'INV4821']);
        $this->assignRole($this->docenteId, Role::INVITADO);

        $this->exam = $this->createExam([], [$this->aulaId]);
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
        $this->actAsUserId($this->docenteId);
    }

    public function test_invitado_ve_el_aviso_de_antecedentes_al_verificar_a_un_estudiante(): void
    {
        $cache = Cache::store(config('sciem.entry_control_cache_store', 'redis'));
        $token = $cache->get("entry-control:{$this->exam->id_examen}:active");
        $key = "entry-control:{$this->exam->id_examen}:{$token}:student:{$this->student->id_estudiante}";
        $record = $cache->get($key);
        $record['antecedentes'] = [
            'cantidad' => 1,
            'detalles' => [['tipo' => 'Suplantacion', 'fecha' => '2026-10-13']],
        ];
        $cache->put($key, $record, now()->addHour());

        $this->postJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/verificar", [
            'cod_sis' => $this->student->cod_sis,
            'ci' => $this->student->ci,
            'id_ambiente' => $this->aulaId,
        ])
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO')
            ->assertJsonPath('data.antecedentes.total', 1)
            ->assertJsonPath('data.antecedentes.latest.tipo_falta', 'Suplantacion');
    }

    public function test_invitado_confirma_el_ingreso_y_lista_los_intentos_de_su_examen(): void
    {
        $this->postJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/confirmar-ingreso", [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();

        $this->getJson("/api/control-ingreso/examenes/{$this->exam->id_examen}/intentos?id_ambiente={$this->aulaId}")
            ->assertOk();
    }
}
