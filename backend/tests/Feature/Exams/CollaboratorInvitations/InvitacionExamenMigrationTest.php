<?php

namespace Tests\Feature\Exams\CollaboratorInvitations;

use App\Models\ExamInvitation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\AssignsIncidentRole;
use Tests\Concerns\SeedsCollaboratorInvitations;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

/**
 * HU-026: las dos migraciones de invitacion_examen son aditivas, se pueden repetir y la segunda no se deja
 * revertir si perdería historial.
 */
class InvitacionExamenMigrationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;
    use AssignsIncidentRole;
    use SeedsCollaboratorInvitations;

    private ?\App\Models\Exam $exam = null;

    protected function setUp(): void
    {
        parent::setUp();

        require_once database_path('migrations/2026_11_16_000000_add_sin_efecto_to_estado_invitacion.php');
        require_once database_path('migrations/2026_11_16_000001_prepare_invitacion_examen_for_collaborators.php');

        $this->seedExamCatalog();
        $this->exam = $this->createExam();
    }

    private function columnMigration(): \PrepareInvitacionExamenForCollaborators
    {
        return new \PrepareInvitacionExamenForCollaborators();
    }

    private function row(string $state, string $invited, array $overrides = []): array
    {
        return array_merge([
            'id_examen' => $this->exam->id_examen,
            'id_docente_creador' => $this->docenteId,
            'id_docente_invitado' => $invited,
            'fecha_invitacion' => '2026-11-01 10:00:00+00',
            'estado' => $state,
        ], $overrides);
    }

    private function assertRejected(callable $statement, string $constraint): void
    {
        try {
            DB::transaction($statement);
            $this->fail("La base debía rechazar la fila ({$constraint}).");
        } catch (QueryException $exception) {
            $this->assertStringContainsString($constraint, $exception->getMessage());
        }
    }

    public function test_el_enum_admite_sin_efecto_y_las_columnas_tienen_su_valor_por_defecto(): void
    {
        $labels = collect(DB::select(
            "select enumlabel from pg_enum e join pg_type t on t.oid = e.enumtypid
             where t.typname = 'estado_invitacion' order by e.enumsortorder"
        ))->pluck('enumlabel')->all();

        $this->assertSame(['PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'SIN_EFECTO'], $labels);
        $this->assertTrue(Schema::hasColumns('invitacion_examen', ['permite_agregar_grupos', 'fecha_sin_efecto']));

        DB::table('invitacion_examen')->insert($this->row('PENDIENTE', $this->otroDocenteId));
        $stored = DB::table('invitacion_examen')->first();

        $this->assertTrue($stored->permite_agregar_grupos);
        $this->assertNull($stored->fecha_sin_efecto);
    }

    public function test_el_check_de_estado_exige_las_fechas_de_cada_estado(): void
    {
        $guest = $this->otroDocenteId;
        $check = 'chk_invitacion_examen_estado';

        $this->assertRejected(fn () => DB::table('invitacion_examen')->insert(
            $this->row('PENDIENTE', $guest, ['fecha_respuesta' => '2026-11-02 10:00:00+00'])
        ), $check);
        $this->assertRejected(fn () => DB::table('invitacion_examen')->insert(
            $this->row('PENDIENTE', $guest, ['fecha_sin_efecto' => '2026-11-02 10:00:00+00'])
        ), $check);
        $this->assertRejected(fn () => DB::table('invitacion_examen')->insert(
            $this->row('ACEPTADA', $guest)
        ), $check);
        $this->assertRejected(fn () => DB::table('invitacion_examen')->insert(
            $this->row('SIN_EFECTO', $guest)
        ), $check);
        $this->assertRejected(fn () => DB::table('invitacion_examen')->insert(
            $this->row('SIN_EFECTO', $guest, [
                'fecha_sin_efecto' => '2026-11-02 10:00:00+00',
                'fecha_respuesta' => '2026-11-02 10:00:00+00',
            ])
        ), $check);

        DB::table('invitacion_examen')->insert($this->row('SIN_EFECTO', $guest, [
            'fecha_sin_efecto' => '2026-11-02 10:00:00+00',
        ]));
        $this->assertSame(1, DB::table('invitacion_examen')->count());
    }

    public function test_un_docente_no_se_invita_a_si_mismo(): void
    {
        $this->assertRejected(
            fn () => DB::table('invitacion_examen')->insert($this->row('PENDIENTE', $this->docenteId)),
            'chk_invitacion_examen_no_autoinvitacion'
        );
    }

    public function test_el_indice_parcial_solo_cubre_pendientes_y_aceptadas(): void
    {
        $guest = $this->otroDocenteId;

        DB::table('invitacion_examen')->insert($this->row('RECHAZADA', $guest, [
            'fecha_respuesta' => '2026-11-02 10:00:00+00',
        ]));
        DB::table('invitacion_examen')->insert($this->row('SIN_EFECTO', $guest, [
            'fecha_sin_efecto' => '2026-11-02 10:00:00+00',
        ]));
        DB::table('invitacion_examen')->insert($this->row('PENDIENTE', $guest));

        $this->assertRejected(
            fn () => DB::table('invitacion_examen')->insert($this->row('PENDIENTE', $guest)),
            'uq_invitacion_examen_vigente'
        );
        $this->assertSame(3, DB::table('invitacion_examen')->count());
    }

    public function test_registra_la_accion_de_bitacora_una_sola_vez(): void
    {
        $this->assertSame(1, DB::table('accion')->where('operacion', 'INVITAR_COLABORADOR')->count());
        $this->assertSame(
            'INSERT',
            DB::table('accion')->where('operacion', 'INVITAR_COLABORADOR')->value('tipo_operacion')
        );
    }

    public function test_repetir_las_migraciones_no_cambia_nada(): void
    {
        $constraints = fn () => DB::table('pg_constraint')
            ->where('conrelid', DB::raw("'public.invitacion_examen'::regclass"))
            ->orderBy('conname')
            ->pluck('conname')
            ->all();
        $before = $constraints();

        (new \AddSinEfectoToEstadoInvitacion())->up();
        $this->columnMigration()->up();
        $this->columnMigration()->up();

        $this->assertSame($before, $constraints());
        $this->assertSame(1, DB::table('accion')->where('operacion', 'INVITAR_COLABORADOR')->count());
        $this->assertSame(
            1,
            DB::table('pg_indexes')->where('indexname', 'uq_invitacion_examen_vigente')->count()
        );
    }

    public function test_la_migracion_de_columnas_se_aplica_sobre_el_check_original(): void
    {
        $migration = $this->columnMigration();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('invitacion_examen', 'permite_agregar_grupos'));
        $this->assertSame(0, DB::table('accion')->where('operacion', 'INVITAR_COLABORADOR')->count());

        $migration->up();

        $this->assertTrue(Schema::hasColumns('invitacion_examen', ['permite_agregar_grupos', 'fecha_sin_efecto']));
        $this->assertSame(1, DB::table('accion')->where('operacion', 'INVITAR_COLABORADOR')->count());
        DB::table('invitacion_examen')->insert($this->row('SIN_EFECTO', $this->otroDocenteId, [
            'fecha_sin_efecto' => '2026-11-02 10:00:00+00',
        ]));
    }

    public function test_down_aborta_sin_tocar_nada_si_hay_invitaciones(): void
    {
        $this->createInvitation($this->exam, $this->otroDocenteId);

        try {
            $this->columnMigration()->down();
            $this->fail('down() debía abortar con invitaciones guardadas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('No se modificó nada', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('invitacion_examen', 'permite_agregar_grupos'));
        $this->assertSame(1, ExamInvitation::query()->count());
    }

    public function test_down_aborta_si_hay_filas_de_bitacora_de_la_accion(): void
    {
        DB::table('log')->insert([
            'id_accion' => DB::table('accion')->where('operacion', 'INVITAR_COLABORADOR')->value('id_accion'),
            'tabla_afectada' => 'invitacion_examen',
            'id_usuario' => $this->docenteId,
        ]);

        $this->expectException(RuntimeException::class);
        $this->columnMigration()->down();
    }
}
