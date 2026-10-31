<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Intentos a exámenes ajenos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        .num { text-align: right; }
        .meta td { border: none; padding: 2px 8px 2px 0; }
    </style>
</head>
<body>
    <h1>Intentos de ingreso a exámenes ajenos</h1>

    <table class="meta">
        <tr><td>Desde:</td><td>{{ \Carbon\Carbon::parse($report['cabecera']['desde'])->format('d/m/Y') }}</td></tr>
        <tr><td>Hasta:</td><td>{{ \Carbon\Carbon::parse($report['cabecera']['hasta'])->format('d/m/Y') }}</td></tr>
        <tr><td>Facultad:</td><td>{{ $report['cabecera']['facultad'] ?? 'Todas' }}</td></tr>
        <tr><td>Reporte generado:</td><td>{{ \Carbon\Carbon::parse($report['cabecera']['generado_en'])->format('d/m/Y H:i') }}</td></tr>
    </table>

    <h2>Totales</h2>
    <table>
        <tr><td>Intentos en el rango</td><td class="num">{{ $report['meta']['total_intentos'] }}</td></tr>
        <tr><td>Estudiantes</td><td class="num">{{ $report['meta']['total_estudiantes'] }}</td></tr>
        <tr><td>Ya en la Central de Riesgo</td><td class="num">{{ $report['meta']['total_en_central_riesgo'] }}</td></tr>
    </table>

    <h2>Estudiantes</h2>
    @if (count($report['estudiantes']) === 0)
        <p>No hubo intentos de ingreso a exámenes ajenos en este rango.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Código SIS</th>
                    <th class="num">Intentos</th>
                    <th>Exámenes</th>
                    <th>Central de Riesgo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['estudiantes'] as $s)
                    <tr>
                        <td>{{ $s['nombre_completo'] }}</td>
                        <td>{{ $s['cod_sis'] }}</td>
                        <td class="num">{{ $s['intentos'] }}</td>
                        <td>{{ implode(' · ', $s['examenes']) }}</td>
                        <td>{{ $s['id_cr_estudiante'] !== null ? 'Sí (#' . $s['id_cr_estudiante'] . ')' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
