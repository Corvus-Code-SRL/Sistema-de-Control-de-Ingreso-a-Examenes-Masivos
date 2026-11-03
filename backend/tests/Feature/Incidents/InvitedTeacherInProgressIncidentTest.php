<?php

namespace Tests\Feature\Incidents;

use App\Models\Role;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

/**
 * El docente invitado es el responsable de sus exámenes: reporta incidencias durante el examen en
 * curso igual que un Docente, porque el acceso se decide por función y no por nombre de rol.
 */
class InvitedTeacherInProgressIncidentTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;
    use AssignsIncidentRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
        $this->seed(IncidentTypeSeeder::class);
        Storage::fake('local');

        DB::table('usuario_rol')->where('id_usuario', $this->docenteId)->whereNull('fecha_fin')
            ->update(['fecha_fin' => now()]);
        DB::table('usuario')->where('id_usuario', $this->docenteId)->update(['cod_sis' => 'INV4821']);
        $this->assignRole($this->docenteId, Role::INVITADO);
        $this->actAsUserId($this->docenteId);
    }

    private function url(): string
    {
        return str_replace('/situaciones', '/incidencias', $this->situationUrl());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'id_tipo_falta' => DB::table('tipo_falta')->where('nombre', 'Material no permitido')->value('id_falta'),
            'descripcion' => 'Usó un celular durante el examen sin autorización.',
            'id_ambiente' => $this->aulaId,
            'retirar' => false,
        ], $overrides);
    }

    public function testInvitedOwnerReportsAnIncident(): void
    {
        $this->postJson($this->url(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.origen', 'EN_CURSO')
            ->assertJsonPath('data.estado', 'PENDIENTE');

        $this->assertDatabaseHas('reporte_estudiante', [
            'id_usuario_reportante' => $this->docenteId,
            'origen' => 'EN_CURSO',
        ]);
    }

    public function testInvitedOwnerCanWithdrawTheStudent(): void
    {
        $this->postJson($this->url(), $this->payload(['retirar' => true]))
            ->assertCreated()
            ->assertJsonPath('data.retiro_examen', true);

        $this->assertNotNull(DB::table('examen_estudiante')
            ->where('id_examen', $this->situationExam->id_examen)
            ->where('id_estudiante', $this->situationStudent->id_estudiante)
            ->value('expulsado_en'));
    }

    public function testInvitedTeacherCannotReportInAnotherTeachersExam(): void
    {
        $this->situationExam->forceFill(['id_usuario_docente' => $this->otroDocenteId])->save();

        $this->postJson($this->url(), $this->payload())->assertForbidden();
        $this->assertSame(0, DB::table('reporte_estudiante')->where('origen', 'EN_CURSO')->count());
    }
}
