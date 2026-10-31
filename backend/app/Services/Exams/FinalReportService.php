<?php

namespace App\Services\Exams;

use App\Exceptions\Exams\FinalReportUnavailableException;
use App\Models\Exam;
use App\Models\StudentReport;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Arma los datos del reporte final de un examen finalizado (HU-051).
 *
 * La vista web y el PDF consumen este mismo resultado para que los totales coincidan.
 */
class FinalReportService
{
    private const ENTRY_LABELS = [
        'INGRESO' => 'A tiempo',
        'CON_RETRASO' => 'Con retraso',
        'NO_INGRESO' => 'Ausente',
    ];

    private const UNCLASSIFIED_LABEL = 'Sin clasificar';

    private const DECISION_LABELS = [
        ReportStatus::APPROVED => 'Aprobada',
        ReportStatus::REJECTED => 'Rechazada',
        ReportStatus::PENDING => 'Pendiente de revisión',
    ];

    public function build(Exam $exam, string $teacherId): array
    {
        $this->assertTeacherGiven($teacherId);
        $this->assertAvailable($exam, $teacherId);

        $exam->loadMissing(['subject', 'teacher']);
        $rows = $this->studentRows((int) $exam->id_examen);

        return [
            'cabecera' => $this->header($exam),
            'asistencia' => $this->attendanceByGroup($rows),
            'estudiantes' => $this->studentList($rows),
            'incidencias' => $this->incidents((int) $exam->id_examen),
        ];
    }

    private function assertTeacherGiven(string $teacherId): void
    {
        if ($teacherId === '') {
            throw new RuntimeException('No hay un docente autenticado que ejecute la operación.');
        }
    }

    /** El examen ajeno y el no finalizado responden igual para no revelar si existe. */
    private function assertAvailable(Exam $exam, string $teacherId): void
    {
        if ((string) $exam->id_usuario_docente !== $teacherId || $exam->estado !== Exam::FINALIZADO) {
            throw new FinalReportUnavailableException();
        }
    }

    private function header(Exam $exam): array
    {
        $teacher = $exam->teacher;
        $timezone = config('sciem.zona_horaria');

        return [
            'id_examen' => (int) $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'materia' => $exam->subject?->nombre,
            'docente' => $teacher ? trim("{$teacher->nombre} {$teacher->apellido_paterno}") : null,
            'fecha' => $exam->fecha->toDateString(),
            'hora_inicio' => $this->realOrScheduledTime($exam->inicio_real_en, $exam->hora_inicio, $timezone),
            'hora_fin' => $this->realOrScheduledTime($exam->fin_real_en, $exam->hora_fin, $timezone),
            'generado_en' => Carbon::now($timezone)->toIso8601String(),
        ];
    }

    /** Hora real en La Paz; si falta, la programada con su etiqueta para no confundirlas. */
    private function realOrScheduledTime(?Carbon $real, ?string $scheduled, string $timezone): ?string
    {
        if ($real !== null) {
            return $real->copy()->setTimezone($timezone)->format('H:i');
        }

        $time = $this->shortTime($scheduled);

        return $time === null ? null : $time . ' (programada)';
    }

    /** Todos los esperados están en examen_estudiante porque al finalizar se registra a los ausentes. */
    private function studentRows(int $examId): Collection
    {
        return DB::table('examen_estudiante as ee')
            ->join('estudiante as e', 'e.id_estudiante', '=', 'ee.id_estudiante')
            ->join('grupo as g', 'g.id_grupo', '=', 'ee.id_grupo')
            ->leftJoin('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->where('ee.id_examen', $examId)
            ->orderBy('e.apellido_paterno')
            ->orderBy('e.apellido_materno')
            ->orderBy('e.nombre')
            ->get([
                'ee.id_grupo',
                'g.num_grupo',
                'ee.estado_ingreso',
                'ee.hora_ingreso',
                'e.cod_sis',
                'e.nombre',
                'e.apellido_paterno',
                'e.apellido_materno',
                'a.nro_aula',
            ]);
    }

    private function attendanceByGroup(Collection $rows): array
    {
        $groups = $rows->groupBy('id_grupo')
            ->map(fn (Collection $group) => [
                'id_grupo' => (int) $group->first()->id_grupo,
                'num_grupo' => $group->first()->num_grupo,
                'inscritos' => $group->count(),
                'a_tiempo' => $group->where('estado_ingreso', 'INGRESO')->count(),
                'con_retraso' => $group->where('estado_ingreso', 'CON_RETRASO')->count(),
                'ausentes' => $group->where('estado_ingreso', 'NO_INGRESO')->count(),
                'sin_clasificar' => $group
                    ->whereNotIn('estado_ingreso', array_keys(self::ENTRY_LABELS))
                    ->count(),
            ])
            ->sortBy('num_grupo', SORT_NATURAL)
            ->values();

        return [
            'grupos' => $groups->all(),
            'totales' => [
                'inscritos' => $groups->sum('inscritos'),
                'a_tiempo' => $groups->sum('a_tiempo'),
                'con_retraso' => $groups->sum('con_retraso'),
                'ausentes' => $groups->sum('ausentes'),
                'sin_clasificar' => $groups->sum('sin_clasificar'),
            ],
        ];
    }

    private function studentList(Collection $rows): array
    {
        // «Expulsado» se agregará cuando exista su estado en el sistema (HU-042 y HU-057).
        return $rows->map(fn ($row) => [
            'cod_sis' => $row->cod_sis,
            'nombre' => trim("{$row->nombre} {$row->apellido_paterno} {$row->apellido_materno}"),
            'ambiente' => $row->nro_aula,
            'hora_ingreso' => $this->shortTime($row->hora_ingreso),
            'estado' => self::ENTRY_LABELS[$row->estado_ingreso] ?? self::UNCLASSIFIED_LABEL,
        ])->all();
    }

    private function incidents(int $examId): array
    {
        $timezone = config('sciem.zona_horaria');

        return StudentReport::query()
            ->where('id_examen', $examId)
            ->with(['student', 'incidentType'])
            ->orderBy('fecha_reporte')
            ->get()
            ->map(fn (StudentReport $report) => [
                'hora' => $report->fecha_reporte->copy()->setTimezone($timezone)->format('H:i'),
                'estudiante' => $report->student ? $report->student->nombre_completo : null,
                'tipo' => $report->incidentType ? $report->incidentType->nombre : null,
                'decision' => self::DECISION_LABELS[$report->estado] ?? $report->estado,
                'observacion' => $report->observacion_revision,
            ])
            ->all();
    }

    private function shortTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
