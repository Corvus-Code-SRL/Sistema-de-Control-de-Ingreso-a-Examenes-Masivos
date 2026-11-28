<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de bitácora</title>

    <style>
        @page {
            margin: 28px 32px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }

        h1 {
            font-size: 19px;
            margin: 0 0 5px;
        }

        h2 {
            font-size: 13px;
            margin: 16px 0 6px;
            border-bottom: 1px solid #999;
            padding-bottom: 3px;
        }

        .institution {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #555;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #bbb;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #eee;
            font-weight: bold;
        }

        .meta td {
            border: none;
            padding: 3px 6px 3px 0;
        }

        .meta .label {
            width: 135px;
            font-weight: bold;
        }

        .num {
            text-align: right;
        }

        .muted {
            color: #555;
            font-size: 9px;
        }

        .totals {
            width: 65%;
        }

        .records {
            table-layout: fixed;
        }

        .records thead {
            display: table-header-group;
        }

        .records tr {
            page-break-inside: avoid;
        }

        .records tbody tr:nth-child(even) {
            background: #f7f7f7;
        }

        .footer-note {
            margin-top: 14px;
            font-size: 9px;
            color: #555;
        }
    </style>
</head>

<body>

    @php
        $filters = $report['filtros'];
        $header = $report['cabecera'];
        $totals = $report['totales'];
        $timezone = config('sciem.zona_horaria', 'America/La_Paz');
    @endphp

    <div class="institution">SCIEM</div>

    <h1>Reporte de bitácora</h1>

    <div class="subtitle">
        Sistema de Control de Ingreso a Exámenes Masivos
    </div>

    <table class="meta">
        <tr>
            <td class="label">Desde:</td>
            <td>
                {{ \Carbon\Carbon::parse($filters['desde'])->format('d/m/Y') }}
            </td>
        </tr>

        <tr>
            <td class="label">Hasta:</td>
            <td>
                {{ \Carbon\Carbon::parse($filters['hasta'])->format('d/m/Y') }}
            </td>
        </tr>

        <tr>
            <td class="label">Generado por:</td>
            <td>{{ $header['generado_por'] }}</td>
        </tr>

        <tr>
            <td class="label">Fecha de generación:</td>
            <td>
                {{ \Carbon\Carbon::parse($header['generado_en'])->setTimezone($timezone)->format('d/m/Y H:i') }}
                (La Paz)
            </td>
        </tr>
    </table>

    <h2>Filtros aplicados</h2>

    <table class="meta">
        <tr>
            <td class="label">Usuario:</td>
            <td>{{ $filters['q'] ?? 'Todos los usuarios' }}</td>
        </tr>

        <tr>
            <td class="label">Acción:</td>
            <td>{{ $header['accion'] }}</td>
        </tr>

        <tr>
            <td class="label">Módulo:</td>
            <td>{{ $header['modulo'] }}</td>
        </tr>
    </table>

    <h2>Resumen de operaciones</h2>

    <table class="totals">
        <tr>
            <th>Total de operaciones</th>
            <td class="num">{{ $totals['total_operaciones'] }}</td>
        </tr>

        <tr>
            <th>Usuarios distintos</th>
            <td class="num">{{ $totals['total_usuarios'] }}</td>
        </tr>
    </table>

    <h2>Totales por acción</h2>

    <table class="totals">
        <thead>
            <tr>
                <th>Acción</th>
                <th class="num">Cantidad</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($totals['por_accion'] as $action)
                <tr>
                    <td>{{ $action['etiqueta'] }}</td>
                    <td class="num">{{ $action['total'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No se registraron operaciones.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de operaciones</h2>

    <table class="records">
        <thead>
            <tr>
                <th style="width: 17%;">Fecha y hora</th>
                <th style="width: 23%;">Usuario / Código SIS</th>
                <th style="width: 19%;">Acción</th>
                <th style="width: 16%;">Módulo</th>
                <th style="width: 25%;">Registro afectado</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($report['registros'] as $entry)
                <tr>
                    <td>
                        {{ \Carbon\Carbon::parse($entry['fecha_hora'])->setTimezone($timezone)->format('d/m/Y H:i') }}
                    </td>

                    <td>
                        {{ $entry['usuario']['etiqueta'] }}

                        @if (!empty($entry['usuario']['cod_sis']))
                            <div class="muted">
                                SIS: {{ $entry['usuario']['cod_sis'] }}
                            </div>
                        @endif
                    </td>

                    <td>{{ $entry['accion']['etiqueta'] }}</td>

                    <td>{{ $entry['modulo']['etiqueta'] }}</td>

                    <td>{{ $entry['registro']['descripcion'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        No se encontraron operaciones para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-note">
        Reporte generado por SCIEM.
        Incluye todos los registros correspondientes a los filtros aplicados.
    </div>

</body>
</html>
