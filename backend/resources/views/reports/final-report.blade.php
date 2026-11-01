<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte final</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        .num { text-align: right; }
        .meta td { border: none; padding: 2px 8px 2px 0; }
        .total td { font-weight: bold; }
    </style>
</head>
<body>
    @php($c = $report['cabecera'])

    <h1>Reporte final</h1>

    <table class="meta">
        <tr><td>Examen:</td><td>{{ $c['nombre_examen'] }}</td></tr>
        <tr><td>Materia:</td><td>{{ $c['materia'] ?? '—' }}</td></tr>
        <tr><td>Docente:</td><td>{{ $c['docente'] ?? '—' }}</td></tr>
        <tr><td>Fecha del examen:</td><td>{{ \Carbon\Carbon::parse($c['fecha'])->format('d/m/Y') }}</td></tr>
        <tr><td>Hora de inicio:</td><td>{{ $c['hora_inicio'] ?? '—' }}</td></tr>
        <tr><td>Hora de fin:</td><td>{{ $c['hora_fin'] ?? '—' }}</td></tr>
        <tr><td>Reporte generado:</td><td>{{ \Carbon\Carbon::parse($c['generado_en'])->format('d/m/Y H:i') }}</td></tr>
    </table>

    @php($showUnclassified = $report['asistencia']['totales']['sin_clasificar'] > 0)

    <h2>Asistencia por grupo</h2>
    <table>
        <thead>
            <tr>
                <th>Grupo</th>
                <th class="num">Inscritos</th>
                <th class="num">A tiempo</th>
                <th class="num">Con retraso</th>
                <th class="num">Ausentes</th>
                @if ($showUnclassified)
                    <th class="num">Sin clasificar</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($report['asistencia']['grupos'] as $group)
                <tr>
                    <td>{{ $group['num_grupo'] }}</td>
                    <td class="num">{{ $group['inscritos'] }}</td>
                    <td class="num">{{ $group['a_tiempo'] }}</td>
                    <td class="num">{{ $group['con_retraso'] }}</td>
                    <td class="num">{{ $group['ausentes'] }}</td>
                    @if ($showUnclassified)
                        <td class="num">{{ $group['sin_clasificar'] }}</td>
                    @endif
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="num">{{ $report['asistencia']['totales']['inscritos'] }}</td>
                <td class="num">{{ $report['asistencia']['totales']['a_tiempo'] }}</td>
                <td class="num">{{ $report['asistencia']['totales']['con_retraso'] }}</td>
                <td class="num">{{ $report['asistencia']['totales']['ausentes'] }}</td>
                @if ($showUnclassified)
                    <td class="num">{{ $report['asistencia']['totales']['sin_clasificar'] }}</td>
                @endif
            </tr>
        </tbody>
    </table>

    <h2>Estudiantes</h2>
    <table>
        <thead>
            <tr>
                <th>Código SIS</th>
                <th>Nombre</th>
                <th>Aula</th>
                <th>Hora de ingreso</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['estudiantes'] as $student)
                <tr>
                    <td>{{ $student['cod_sis'] }}</td>
                    <td>{{ $student['nombre'] }}</td>
                    <td>{{ $student['ambiente'] ?? '—' }}</td>
                    <td>{{ $student['hora_ingreso'] ?? '—' }}</td>
                    <td>{{ $student['estado'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Incidencias</h2>
    @if (count($report['incidencias']) === 0)
        <p>No se registraron incidencias en este examen</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Estudiante</th>
                    <th>Tipo</th>
                    <th>Decisión del docente</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['incidencias'] as $incident)
                    <tr>
                        <td>{{ $incident['hora'] }}</td>
                        <td>{{ $incident['estudiante'] ?? '—' }}</td>
                        <td>{{ $incident['tipo'] ?? '—' }}</td>
                        <td>{{ $incident['decision'] }}@if ($incident['observacion']): {{ $incident['observacion'] }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
