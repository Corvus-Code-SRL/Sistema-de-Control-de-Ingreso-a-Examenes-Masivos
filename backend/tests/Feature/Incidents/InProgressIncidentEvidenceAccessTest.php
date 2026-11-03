<?php

namespace Tests\Feature\Incidents;

use App\Models\User;
use Database\Seeders\IncidentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

/**
 * La evidencia de una incidencia reportada durante el examen queda en el disco privado y solo se entrega
 * por el endpoint autenticado de revisión al docente responsable.
 */
class InProgressIncidentEvidenceAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
        $this->seed(IncidentTypeSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function reportWithEvidence(): int
    {
        $response = $this->postJson(
            str_replace('/situaciones', '/incidencias', $this->situationUrl()),
            [
                'id_tipo_falta' => DB::table('tipo_falta')->where('nombre', 'Material no permitido')->value('id_falta'),
                'descripcion' => 'Usó un celular durante el examen sin autorización.',
                'id_ambiente' => $this->aulaId,
                'retirar' => false,
                'evidencia' => UploadedFile::fake()->image('prueba.png'),
            ]
        )->assertCreated();

        return (int) $response->json('data.id_reporte_est');
    }

    public function testEvidenceIsStoredPrivatelyAndServedOnlyToTheResponsibleTeacher(): void
    {
        $reportId = $this->reportWithEvidence();
        $path = DB::table('reporte_estudiante')->where('id_reporte_est', $reportId)->value('evidencia_public_id');
        $url = "/api/docente/incidencias/estudiante-{$reportId}/evidencia";

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $this->get($url)->assertOk();

        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->getJson($url)->assertForbidden();

        $this->actAsGuest();
        $this->getJson($url)->assertUnauthorized();
    }
}
