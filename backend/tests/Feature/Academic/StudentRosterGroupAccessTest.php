<?php

namespace Tests\Feature\Academic;

use App\Exceptions\Academic\StudentRosterGroupAccessException;
use App\Models\Group;
use App\Services\Academic\StudentRosterGroupAccess;
use App\Support\RecordStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class StudentRosterGroupAccessTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    public function test_devuelve_grupo_propio_activo_del_periodo_actual(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $result = $this->access()->getAvailable(
            (int) $group->id_grupo
        );

        $this->assertSame(
            $group->id_grupo,
            $result->id_grupo
        );
    }

    public function test_falla_si_el_grupo_no_existe(): void
    {
        $this->seedAcademicCatalog();

        $this->expectException(
            ModelNotFoundException::class
        );

        $this->access()->getAvailable(999999);
    }

    public function test_rechaza_grupo_de_otro_docente(): void
    {
        $this->seedAcademicCatalog();

        $group = Group::query()
            ->where(
                'id_usuario_docente',
                $this->otroDocenteId
            )
            ->where(
                'id_periodo',
                $this->periodoActivoId
            )
            ->firstOrFail();

        $this->assertRejectedWith(
            403,
            fn () => $this->access()->getAvailable(
                (int) $group->id_grupo
            )
        );
    }

    public function test_rechaza_grupo_inactivo(): void
    {
        $this->seedAcademicCatalog();

        $group = $this->ownActiveGroup();

        $group->estado = RecordStatus::INACTIVE;
        $group->save();

        $this->assertRejectedWith(
            422,
            fn () => $this->access()->getAvailable(
                (int) $group->id_grupo
            )
        );
    }

    public function test_rechaza_grupo_de_otro_periodo(): void
    {
        $this->seedAcademicCatalog();

        $group = Group::query()
            ->where(
                'id_usuario_docente',
                $this->docenteId
            )
            ->where(
                'id_periodo',
                $this->periodoAnteriorId
            )
            ->firstOrFail();

        $this->assertRejectedWith(
            422,
            fn () => $this->access()->getAvailable(
                (int) $group->id_grupo
            )
        );
    }

    private function access(): StudentRosterGroupAccess
    {
        return new StudentRosterGroupAccess();
    }

    private function ownActiveGroup(): Group
    {
        return Group::query()
            ->where(
                'id_usuario_docente',
                $this->docenteId
            )
            ->where(
                'id_periodo',
                $this->periodoActivoId
            )
            ->where(
                'estado',
                RecordStatus::ACTIVE
            )
            ->firstOrFail();
    }

    private function assertRejectedWith(
        int $statusCode,
        callable $operation
    ): void {
        try {
            $operation();

            $this->fail(
                'La operación debía rechazar el grupo.'
            );
        } catch (
            StudentRosterGroupAccessException $exception
        ) {
            $this->assertSame(
                $statusCode,
                $exception->getStatusCode()
            );
        }
    }
}