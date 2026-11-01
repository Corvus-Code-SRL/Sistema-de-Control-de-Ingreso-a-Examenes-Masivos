<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Consultas de solo lectura del historial de exámenes de un docente. */
class ExamHistoryService
{
    public function index(string $teacherId, array $filters): array
    {
        $base = Exam::query()
            ->where('id_usuario_docente', $teacherId)
            ->where('estado', Exam::FINALIZADO);

        $subjects = DB::table('materia as m')
            ->join('examen as e', 'e.id_materia', '=', 'm.id_materia')
            ->where('e.id_usuario_docente', $teacherId)
            ->where('e.estado', Exam::FINALIZADO)
            ->distinct()
            ->orderBy('m.nombre')
            ->get(['m.id_materia', 'm.nombre']);

        if (isset($filters['materia'])) {
            $base->where('id_materia', (int) $filters['materia']);
        }
        if (! empty($filters['buscar'])) {
            $term = str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], trim($filters['buscar']));
            $base->where('nombre_examen', 'ilike', '%' . $term . '%');
        }

        $page = $base->with('subject:id_materia,nombre')
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->orderByDesc('id_examen')
            ->paginate(10);

        $ids = $page->getCollection()->pluck('id_examen')->all();
        $attendance = $this->attendanceFor($ids);
        $incidents = DB::table('reporte_estudiante')
            ->whereIn('id_examen', $ids)
            ->selectRaw('id_examen, count(*) as total')
            ->groupBy('id_examen')
            ->pluck('total', 'id_examen');

        return [
            'examenes' => $page->getCollection()->map(function (Exam $exam) use ($attendance, $incidents) {
                $counts = $attendance[$exam->id_examen] ?? $this->emptyAttendance();
                return [
                    'id_examen' => $exam->id_examen,
                    'nombre_examen' => $exam->nombre_examen,
                    'fecha' => $exam->fecha->format('Y-m-d'),
                    'materia' => $exam->subject?->nombre,
                    'esperados' => $counts['esperados'],
                    'presentes' => $counts['a_tiempo'] + $counts['con_retraso'],
                    'incidencias' => (int) ($incidents[$exam->id_examen] ?? 0),
                ];
            })->values()->all(),
            'materias' => $subjects->all(),
            'pagina' => $page->currentPage(),
            'paginas' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }

    public function show(Exam $exam, string $teacherId): array
    {
        abort_unless(
            $exam->id_usuario_docente === $teacherId && $exam->estado === Exam::FINALIZADO,
            404
        );

        $exam->load('subject:id_materia,nombre');
        $counts = $this->attendanceFor([$exam->id_examen])[$exam->id_examen]
            ?? $this->emptyAttendance();
        $reports = DB::table('reporte_estudiante as r')
            ->join('tipo_falta as f', 'f.id_falta', '=', 'r.id_tipo_falta')
            ->leftJoin('usuario as reportante', 'reportante.id_usuario', '=', 'r.id_usuario_reportante')
            ->where('r.id_examen', $exam->id_examen)
            ->select(['r.fecha_reporte', 'r.estado', 'f.nombre as falta'])
            ->selectRaw("TRIM(CONCAT_WS(' ', reportante.nombre, reportante.apellido_paterno, reportante.apellido_materno)) as reportante")
            ->orderBy('r.fecha_reporte')
            ->get();

        $entries = DB::table('examen_estudiante as ee')
            ->join('estudiante as s', 's.id_estudiante', '=', 'ee.id_estudiante')
            ->leftJoin('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->leftJoin('usuario as controlador', 'controlador.id_usuario', '=', 'ee.id_usuario_controlador')
            ->where('ee.id_examen', $exam->id_examen)
            ->whereIn('ee.estado_ingreso', ['INGRESO', 'CON_RETRASO'])
            ->whereNotNull('ee.hora_ingreso')
            ->select(['ee.hora_ingreso', 'ee.estado_ingreso', 's.nombre', 's.apellido_paterno', 'a.nro_aula'])
            ->selectRaw("TRIM(CONCAT_WS(' ', controlador.nombre, controlador.apellido_paterno, controlador.apellido_materno)) as controlador")
            ->orderBy('ee.hora_ingreso')
            ->get();

        $transitions = DB::table('log as l')
            ->join('accion as ac', 'ac.id_accion', '=', 'l.id_accion')
            ->where('l.tabla_afectada', 'examen')
            ->whereRaw("l.nuevo_valor->>'id_examen' = ?", [(string) $exam->id_examen])
            ->where(function ($query) {
                $query->whereRaw("l.nuevo_valor->>'evento' = ?", ['APERTURA_CONTROL'])
                    ->orWhere(function ($closing) {
                        $closing->where('ac.operacion', 'FINALIZAR')
                            ->whereRaw("l.nuevo_valor->>'estado' = ?", [Exam::FINALIZADO]);
                    });
            })
            ->select(['l.fecha_hora', 'ac.operacion', 'l.nuevo_valor'])
            ->orderBy('l.fecha_hora')
            ->get();

        $timeline = $transitions->map(function ($log) use ($exam) {
            $after = json_decode($log->nuevo_valor, true) ?? [];
            $opening = ($after['evento'] ?? null) === 'APERTURA_CONTROL';
            $automatic = $exam->fin_real_en !== null
                ? $exam->fin_automatico
                : ($after['tipo_finalizacion'] ?? null) === 'AUTOMATICA';
            return [
                'tipo' => $opening ? 'apertura' : 'fin',
                'fecha_hora' => $opening
                    ? $log->fecha_hora
                    : ($exam->fin_real_en?->toIso8601String() ?? $log->fecha_hora),
                'descripcion' => $opening ? 'Se abrió el control de ingreso' : 'Examen terminado',
                'detalle' => $opening || $automatic ? 'Automático' : 'Finalización manual',
            ];
        });
        if ($exam->fin_real_en !== null && ! $transitions->contains('operacion', 'FINALIZAR')) {
            $timeline->push([
                'tipo' => 'fin',
                'fecha_hora' => $exam->fin_real_en->toIso8601String(),
                'descripcion' => 'Examen terminado',
                'detalle' => $exam->fin_automatico ? 'Automático' : 'Finalización manual',
            ]);
        }
        $timeline = $timeline->concat($entries->map(fn ($entry) => [
            'tipo' => $entry->estado_ingreso === 'CON_RETRASO' ? 'retraso' : 'ingreso',
            'fecha_hora' => Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $exam->fecha->format('Y-m-d') . ' ' . $entry->hora_ingreso,
                config('sciem.zona_horaria')
            )->toIso8601String(),
            'descripcion' => $entry->estado_ingreso === 'CON_RETRASO'
                ? 'Ingreso con retraso' : 'Ingreso registrado',
            'detalle' => trim($entry->nombre . ' ' . $entry->apellido_paterno)
                . ($entry->nro_aula ? ' · ' . $entry->nro_aula : '')
                . ($entry->controlador ? ' · Controló: ' . $entry->controlador : ' · Controlador no registrado'),
        ]))->concat($reports->map(fn ($report) => [
            'tipo' => 'incidencia',
            'fecha_hora' => $report->fecha_reporte,
            'descripcion' => 'Incidencia: ' . $report->falta,
            'detalle' => ($report->estado === ReportStatus::APPROVED
                ? 'Aprobada · en Central de Riesgo' : ucfirst(strtolower($report->estado)))
                . ($report->reportante ? ' · Reportó: ' . $report->reportante : ' · Reportante no registrado'),
        ]));

        $startedAt = $exam->inicio_real_en ?? Carbon::createFromFormat(
            'Y-m-d H:i',
            $exam->fecha->format('Y-m-d') . ' ' . substr($exam->hora_inicio, 0, 5),
            config('sciem.zona_horaria')
        );
        $hasActualStart = $exam->inicio_real_en !== null;
        $timeline->push([
            'tipo' => 'inicio',
            'fecha_hora' => $startedAt->toIso8601String(),
            'descripcion' => $hasActualStart ? 'Examen iniciado' : 'Inicio programado del examen',
            'detalle' => $hasActualStart
                ? ($exam->inicio_automatico ? 'Inicio automático' : 'Inicio manual')
                : 'Horario previsto',
        ]);
        $timeline = $timeline
            ->sortBy(fn (array $event) => Carbon::parse($event['fecha_hora'])->getTimestamp())
            ->map(function (array $event) {
                $event['fecha_hora'] = Carbon::parse($event['fecha_hora'])
                    ->setTimezone(config('sciem.zona_horaria'))
                    ->format('Y-m-d H:i:s');
                return $event;
            })->values()->all();

        $rooms = DB::table('examen_ambiente as ea')
            ->join('ambiente as a', 'a.id_ambiente', '=', 'ea.id_ambiente')
            ->leftJoin('examen_estudiante as ee', function ($join) {
                $join->on('ee.id_ambiente', '=', 'ea.id_ambiente')
                    ->on('ee.id_examen', '=', 'ea.id_examen')
                    ->whereIn('ee.estado_ingreso', ['INGRESO', 'CON_RETRASO']);
            })
            ->where('ea.id_examen', $exam->id_examen)
            ->selectRaw('a.id_ambiente, a.nro_aula, count(ee.id_estudiante) as presentes')
            ->groupBy('a.id_ambiente', 'a.nro_aula')
            ->orderBy('a.nro_aula')
            ->get();

        $assistants = DB::table('examen_auxiliar as exa')
            ->join('usuario as u', 'u.id_usuario', '=', 'exa.id_usuario')
            ->where('exa.id_examen', $exam->id_examen)
            ->whereNotNull('exa.id_ambiente')
            ->get(['exa.id_ambiente', 'u.nombre', 'u.apellido_paterno'])
            ->groupBy('id_ambiente');

        return [
            'id_examen' => $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'fecha' => $exam->fecha->format('Y-m-d'),
            'materia' => $exam->subject?->nombre,
            'inicio_real_en' => $exam->inicio_real_en?->toIso8601String(),
            'inicio_automatico' => (bool) $exam->inicio_automatico,
            'fin_real_en' => $exam->fin_real_en?->toIso8601String(),
            'fin_automatico' => (bool) $exam->fin_automatico,
            'totales' => [
                'a_tiempo' => $counts['a_tiempo'],
                'con_retraso' => $counts['con_retraso'],
                'ausentes' => $counts['ausentes'],
                'incidencias' => $reports->count(),
            ],
            'linea_tiempo' => $timeline,
            'ambientes' => $rooms->map(fn ($room) => [
                'id_ambiente' => $room->id_ambiente,
                'nro_aula' => $room->nro_aula,
                'presentes' => (int) $room->presentes,
                'auxiliares' => ($assistants[$room->id_ambiente] ?? collect())
                    ->map(fn ($assistant) => trim($assistant->nombre . ' ' . $assistant->apellido_paterno))
                    ->values()->all(),
            ])->all(),
        ];
    }

    private function attendanceFor(array $examIds): array
    {
        if ($examIds === []) {
            return [];
        }

        $rows = DB::table('examen_estudiante')
            ->whereIn('id_examen', $examIds)
            ->selectRaw("id_examen, count(*) as esperados,
                count(*) filter (where estado_ingreso = 'INGRESO') as a_tiempo,
                count(*) filter (where estado_ingreso = 'CON_RETRASO') as con_retraso,
                count(*) filter (where estado_ingreso is null or estado_ingreso not in ('INGRESO', 'CON_RETRASO')) as ausentes")
            ->groupBy('id_examen')
            ->get();

        $attendance = $rows->mapWithKeys(fn ($row) => [$row->id_examen => [
            'esperados' => (int) $row->esperados,
            'a_tiempo' => (int) $row->a_tiempo,
            'con_retraso' => (int) $row->con_retraso,
            'ausentes' => (int) $row->ausentes,
        ]])->all();

        // Un cierre normal congela la nómina y deja una auditoría. Si solo se cambió
        // el estado, las filas de los alumnos que no ingresaron pueden faltar.
        $closedIds = DB::table('log as l')
            ->join('accion as ac', 'ac.id_accion', '=', 'l.id_accion')
            ->where('l.tabla_afectada', 'examen')
            ->where('ac.operacion', 'FINALIZAR')
            ->whereRaw("l.nuevo_valor->>'estado' = ?", [Exam::FINALIZADO])
            ->whereIn(DB::raw("l.nuevo_valor->>'id_examen'"), array_map('strval', $examIds))
            ->selectRaw("l.nuevo_valor->>'id_examen' as id_examen")
            ->get()
            ->pluck('id_examen')
            ->map(fn ($id) => (int) $id)
            ->all();
        $fallbackIds = array_diff($examIds, $closedIds);

        if ($fallbackIds !== []) {
            $rosterCounts = DB::table('grupo_examen as gx')
                ->join('grupo_estudiante as gs', 'gs.id_grupo', '=', 'gx.id_grupo')
                ->whereIn('gx.id_examen', $fallbackIds)
                ->selectRaw('gx.id_examen, COUNT(DISTINCT gs.id_estudiante) as esperados')
                ->groupBy('gx.id_examen')
                ->pluck('esperados', 'id_examen');

            foreach ($fallbackIds as $examId) {
                $counts = $attendance[$examId] ?? $this->emptyAttendance();
                $missing = max(0, (int) ($rosterCounts[$examId] ?? 0) - $counts['esperados']);
                $counts['esperados'] += $missing;
                $counts['ausentes'] += $missing;
                $attendance[$examId] = $counts;
            }
        }

        return $attendance;
    }

    private function emptyAttendance(): array
    {
        return ['esperados' => 0, 'a_tiempo' => 0, 'con_retraso' => 0, 'ausentes' => 0];
    }
}
