<?php

namespace Tests\Feature\EntryControl;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use App\Services\EntryControl\EntryControlSnapshotService;
use App\Services\EntryControl\RoomAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * Quién puede usar el control de ingreso de un examen: su docente y los auxiliares habilitados
 * en él, siempre con sesión.
 */
class EntryControlAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private Exam $exam;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamCatalog();

        $this->exam = $this->examWithRooms([$this->aulaId, $this->otraAulaId]);
        $this->student = Student::query()
            ->join('grupo_estudiante as gs', 'gs.id_estudiante', '=', 'estudiante.id_estudiante')
            ->where('gs.id_grupo', $this->grupoPropioId)
            ->orderBy('estudiante.id_estudiante')
            ->select('estudiante.*')
            ->firstOrFail();
    }

    /** @param array<int, int> $roomIds */
    private function examWithRooms(array $roomIds, string $name = 'Examen'): Exam
    {
        $exam = $this->createExam(['nombre_examen' => $name], $roomIds);
        DB::table('grupo_examen')->insert([
            'id_examen' => $exam->id_examen,
            'id_grupo' => $this->grupoPropioId,
        ]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);

        return $exam;
    }

    private function enable(Exam $exam, string $userId, ?int $roomId): void
    {
        DB::table('examen_auxiliar')->insert([
            'id_examen' => $exam->id_examen,
            'id_usuario' => $userId,
            'id_usuario_docente_habilita' => $this->docenteId,
            'id_ambiente' => $roomId,
        ]);
        app(RoomAssignmentService::class)->prepare((int) $exam->id_examen);
    }

    private function url(Exam $exam, string $action): string
    {
        return "/api/control-ingreso/examenes/{$exam->id_examen}/{$action}";
    }

    private function actingAsUser(string $userId): void
    {
        $this->actingAs(User::query()->findOrFail($userId));
    }

    private function verifyInput(int $roomId): array
    {
        return ['cod_sis' => $this->student->cod_sis, 'id_ambiente' => $roomId];
    }

    public function test_sin_sesion_todas_las_rutas_responden_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/control-ingreso/examenes')->assertUnauthorized();
        $this->getJson($this->url($this->exam, 'contexto'))->assertUnauthorized();
        $this->getJson($this->url($this->exam, 'buscar') . '?nombre=Estudiante')->assertUnauthorized();
        $this->getJson($this->url($this->exam, 'estado'))->assertUnauthorized();
        $this->postJson($this->url($this->exam, 'verificar'), $this->verifyInput($this->aulaId))->assertUnauthorized();
        $this->postJson($this->url($this->exam, 'confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertUnauthorized();
        $this->postJson($this->url($this->exam, 'rechazar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'motivo' => 'OTRO',
        ])->assertUnauthorized();
    }

    public function test_el_docente_del_examen_entra_con_un_token_bearer(): void
    {
        $token = User::query()->findOrFail($this->docenteId)->createToken('prueba')->plainTextToken;

        $this->withToken($token)
            ->getJson($this->url($this->exam, 'contexto'))
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'DOCENTE');
    }

    public function test_un_auxiliar_de_otro_examen_recibe_403_en_este(): void
    {
        $other = $this->examWithRooms([$this->aulaId], 'Otro examen');
        $this->enable($other, $this->otroDocenteId, $this->aulaId);
        $this->actingAsUser($this->otroDocenteId);

        $this->getJson($this->url($other, 'contexto'))->assertOk()->assertJsonPath('data.rol_controlador', 'AUXILIAR');

        $this->getJson($this->url($this->exam, 'contexto'))->assertForbidden();
        $this->getJson($this->url($this->exam, 'buscar') . '?nombre=Estudiante')->assertForbidden();
        $this->getJson($this->url($this->exam, 'estado'))->assertForbidden();
        $this->postJson($this->url($this->exam, 'verificar'), $this->verifyInput($this->aulaId))->assertForbidden();
        $this->postJson($this->url($this->exam, 'confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertForbidden();
        $this->postJson($this->url($this->exam, 'rechazar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'motivo' => 'OTRO',
        ])->assertForbidden();

        $this->assertSame(0, DB::table('examen_estudiante')->where('id_examen', $this->exam->id_examen)->count());
    }

    public function test_con_un_solo_ambiente_el_auxiliar_sin_asignar_controla_ese_ambiente(): void
    {
        $single = $this->examWithRooms([$this->aulaId], 'Un solo ambiente');
        $this->enable($single, $this->otroDocenteId, null);
        $this->actingAsUser($this->otroDocenteId);

        $this->getJson('/api/control-ingreso/examenes')
            ->assertOk()
            ->assertJsonFragment(['id_examen' => (int) $single->id_examen]);

        $this->getJson($this->url($single, 'contexto'))
            ->assertOk()
            ->assertJsonPath('data.rol_controlador', 'AUXILIAR')
            ->assertJsonPath('data.id_ambiente_asignado', $this->aulaId);

        $this->postJson($this->url($single, 'verificar'), $this->verifyInput($this->aulaId))
            ->assertOk()
            ->assertJsonPath('data.veredicto', 'AUTORIZADO');

        $this->postJson($this->url($single, 'confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertCreated();

        $this->assertDatabaseHas('examen_estudiante', [
            'id_examen' => $single->id_examen,
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
            'id_usuario_controlador' => $this->otroDocenteId,
        ]);

        // La asignación implícita nunca se escribe: el docente sigue sin haberle asignado nada.
        $this->assertNull(
            DB::table('examen_auxiliar')
                ->where('id_examen', $single->id_examen)
                ->where('id_usuario', $this->otroDocenteId)
                ->value('id_ambiente')
        );
    }

    public function test_con_varios_ambientes_el_auxiliar_sin_asignar_recibe_403_con_un_mensaje_claro(): void
    {
        $this->enable($this->exam, $this->otroDocenteId, null);
        $this->actingAsUser($this->otroDocenteId);
        $message = EntryControlSnapshotService::UNASSIGNED_ROOM_MESSAGE;

        $this->getJson($this->url($this->exam, 'contexto'))->assertForbidden()->assertJsonPath('message', $message);
        $this->getJson($this->url($this->exam, 'buscar') . '?nombre=Estudiante')
            ->assertForbidden()
            ->assertJsonPath('message', $message);
        $this->postJson($this->url($this->exam, 'verificar'), $this->verifyInput($this->aulaId))
            ->assertForbidden()
            ->assertJsonPath('message', $message);
        $this->getJson($this->url($this->exam, 'estado'))->assertForbidden()->assertJsonPath('message', $message);

        $this->getJson('/api/control-ingreso/examenes')
            ->assertOk()
            ->assertJsonMissing(['id_examen' => (int) $this->exam->id_examen]);
    }

    public function test_un_auxiliar_asignado_a_otro_ambiente_recibe_403_en_este(): void
    {
        $this->enable($this->exam, $this->otroDocenteId, $this->otraAulaId);
        $this->actingAsUser($this->otroDocenteId);

        $this->postJson($this->url($this->exam, 'verificar'), $this->verifyInput($this->aulaId))->assertForbidden();
        $this->postJson($this->url($this->exam, 'confirmar-ingreso'), [
            'id_estudiante' => $this->student->id_estudiante,
            'id_ambiente' => $this->aulaId,
        ])->assertForbidden();
    }
}
