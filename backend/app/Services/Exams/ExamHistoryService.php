<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\LateEntryRequest;
use App\Models\StudentExam;
use App\Support\ReportStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $term = Str::ascii(mb_strtolower(trim($filters['buscar'])));
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $base->whereRaw(
                "translate(lower(nombre_examen), 'áéíóúüñ', 'aeiouun') LIKE ?",
                ['%' . $term . '%']
            );
        }

        $page = $base->with('subject:id_materia,nombre')
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->orderByDesc('id_examen')
            ->paginate(10);

        $ids = $page->getCollection()->pluck('id_examen')->all();
        $attendance = $this->attendanceFor($ids);

        return [
            'examenes' => $page->getCollection()->map(function (Exam $exam) use ($attendance) {
                $counts = $attendance[$exam->id_examen] ?? $this->emptyAttendance();
                return [
                    'id_examen' => $exam->id_examen,
                    'nombre_examen' => $exam->nombre_examen,
                    'fecha' => $exam->fecha->format('Y-m-d'),
                    'materia' => $exam->subject?->nombre,
                    'esperados' => $counts['esperados'],
                    'presentes' => $counts['presentes'],
                    'incidencias' => $counts['incidencias'],
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
        abort_unless($exam->id_usuario_docente === $teacherId, 403, 'No tiene acceso a este examen.');
        abort_unless($exam->estado === Exam::FINALIZADO, 404);

        $exam->load('subject:id_materia,nombre');
        $counts = $this->attendanceFor([$exam->id_examen])[$exam->id_examen]
            ?? $this->emptyAttendance();
        $reports = DB::table('reporte_estudiante as r')
            ->join('tipo_falta as f', 'f.id_falta', '=', 'r.id_tipo_falta')
            ->leftJoin('usuario as reportante', 'reportante.id_usuario', '=', 'r.id_usuario_reportante')
            ->leftJoin('central_riesgo as cr', 'cr.id_reporte_est', '=', 'r.id_reporte_est')
            ->where('r.id_examen', $exam->id_examen)
            ->select(['r.fecha_reporte', 'r.estado', 'f.nombre as falta', 'cr.rectificado_en'])
            ->selectRaw("TRIM(CONCAT_WS(' ', reportante.nombre, reportante.apellido_paterno, reportante.apellido_materno)) as reportante")
            ->orderBy('r.fecha_reporte')
            ->get();

        // Solo los ingresos con retraso (HU-037) entran a la línea de tiempo; los puntuales no se listan.
        $lateEntries = DB::table('examen_estudiante as ee')
            ->join('estudiante as s', 's.id_estudiante', '=', 'ee.id_estudiante')
            ->leftJoin('ambiente as a', 'a.id_ambiente', '=', 'ee.id_ambiente')
            ->leftJoin('solicitud_ingreso_retraso as sr', function ($join) {
                $join->on('sr.id_examen', '=', 'ee.id_examen')
                    ->on('sr.id_estudiante', '=', 'ee.id_estudiante')
                    ->where('sr.estado', '=', LateEntryRequest::AUTORIZADA);
            })
            ->leftJoin('usuario as resolutor', 'resolutor.id_usuario', '=', 'sr.id_usuario_resolutor')
            ->leftJoin('usuario as controlador', 'controlador.id_usuario', '=', 'ee.id_usuario_controlador')
            ->where('ee.id_examen', $exam->id_examen)
            ->where('ee.estado_ingreso', StudentExam::CON_RETRASO)
            ->whereNotNull('ee.hora_ingreso')
            ->select(['ee.hora_ingreso', 's.nombre', 's.apellido_paterno', 'a.nro_aula'])
            ->selectRaw('sr.id_solicitud is not null as por_solicitud')
            ->selectRaw("TRIM(CONCAT_WS(' ', resolutor.nombre, resolutor.apellido_paterno, resolutor.apellido_materno)) as resolutor")
            ->selectRaw("TRIM(CONCAT_WS(' ', controlador.nombre, controlador.apellido_paterno, controlador.apellido_materno)) as controlador")
            ->orderBy('ee.hora_ingreso')
            ->get();

        $transitions = DB::table('log as l')
            ->join('accion as ac', 'ac.id_accion', '=', 'l.id_accion')
            ->leftJoin('usuario as actor', 'actor.id_usuario', '=', 'l.id_usuario')
            ->where('l.tabla_afectada', 'examen')
            ->whereRaw("l.nuevo_valor->>'id_examen' = ?", [(string) $exam->id_examen])
            ->where(function ($query) {
                $query->whereRaw("l.nuevo_valor->>'evento' = ?", ['CIERRE_INGRESO_ORDINARIO'])
                    ->orWhere(function ($closing) {
                        $closing->where('ac.operacion', 'FINALIZAR')
                            ->whereRaw("l.nuevo_valor->>'estado' = ?", [Exam::FINALIZADO]);
                    });
            })
            ->select(['l.fecha_hora', 'ac.operacion', 'l.nuevo_valor'])
            ->selectRaw("TRIM(CONCAT_WS(' ', actor.nombre, actor.apellido_paterno, actor.apellido_materno)) as actor")
            ->orderBy('l.fecha_hora')
            ->get();

        $timeline = $transitions->map(function ($log) use ($exam) {
            $after = json_decode($log->nuevo_valor, true) ?? [];
            if (($after['evento'] ?? null) === 'CIERRE_INGRESO_ORDINARIO') {
                return [
                    'tipo' => 'cierre_ingreso',
                    'fecha_hora' => $exam->ingreso_ordinario_cerrado_en?->toIso8601String() ?? $log->fecha_hora,
                    'descripcion' => 'Cierre del ingreso ordinario',
                    'detalle' => $log->actor ?: 'Responsable no registrado',
                ];
            }

            $automatic = $exam->fin_real_en !== null
                ? $exam->fin_automatico
                : ($after['tipo_finalizacion'] ?? null) === 'AUTOMATICA';
            return [
                'tipo' => 'fin',
                'fecha_hora' => $exam->fin_real_en?->toIso8601String() ?? $log->fecha_hora,
                'descripcion' => 'Examen terminado',
                'detalle' => $automatic ? 'Automático' : ($log->actor ?: 'Responsable no registrado'),
            ];
        });
        if ($exam->ingreso_abierto_en !== null) {
            $timeline->push([
                'tipo' => 'apertura',
                'fecha_hora' => $exam->ingreso_abierto_en->toIso8601String(),
                'descripcion' => 'Apertura del control',
                'detalle' => 'Automático',
            ]);
        }
        $closingLogged = $transitions->contains(
            fn ($log) => (json_decode($log->nuevo_valor, true)['evento'] ?? null) === 'CIERRE_INGRESO_ORDINARIO'
        );
        if ($exam->ingreso_ordinario_cerrado_en !== null && ! $closingLogged) {
            $timeline->push([
                'tipo' => 'cierre_ingreso',
                'fecha_hora' => $exam->ingreso_ordinario_cerrado_en->toIso8601String(),
                'descripcion' => 'Cierre del ingreso ordinario',
                'detalle' => 'Al terminar el examen',
            ]);
        }
        if ($exam->fin_real_en !== null && ! $transitions->contains('operacion', 'FINALIZAR')) {
            $timeline->push([
                'tipo' => 'fin',
                'fecha_hora' => $exam->fin_real_en->toIso8601String(),
                'descripcion' => 'Examen terminado',
                'detalle' => $exam->fin_automatico ? 'Automático' : 'Responsable no registrado',
            ]);
        }
        $timeline = $timeline->concat($lateEntries->map(fn ($entry) => [
            'tipo' => 'retraso',
            'fecha_hora' => Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $exam->fecha->format('Y-m-d') . ' ' . $entry->hora_ingreso,
                config('sciem.zona_horaria')
            )->toIso8601String(),
            'descripcion' => 'Ingreso con retraso: ' . trim($entry->nombre . ' ' . $entry->apellido_paterno)
                . ($entry->nro_aula ? ' (' . $entry->nro_aula . ')' : ''),
            'detalle' => $entry->por_solicitud
                ? 'Autorizó: ' . ($entry->resolutor ?: 'Responsable no registrado')
                : 'Registró: ' . ($entry->controlador ?: 'Responsable no registrado'),
        ]))->concat($reports->map(fn ($report) => [
            'tipo' => 'incidencia',
            'fecha_hora' => $report->fecha_reporte,
            'descripcion' => 'Incidencia: ' . $report->falta,
            'detalle' => ($report->estado === ReportStatus::APPROVED
                ? ($report->rectificado_en !== null
                    ? 'Aprobada · retirada de la Central de Riesgo'
                    : 'Aprobada · en Central de Riesgo')
                : ucfirst(strtolower($report->estado)))
                . ($report->reportante ? ' · Reportó: ' . $report->reportante : ' · Reportante no registrado'),
        ]));

        $scheduledStart = Carbon::createFromFormat(
            'Y-m-d H:i',
            $exam->fecha->format('Y-m-d') . ' ' . substr($exam->hora_inicio, 0, 5),
            config('sciem.zona_horaria')
        );
        $timeline->push([
            'tipo' => 'inicio',
            'fecha_hora' => $scheduledStart->toIso8601String(),
            'descripcion' => 'Inicio programado del examen',
            'detalle' => 'Horario previsto',
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

        $controllers = DB::table('examen_estudiante as ee')
            ->join('usuario as u', 'u.id_usuario', '=', 'ee.id_usuario_controlador')
            ->where('ee.id_examen', $exam->id_examen)
            ->whereIn('ee.estado_ingreso', ['INGRESO', 'CON_RETRASO'])
            ->whereNotNull('ee.id_ambiente')
            ->distinct()
            ->get(['ee.id_ambiente', 'u.nombre', 'u.apellido_paterno'])
            ->groupBy('id_ambiente');

        return [
            'id_examen' => $exam->id_examen,
            'nombre_examen' => $exam->nombre_examen,
            'fecha' => $exam->fecha->format('Y-m-d'),
            'materia' => $exam->subject?->nombre,
            'total_estudiantes' => $counts['esperados'],
            'inicio_programado' => $scheduledStart->toIso8601String(),
            'ingreso_abierto_en' => $exam->ingreso_abierto_en?->toIso8601String(),
            'fin_real_en' => $exam->fin_real_en?->toIso8601String(),
            'fin_automatico' => (bool) $exam->fin_automatico,
            'totales' => [
                'a_tiempo' => $counts['a_tiempo'],
                'con_retraso' => $counts['con_retraso'],
                'ausentes' => $counts['ausentes'],
                'no_habilitados' => $counts['no_habilitados'],
                'sin_clasificar' => $counts['sin_clasificar'],
                'incidencias' => $counts['incidencias'],
            ],
            'linea_tiempo' => $timeline,
            'ambientes' => $rooms->map(fn ($room) => [
                'id_ambiente' => $room->id_ambiente,
                'nro_aula' => $room->nro_aula,
                'presentes' => (int) $room->presentes,
                'auxiliares' => ($controllers[$room->id_ambiente] ?? collect())
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

        $known = [StudentExam::INGRESO, StudentExam::CON_RETRASO, StudentExam::NO_INGRESO];
        $rows = DB::table('examen_estudiante')
            ->whereIn('id_examen', $examIds)
            ->selectRaw("id_examen, count(*) as esperados,
                count(*) filter (where estado_ingreso = ?) as a_tiempo,
                count(*) filter (where estado_ingreso = ?) as con_retraso,
                count(*) filter (where estado_ingreso = ? and coalesce(estado_habilitacion, 'HABILITADO') <> 'NO_HABILITADO') as ausentes,
                count(*) filter (where estado_ingreso = ? and estado_habilitacion = 'NO_HABILITADO') as no_habilitados,
                count(*) filter (where estado_ingreso is null or estado_ingreso not in (?, ?, ?)) as sin_clasificar", [
                StudentExam::INGRESO,
                StudentExam::CON_RETRASO,
                StudentExam::NO_INGRESO,
                StudentExam::NO_INGRESO,
                ...$known,
            ])
            ->groupBy('id_examen')
            ->get();

        $attendance = $rows->mapWithKeys(fn ($row) => [(int) $row->id_examen => [
            'esperados' => (int) $row->esperados,
            'a_tiempo' => (int) $row->a_tiempo,
            'con_retraso' => (int) $row->con_retraso,
            'ausentes' => (int) $row->ausentes,
            'no_habilitados' => (int) $row->no_habilitados,
            'sin_clasificar' => (int) $row->sin_clasificar,
            'presentes' => (int) $row->a_tiempo + (int) $row->con_retraso,
            'incidencias' => 0,
        ]])->all();

        // Solo para exámenes sin nómina congelada (datos anteriores a HU-021): se toman las inscripciones
        // actuales de sus grupos y nadie cuenta como presente. Con una sola fila en examen_estudiante no aplica.
        $withoutRoster = array_values(array_diff($examIds, array_keys($attendance)));
        if ($withoutRoster !== []) {
            $enrolled = DB::table('grupo_examen as gx')
                ->join('grupo_estudiante as gs', 'gs.id_grupo', '=', 'gx.id_grupo')
                ->whereIn('gx.id_examen', $withoutRoster)
                ->selectRaw('gx.id_examen, COUNT(DISTINCT gs.id_estudiante) as esperados')
                ->groupBy('gx.id_examen')
                ->pluck('esperados', 'id_examen');

            foreach ($enrolled as $examId => $count) {
                $attendance[(int) $examId] = ['esperados' => (int) $count, 'ausentes' => (int) $count]
                    + $this->emptyAttendance();
            }
        }

        // Las incidencias son un total aparte: no sacan al estudiante de su categoría de asistencia.
        $incidents = DB::table('reporte_estudiante')
            ->whereIn('id_examen', $examIds)
            ->selectRaw('id_examen, count(*) as total')
            ->groupBy('id_examen')
            ->pluck('total', 'id_examen');

        foreach ($incidents as $examId => $total) {
            $counts = $attendance[(int) $examId] ?? $this->emptyAttendance();
            $counts['incidencias'] = (int) $total;
            $attendance[(int) $examId] = $counts;
        }

        return $attendance;
    }

    private function emptyAttendance(): array
    {
        return [
            'esperados' => 0,
            'a_tiempo' => 0,
            'con_retraso' => 0,
            'ausentes' => 0,
            'no_habilitados' => 0,
            'sin_clasificar' => 0,
            'presentes' => 0,
            'incidencias' => 0,
        ];
    }
}
