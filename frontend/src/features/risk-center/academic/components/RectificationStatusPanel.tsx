import { Clock, UserX } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { getRejectionEvidence } from '../services/riskRectificationService'
import type { RectificationSummary } from '../types/riskRectification.types'
import { formatDate, formatDayMonthTime } from '../utils/format'
import { rectifyModeOf } from '../utils/rectificationMode'
import { EvidenceLink } from './EvidenceLink'

/**
 * Lo que el Administrador debe saber antes de decidir: si hay una solicitud esperando al docente, cuántas veces
 * se rechazó o venció una solicitud (por separado), el último rechazo con su motivo y su imagen, y si el docente
 * que aprobó el reporte sigue activo.
 */
export function RectificationStatusPanel({ summary }: { summary: RectificationSummary }) {
  const mode = rectifyModeOf(summary)
  const { solicitud_pendiente: pending, ultimo_rechazo: lastRejection, docente } = summary
  const hasHistory = summary.rechazos > 0 || summary.vencidas > 0

  if (mode !== 'waiting' && mode !== 'blocked' && mode !== 'direct' && !hasHistory) return null

  return (
    <Card className="space-y-4 p-5" aria-label="Estado de la rectificación">
      {pending !== null && (
        <div role="status" className="flex items-start gap-2 text-sm">
          <Clock className="mt-0.5 size-4 shrink-0 text-warn-fg" aria-hidden="true" />
          <div className="space-y-0.5">
            <p className="font-semibold">Esperando confirmación del docente</p>
            <p>
              Solicitud enviada a {pending.docente.nombre_completo ?? 'el docente que aprobó el reporte'} el{' '}
              {formatDayMonthTime(pending.fecha_solicitud)}. Vence el {formatDayMonthTime(pending.vence_en)}. El
              registro sigue activo en la Central hasta que el docente la acepte.
            </p>
            <p className="whitespace-pre-line break-words text-muted-foreground">Motivo: {pending.motivo}</p>
          </div>
        </div>
      )}

      {!docente.activo && (
        <div className="flex items-start gap-2 text-sm">
          <UserX className="mt-0.5 size-4 shrink-0 text-warn-fg" aria-hidden="true" />
          <div className="space-y-0.5">
            <p className="font-semibold">
              {docente.nombre_completo ?? 'El docente que aprobó el reporte'} ya no es docente activo
            </p>
            {docente.puede_rectificar_sin_confirmacion ? (
              <p>
                Pasaron más de {summary.dias_docente_inactivo} días de inactividad: puede anular el registro sin su
                confirmación.
              </p>
            ) : docente.puede_rectificar_desde !== null ? (
              <p>
                No se le puede enviar la solicitud. Podrá anular el registro sin su confirmación desde el{' '}
                {formatDate(docente.puede_rectificar_desde)}.
              </p>
            ) : (
              <p>
                No se le puede enviar la solicitud. Al anular, se iniciará el plazo de {summary.dias_docente_inactivo}{' '}
                días de inactividad para poder anular sin su confirmación.
              </p>
            )}
          </div>
        </div>
      )}

      {hasHistory && (
        <dl className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-0.5">
            <dt className="sciem-overline text-muted-foreground">Rechazos</dt>
            <dd className="text-sm">
              <span className="sciem-tnum font-semibold">{summary.rechazos}</span>{' '}
              {summary.rechazos === 1 ? 'solicitud rechazada' : 'solicitudes rechazadas'}
            </dd>
          </div>
          <div className="space-y-0.5">
            <dt className="sciem-overline text-muted-foreground">Vencidas sin respuesta</dt>
            <dd className="text-sm">
              <span className="sciem-tnum font-semibold">{summary.vencidas}</span>{' '}
              {summary.vencidas === 1 ? 'solicitud vencida' : 'solicitudes vencidas'}
            </dd>
          </div>
        </dl>
      )}

      {lastRejection !== null && (
        <div className="space-y-2 rounded-md border border-border p-3 text-sm">
          <p className="sciem-overline text-muted-foreground">Último rechazo · {formatDate(lastRejection.fecha)}</p>
          <p className="whitespace-pre-line break-words">{lastRejection.motivo}</p>
          {lastRejection.evidencia !== null && (
            <EvidenceLink
              id={lastRejection.id_solicitud_rect}
              evidence={lastRejection.evidencia}
              fetcher={getRejectionEvidence}
              label="Descargar imagen del rechazo"
            />
          )}
        </div>
      )}
    </Card>
  )
}
