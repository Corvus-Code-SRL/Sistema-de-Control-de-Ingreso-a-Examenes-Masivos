<?php

namespace Tests\Feature\Incidents;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

class ExamSituationConstraintsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedExamSituations();
    }

    /** @dataProvider invalidFacts */
    public function testChecksRejectInvalidFactsEvenWithoutHttpValidation(array $overrides): void
    {
        try {
            DB::transaction(function () use ($overrides): void {
                $this->createSituationFixture($overrides);
            });
            $this->fail('La base debía rechazar el hecho inválido.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', (string) $exception->getCode());
        }

        $this->assertSame(0, DB::table('situacion_examen')->count());
    }

    public static function invalidFacts(): array
    {
        return [
            [['tipo' => 'EXPULSION']],
            [['tipo' => 'CAMBIO_AMBIENTE', 'id_ambiente_destino' => null]],
            [['motivo' => '   ']],
            [['motivo' => 'Duda']],
        ];
    }

    public function testRoomChangeCannotUseTheSameOriginAndDestination(): void
    {
        try {
            DB::transaction(function (): void {
                $this->createSituationFixture([
                    'tipo' => 'CAMBIO_AMBIENTE',
                    'id_ambiente_destino' => $this->aulaId,
                    'motivo' => 'Ruido',
                ]);
            });
            $this->fail('La base debía rechazar origen igual a destino.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', (string) $exception->getCode());
        }
    }

    public function testDestinationMustBelongToTheExam(): void
    {
        try {
            DB::transaction(function (): void {
                $this->createSituationFixture([
                    'tipo' => 'CAMBIO_AMBIENTE',
                    'id_ambiente_destino' => $this->aulaInactivaId,
                    'motivo' => 'Ruido',
                ]);
            });
            $this->fail('La clave compuesta debía rechazar un aula ajena.');
        } catch (QueryException $exception) {
            $this->assertSame('23503', (string) $exception->getCode());
        }
    }
}