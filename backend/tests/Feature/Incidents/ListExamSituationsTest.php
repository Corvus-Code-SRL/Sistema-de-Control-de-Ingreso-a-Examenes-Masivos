<?php

namespace Tests\Feature\Incidents;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

class ListExamSituationsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
    }

    public function testEmptyListUsesTheFrontendContract(): void
    {
        $this->getJson($this->situationListUrl())->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.total', 0);
    }

    public function testListsNewestFirstAndUsesIdToBreakTimestampTies(): void
    {
        $oldest = $this->createSituationFixture(['registrado_en' => '2026-10-27 12:10:00+00:00']);
        $second = $this->createSituationFixture();
        $latest = $this->createSituationFixture();

        $this->getJson($this->situationListUrl())->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id_situacion', (int) $latest->id_situacion)
            ->assertJsonPath('data.1.id_situacion', (int) $second->id_situacion)
            ->assertJsonPath('data.2.id_situacion', (int) $oldest->id_situacion)
            ->assertJsonPath('data.0.estudiante.id_estudiante', (int) $this->situationStudent->id_estudiante)
            ->assertJsonPath('data.0.registrante.id_usuario', $this->docenteId)
            ->assertJsonPath('data.0.ambiente_origen.id_ambiente', $this->aulaId)
            ->assertJsonPath('data.0.ambiente_destino', null)
            ->assertJsonStructure(['data' => [['tipo', 'registrado_en', 'registrante' => ['nombre_completo']]]]);
    }

    public function testPaginatesTwentyRows(): void
    {
        for ($index = 0; $index < 21; $index++) {
            $this->createSituationFixture();
        }

        $this->getJson($this->situationListUrl())->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2);
        $this->getJson($this->situationListUrl() . '?page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function testTeacherFilterIncludesRoomAsOriginOrDestination(): void
    {
        $this->createSituationFixture();
        $moved = $this->createSituationFixture([
            'tipo' => 'CAMBIO_AMBIENTE',
            'id_ambiente_destino' => $this->otraAulaId,
            'motivo' => 'Ruido',
        ]);

        $this->getJson($this->situationListUrl() . '?id_ambiente=' . $this->otraAulaId)
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_situacion', (int) $moved->id_situacion);
    }

    public function testAssistantListIsScopedEvenWithoutAnExplicitFilter(): void
    {
        $this->createSituationFixture();
        $own = $this->createSituationFixture(['id_ambiente_origen' => $this->otraAulaId]);
        $this->actAsSituationAssistant($this->otraAulaId);

        $this->getJson($this->situationListUrl())->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_situacion', (int) $own->id_situacion);
        $this->getJson($this->situationListUrl() . '?id_ambiente=' . $this->aulaId)->assertForbidden();
    }

    public function testTeacherCannotFilterARoomOutsideExam(): void
    {
        $this->getJson($this->situationListUrl() . '?id_ambiente=' . $this->aulaInactivaId)->assertForbidden();
    }

    public function testOtherTeacherCannotReadHistory(): void
    {
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->getJson($this->situationListUrl())->assertForbidden();
    }

    public function testGuestCannotReadHistory(): void
    {
        $this->actAsGuest();
        $this->getJson($this->situationListUrl())->assertUnauthorized();
    }

    public function testFinishedExamRemainsReadableButCannotBeWritten(): void
    {
        $this->createSituationFixture();
        $this->situationExam->update(['estado' => Exam::FINALIZADO]);
        $this->getJson($this->situationListUrl())->assertOk()->assertJsonCount(1, 'data');
        $this->postJson($this->situationUrl(), $this->situationPayload())->assertStatus(409);
        $this->assertSame(1, DB::table('situacion_examen')->count());
    }

    public function testRejectsInvalidPageAndRoomIdentifiers(): void
    {
        $this->getJson($this->situationListUrl() . '?page=0&id_ambiente=abc')
            ->assertStatus(422)->assertJsonValidationErrors(['page', 'id_ambiente']);
    }
}