<?php

namespace App\Http\Requests\Incidents;

/**
 * Reporte de un intento rechazado en el ingreso con retraso (HU-037). Tiene las mismas reglas,
 * mensajes y roles que el reporte del control de ingreso (HU-032); cambia solo la ruta.
 */
class StoreLateEntryIncidentReportRequest extends StoreIncidentReportRequest
{
}
