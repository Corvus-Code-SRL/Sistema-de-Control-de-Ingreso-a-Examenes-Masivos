import { useState, type ReactNode } from 'react'
import { AlertTriangle, Check, X } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { useRespondRectificationRequest } from '../hooks/useRespondRectificationRequest'
import { getRequestRejectionEvidence, getRequestReportEvidence } from '../services/rectificationRequestsService'
import type { RectificationRequestCard } from '../types/riskRectification.types'
import { formatDate, formatDayMonthTime, formatPlainDate, formatTime } from '../utils/format'
import { timeLeftUntil } from '../utils/rectificationMode'
import { EvidenceLink } from './EvidenceLink'
import { FaultTypeBadge } from './FaultTypeBadge'
import { RejectRequestForm } from './RejectRequestForm'
import { RespondConfirmDialog, type PendingResponse } from './RespondConfirmDialog'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="space-y-0.5">
      <dt className="sciem-overline text-muted-foreground">{label}</dt>
      <dd className="text-sm text-foreground">{children}</dd>
    </div>
  )
}

const STATE_LABELS: Record<Exclude<RectificationRequestCard['estado'], 'PENDIENTE'>, string> = {
  ACEPTADA: 'Aceptada',
  RECHAZADA: 'Rechazada',
  VENCIDA: 'Vencida',
}

interface RectificationRequestItemProps {
  request: RectificationRequestCard
  /** La solicitud ya quedó respondida: la bandeja se recarga y avisa. */
  onResponded: (message: string) => void
}

/**
 * Una solicitud de rectificación. Las pendientes se pueden aceptar o rechazar, siempre en dos pasos y con
 * «Cancelar» en cada uno; las respondidas y vencidas son de solo lectura.
 */
export function RectificationRequestItem({ request, onResponded }: RectificationRequestItemProps) {
  const respond = useRespondRectificationRequest()
  const [rejecting, setRejecting] = useState(false)
  const [pending, setPending] = useState<PendingResponse | null>(null)

  const answerable = request.estado === 'PENDIENTE'
  const timeLeft = answerable ? timeLeftUntil(request.vence_en) : null

  async function handleConfirm() {
    if (pending === null) return

    const result =
      pending.kind === 'accept' ? await respond.accept(request.id_solicitud_rect) : await respond.reject(request.id_solicitud_rect, pending.draft)

    setPending(null)

    if (result === null) return

    onResponded(
      pending.kind === 'accept'
        ? `Aceptó la solicitud: el registro de ${request.estudiante.nombre_completo} ya no figura en la Central de Riesgo.`
        : `Rechazó la solicitud: el registro de ${request.estudiante.nombre_completo} sigue en la Central de Riesgo.`
    )
  }

  return (
    <Card className="space-y-4 p-5" aria-label={`Solicitud de rectificación de ${request.estudiante.nombre_completo}`}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="sciem-h3">{request.estudiante.nombre_completo}</h3>
          <p className="text-sm text-muted-foreground">
            Código SIS <span className="sciem-tnum">{request.estudiante.cod_sis}</span>
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <FaultTypeBadge name={request.tipo_falta.nombre} />
          {answerable ? (
            <Badge variant="outline" className="border-warn-border bg-warn-soft text-warn-fg">
              {timeLeft === null ? 'Vence pronto' : `Vence en ${timeLeft}`}
            </Badge>
          ) : (
            <Badge variant="outline">{STATE_LABELS[request.estado as keyof typeof STATE_LABELS]}</Badge>
          )}
        </div>
      </div>

      {answerable && request.rechazos_previos > 0 && (
        <Alert role="note">
          <AlertTriangle className="size-4" aria-hidden="true" />
          <AlertDescription>
            Este registro ya tuvo {request.rechazos_previos}{' '}
            {request.rechazos_previos === 1 ? 'solicitud de rectificación rechazada' : 'solicitudes de rectificación rechazadas'}.
          </AlertDescription>
        </Alert>
      )}

      <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
        <Field label="Examen">{request.examen.nombre}</Field>
        <Field label="Materia">{request.examen.materia ?? 'Sin materia'}</Field>
        <Field label="Fecha del examen">{formatPlainDate(request.examen.fecha)}</Field>
        <Field label="Fecha y hora del hecho">
          {formatDate(request.hecho.fecha_hora)} · {formatTime(request.hecho.fecha_hora)}
        </Field>
        <Field label="Solicita">
          {request.administrador.nombre_completo} · {formatDayMonthTime(request.fecha_solicitud)}
        </Field>
        <Field label="Vence">{formatDayMonthTime(request.vence_en)}</Field>
      </dl>

      <dl className="space-y-4">
        <Field label="Descripción del reporte">
          <span className="whitespace-pre-line break-words">
            {request.descripcion ?? <span className="text-muted-foreground">Sin descripción</span>}
          </span>
        </Field>
        <Field label="Evidencia del reporte">
          {request.evidencia === null ? (
            <span className="text-muted-foreground">Sin evidencia</span>
          ) : (
            <EvidenceLink
              id={request.id_solicitud_rect}
              evidence={request.evidencia}
              fetcher={getRequestReportEvidence}
              label="Descargar evidencia del reporte"
            />
          )}
        </Field>
        <Field label="Motivo de la solicitud">
          <span className="whitespace-pre-line break-words">{request.motivo_solicitud}</span>
        </Field>
      </dl>

      {request.respuesta !== null && (
        <div className="space-y-2 rounded-md border border-border p-3 text-sm">
          <p className="sciem-overline text-muted-foreground">
            Su rechazo{request.fecha_respuesta !== null && ` · ${formatDate(request.fecha_respuesta)}`}
          </p>
          <p className="whitespace-pre-line break-words">{request.respuesta.motivo}</p>
          {request.respuesta.evidencia !== null && (
            <EvidenceLink
              id={request.id_solicitud_rect}
              evidence={request.respuesta.evidencia}
              fetcher={getRequestRejectionEvidence}
              label="Descargar imagen del rechazo"
            />
          )}
        </div>
      )}

      {request.estado === 'ACEPTADA' && request.fecha_respuesta !== null && (
        <p className="text-sm text-muted-foreground">
          Aceptó la solicitud el {formatDate(request.fecha_respuesta)}: el registro ya no figura en la Central.
        </p>
      )}

      {request.estado === 'VENCIDA' && (
        <p className="text-sm text-muted-foreground">
          La solicitud venció sin respuesta: el registro sigue en la Central y el administrador puede enviar otra.
        </p>
      )}

      {respond.error !== null && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{respond.error.message}</AlertDescription>
        </Alert>
      )}

      {answerable && !rejecting && (
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button
            type="button"
            variant="outline"
            size="lg"
            onClick={() => {
              respond.clearError()
              setRejecting(true)
            }}
          >
            <X aria-hidden="true" />
            Rechazar
          </Button>
          <Button
            type="button"
            variant="success"
            size="lg"
            onClick={() => {
              respond.clearError()
              setPending({ kind: 'accept' })
            }}
          >
            <Check aria-hidden="true" />
            Aceptar
          </Button>
        </div>
      )}

      {answerable && rejecting && (
        <RejectRequestForm
          requestId={request.id_solicitud_rect}
          onReview={(draft) => setPending({ kind: 'reject', draft })}
          onCancel={() => setRejecting(false)}
        />
      )}

      <RespondConfirmDialog
        request={request}
        response={pending}
        isSubmitting={respond.isSubmitting}
        onConfirm={handleConfirm}
        onCancel={() => setPending(null)}
      />
    </Card>
  )
}
