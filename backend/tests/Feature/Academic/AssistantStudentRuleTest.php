<?php

namespace Tests\Feature\Academic;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAssistantManagement;
use Tests\TestCase;

/**
 * HU-08: quien es estudiante esperado de un examen no puede ser su auxiliar.
 *
 * Los esperados se derivan de grupo_estudiante; examen_estudiante solo existe desde un
 * ingreso real, así que con el examen PROGRAMADO está siempre vacía.
 */
class AssistantStudentRuleTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAssistantManagement;

    private string $studentAssistantId;

    private string $otherAssistantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAssistantManagement();

        $this->studentAssistantId = $this->createAssistant('Carlos', '202400003');
        $this->otherAssistantId = $this->createAssistant('Diana', '202400004');

        $this->putInGroup($this->studentAssistantId, $this->grupoPropioId);
        $this->putInGroup($this->otherAssistantId, $this->grupoPropioId);
    }

    private function availableExamIds(string $assistantId): array
    {
        $row = collect($this->getJson('/api/docente/auxiliares')->assertOk()->json('data'))
            ->firstWhere('id_usuario', $assistantId);

        return collect($row['examenes_disponibles'])->pluck('id_examen')->all();
    }

    public function test_rechaza_habilitar_a_quien_esta_inscrito_por_un_grupo_del_examen_sin_ingresos_registrados(): void
    {
        $this->enrollStudentWithSis($this->grupoPropioId, '202400003');

        $this->assertSame(Exam::PROGRAMADO, DB::table('examen')->where('id_examen', $this->examId)->value('estado'));
        $this->assertSame(0, DB::table('examen_estudiante')->where('id_examen', $this->examId)->count());

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->studentAssistantId,
        ])->assertStatus(422)->assertJsonValidationErrors('id_usuario');

        $this->assertFalse($this->isEnabledInExam($this->studentAssistantId, $this->examId));

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->otherAssistantId,
        ])->assertCreated();
    }

    public function test_el_estudiante_inscrito_en_otro_grupo_del_mismo_examen_tambien_se_rechaza(): void
    {
        DB::table('grupo_examen')->insert(['id_grupo' => $this->segundoGrupoPropioId, 'id_examen' => $this->examId]);
        $this->enrollStudentWithSis($this->segundoGrupoPropioId, '202400003');

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->studentAssistantId,
        ])->assertStatus(422);
    }

    public function test_un_estudiante_de_un_grupo_que_no_es_del_examen_no_impide_la_habilitacion(): void
    {
        $this->enrollStudentWithSis($this->segundoGrupoPropioId, '202400003');

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $this->studentAssistantId,
        ])->assertCreated();
    }

    public function test_compara_por_sis_normalizado(): void
    {
        // El modelo guarda el SIS recortado y en mayúsculas, igual que la nómina.
        $mixed = User::create([
            'nombre' => 'Elena',
            'apellido_paterno' => 'Auxiliar',
            'correo' => 'elena@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '  abc  123 ',
            'estado' => 'ACTIVO',
        ]);
        $this->assertSame('ABC 123', $mixed->cod_sis);

        $roleId = DB::table('rol')->where('nombre_rol', 'Auxiliar')->value('id_rol');
        DB::table('usuario_rol')->insert([
            'id_usuario' => $mixed->id_usuario,
            'id_rol' => $roleId,
            'fecha_inicio' => now(),
        ]);
        $this->putInGroup($mixed->id_usuario, $this->grupoPropioId);
        $this->enrollStudentWithSis($this->grupoPropioId, 'ABC 123');

        $this->postJson("/api/docente/examenes/{$this->examId}/auxiliares", [
            'id_usuario' => $mixed->id_usuario,
        ])->assertStatus(422);
    }

    public function test_la_lista_de_examenes_disponibles_excluye_el_examen_donde_es_estudiante(): void
    {
        $this->enrollStudentWithSis($this->grupoPropioId, '202400003');

        $this->assertSame([], $this->availableExamIds($this->studentAssistantId));
        $this->assertSame([$this->examId], $this->availableExamIds($this->otherAssistantId));
    }
}
