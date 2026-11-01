<?php

namespace Tests\Feature\Incidents;

use App\Models\Role;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsRiskCenterRecords;
use Tests\TestCase;

/**
 * El docente invitado es el responsable de sus exámenes: revisa con doble comprobación los reportes
 * de falta de esos exámenes, y sin ello nunca llegarían a la Central de Riesgo.
 */
class InvitedTeacherIncidentReviewTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRiskCenterRecords;
    use AssignsIncidentRole;

    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);

        $this->context = $this->createReportingContext();
        $this->assignRole($this->context['id_usuario'], Role::INVITADO);
        $this->actAsUserId($this->context['id_usuario']);
    }

    public function test_invitado_lista_y_ve_los_reportes_de_su_examen(): void
    {
        $reportId = $this->insertStudentReport($this->context);

        $this->getJson('/api/docente/incidencias')->assertOk();
        $this->getJson("/api/docente/incidencias/estudiante-{$reportId}")->assertOk();
    }

    public function test_invitado_aprueba_el_reporte_de_su_examen_y_llega_a_la_central_de_riesgo(): void
    {
        $reportId = $this->insertStudentReport($this->context);

        $this->postJson("/api/docente/incidencias/estudiante-{$reportId}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'APROBADO');

        $this->assertDatabaseHas('central_riesgo', ['id_reporte_est' => $reportId]);
    }

    public function test_invitado_rechaza_el_reporte_de_su_examen(): void
    {
        $reportId = $this->insertStudentReport($this->context);

        $this->postJson(
            "/api/docente/incidencias/estudiante-{$reportId}/rechazar",
            ['observacion' => 'No se comprobó la falta.']
        )->assertOk()->assertJsonPath('data.estado', 'RECHAZADO');
    }

    public function test_invitado_no_revisa_el_reporte_del_examen_de_otro_docente(): void
    {
        $reportId = $this->insertStudentReport($this->context);
        DB::table('examen')->update(['id_usuario_docente' => $this->createOtherTeacherId()]);

        $this->postJson("/api/docente/incidencias/estudiante-{$reportId}/aprobar")->assertStatus(403);
    }

    public function test_invitado_consulta_los_tipos_de_falta(): void
    {
        $this->getJson('/api/tipos-falta')->assertOk();
    }

    private function createOtherTeacherId(): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();

        DB::table('usuario')->insert([
            'id_usuario' => $id,
            'nombre' => 'Otro',
            'apellido_paterno' => 'Docente',
            'correo' => 'otro.docente@test.com',
            'contrasenia' => 'x',
            'cod_sis' => '10999',
            'estado' => 'ACTIVO',
        ]);
        $this->assignDocenteRole($id);

        return $id;
    }
}
