<?php

namespace App\Services\Exams\Distribution;

use App\Models\RoomDistribution;
use App\Models\StudentExam;
use App\Services\Exams\ExamRosterService;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Nómina única y ordenada, capacidades actuales y filas planificadas; no usa la lista editable de HU-028.
 *
 * @phpstan-type DistributionStudent array{id_estudiante:int, apellido_paterno:string,
 *     apellido_materno:?string, nombre:string, cod_sis:string}
 * @phpstan-type DistributionRoom array{id_ambiente:int, nro_aula:string, capacidad:int}
 * @phpstan-type SavedAssignment array{id_examen:int, id_estudiante:int, id_ambiente:int,
 *     id_usuario_asignador:string, asignado_en:string}
 */
class DistributionRosterQuery
{
    private const ELIGIBLE_STATES = [StudentExam::HABILITADO, StudentExam::CASO_ESPECIAL];
    private const INELIGIBLE_STATE = 'NO_HABILITADO';

    public function __construct(private ExamRosterService $roster, private SurnameDistributor $distributor)
    {
    }

    /** @return list<DistributionStudent> */
    public function eligibleStudents(int $examId): array
    {
        $rows = DB::query()
            ->fromSub($this->roster->expected($examId), 'nomina')
            ->whereIn('nomina.estado_habilitacion', self::ELIGIBLE_STATES)
            ->get()->keyBy('id_estudiante')->values()
            ->map(fn (stdClass $row): array => [
                'id_estudiante' => (int) $row->id_estudiante,
                'apellido_paterno' => (string) $row->apellido_paterno,
                'apellido_materno' => $row->apellido_materno,
                'nombre' => (string) $row->nombre,
                'cod_sis' => (string) $row->cod_sis,
            ])->all();

        return $this->distributor->orderedStudents($rows);
    }

    public function ineligibleCount(int $examId): int
    {
        return (int) DB::query()->fromSub($this->roster->expected($examId), 'nomina')
            ->where('nomina.estado_habilitacion', self::INELIGIBLE_STATE)
            ->distinct()->count('nomina.id_estudiante');
    }

    /** @return list<DistributionRoom> */
    public function rooms(int $examId): array
    {
        return DB::table('ambiente')->join('examen_ambiente as ea', 'ea.id_ambiente', '=', 'ambiente.id_ambiente')
            ->where('ea.id_examen', $examId)->orderBy('ambiente.id_ambiente')
            ->get(['ambiente.id_ambiente', 'ambiente.nro_aula', 'ambiente.capacidad'])
            ->map(fn (stdClass $row): array => [
                'id_ambiente' => (int) $row->id_ambiente,
                'nro_aula' => (string) $row->nro_aula,
                'capacidad' => (int) $row->capacidad,
            ])->all();
    }

    /**
     * @param list<int> $examIds
     * @return list<int>
     */
    public function examsWithDistribution(array $examIds): array
    {
        return RoomDistribution::query()->whereIn('id_examen', $examIds)->distinct()
            ->pluck('id_examen')->map(fn ($examId): int => (int) $examId)->all();
    }

    /** @return list<SavedAssignment> */
    public function savedRows(int $examId, bool $lock = false): array
    {
        $query = RoomDistribution::query()->where('id_examen', $examId)->orderBy('id_estudiante')->toBase();

        if ($lock) {
            $query->lockForUpdate();
        }

        // Se conservan las fechas crudas de PostgreSQL, incluidos sus microsegundos.
        return $query->get()->map(fn (stdClass $row): array => (array) $row)->all();
    }

    /**
     * Solo para guardar: bloquea fuentes no agregadas antes de releer la nómina.
     * El examen ya está bloqueado. HU-024 toma ambientes antes que grupos al crear otro examen.
     */
    public function lockForSave(int $examId): void
    {
        $roomIds = DB::table('examen_ambiente')->where('id_examen', $examId)->pluck('id_ambiente')->all();
        DB::table('ambiente')->whereIn('id_ambiente', $roomIds)->orderBy('id_ambiente')->sharedLock()->get();
        DB::table('examen_ambiente')->where('id_examen', $examId)
            ->orderBy('id_ambiente')->lockForUpdate()->get();
        $groupIds = DB::table('grupo_examen')->where('id_examen', $examId)->pluck('id_grupo')->all();
        DB::table('grupo')->whereIn('id_grupo', $groupIds)->orderBy('id_grupo')->lockForUpdate()->get();
        DB::table('grupo_estudiante')->whereIn('id_grupo', $groupIds)
            ->orderBy('id_grupo')->orderBy('id_estudiante')->lockForUpdate()->get();
        DB::table('examen_estudiante')->where('id_examen', $examId)
            ->orderBy('id_estudiante')->lockForUpdate()->get();
    }
}
