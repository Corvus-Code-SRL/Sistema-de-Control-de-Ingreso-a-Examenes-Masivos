<?php

namespace Tests\Unit\Exams;

use App\Models\Exam;
use App\Services\Exams\ExamEligibleListAccess;
use App\Services\Exams\ExamPreparationWindow;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PHPUnit\Framework\TestCase;

class ExamPreparationWindowTest extends TestCase
{
    /** @dataProvider windowCases */
    public function test_hu028_delega_la_misma_ventana_sin_cambiar_su_comportamiento(
        string $status,
        ?string $openedAt,
        bool $expected
    ): void {
        $exam = new Exam();
        // No se requiere aplicación ni conexión para evaluar atributos de una instancia no persistida.
        $exam->setRawAttributes(['estado' => $status, 'ingreso_abierto_en' => $openedAt]);
        $window = new ExamPreparationWindow();

        $this->assertSame($expected, $window->isOpen($exam));
        $this->assertSame($expected, (new ExamEligibleListAccess($window))->isWritable($exam));
    }

    /** @return array<string, array{0:string, 1:?string, 2:bool}> */
    public function windowCases(): array
    {
        return [
            'programado sin apertura' => [Exam::PROGRAMADO, null, true],
            'programado con apertura' => [Exam::PROGRAMADO, '2026-11-18 10:00:00+00', false],
            'en curso' => [Exam::EN_CURSO, null, false],
            'finalizado' => [Exam::FINALIZADO, null, false],
            'cancelado' => [Exam::CANCELADO, null, false],
        ];
    }

    public function test_la_consulta_de_hu028_conserva_sus_filtros_y_la_ventana(): void
    {
        $connection = new Connection(null);
        $builder = new Builder(new QueryBuilder($connection));
        $builder->setModel(new Exam());
        $builder->where('id_usuario_docente', 'docente');
        $access = new ExamEligibleListAccess(new ExamPreparationWindow());

        $this->assertSame($builder, $access->applyWritableWindow($builder));
        $this->assertSame(
            'select * from "examen" where "id_usuario_docente" = ? and "estado" = ? '
                . 'and "ingreso_abierto_en" is null',
            $builder->toSql()
        );
        $this->assertSame(['docente', Exam::PROGRAMADO], $builder->getBindings());
    }
}
