import { useState } from 'react'
import { ArrowLeft, Check, FileImage, FileX, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { LoadingState } from '@/components/common/LoadingState'
import { ErrorState } from '@/components/common/ErrorState'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { formatFileSize } from '@/lib/format'
import { ApproveIncidentModal } from './ApproveIncidentModal'
import { RejectIncidentModal } from './RejectIncidentModal'
import { IncidentStateBadge } from './IncidentStateBadge'
import { approveIncident, getIncident, rejectIncident } from '../services/incidentsService'
import { useIncidentEvidenceDownload } from '../hooks/useIncidentEvidenceDownload'
import type { IncidentPerson, IncidentReview } from '../types/incident.types'

interface IncidentDetailPanelProps {
  incidentId: string
  onReviewed: (message: { title: string; description: string }) => void
  onBack?: () => void
}

export function IncidentDetailPanel({ incidentId, onReviewed, onBack }: IncidentDetailPanelProps) {
  const resource = useAsyncResource(
    (signal) => getIncident(incidentId, signal),
    [incidentId]
  )

  const [approveOpen, setApproveOpen] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)

  if (resource.status === 'loading') {
    return (
      <Card className="p-0">
        <LoadingState rows={4} label="Cargando incidencia" />
      </Card>
    )
  }

  if (resource.status === 'error' && resource.error) {
    return (
      <Card className="p-0">
        <ErrorState error={resource.error} onRetry={resource.reload} />
      </Card>
    )
  }

  if (!resource.data) return null

  const incident: IncidentReview = resource.data.data
  const isPending = incident.estado === 'PENDIENTE'

async function handleApprove(observacion: string | null) {
  await approveIncident(incident.id, observacion)
  setApproveOpen(false)
  onReviewed({
    title: 'Incidencia aprobada',
    description: `${incident.persona.nombre_completo} quedó registrado en la Central de Riesgo.`,
  })
}

async function handleReject(observacion: string) {
  await rejectIncident(incident.id, observacion)
  setRejectOpen(false)
  onReviewed({
    title: 'Incidencia rechazada',
    description: 'El reporte no entró a la Central de Riesgo.',
  })
}

  return (
    <>
      <Card className="p-0">
        <div className="flex flex-col gap-5 p-5">
          {onBack && (
            <Button variant="ghost" size="sm" onClick={onBack} className="self-start">
              <ArrowLeft className="size-4" />
              Volver
            </Button>
          )}

          {/* Cabecera */}
          <div className="flex items-start gap-4">
            <AvatarPersona persona={incident.persona} />
            <div className="min-w-0 flex-1">
              <p className="sciem-h2 truncate">{incident.persona.nombre_completo}</p>
              <p className="text-sm text-muted-foreground">
                <span className="sciem-tnum font-semibold">{incident.persona.codigo}</span>
                {' · '}
                {incident.examen.nombre_examen}
                {incident.examen.materia && ` · ${incident.examen.materia}`}
              </p>
            </div>
            <IncidentStateBadge estado={incident.estado} />
          </div>

          {/* Datos clave */}
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
            <dt className="text-muted-foreground">Tipo de falta</dt>
            <dd className="font-medium">{incident.tipo_falta.nombre}</dd>

            {incident.intento?.motivo_legible && (
              <>
                <dt className="text-muted-foreground">Motivo del rechazo en la puerta</dt>
                <dd className="font-medium">{incident.intento.motivo_legible}</dd>
              </>
            )}

            {incident.ambiente && (
              <>
                <dt className="text-muted-foreground">Ambiente y hora</dt>
                <dd className="font-medium">
                  Aula {incident.ambiente.nro_aula}
                  {incident.intento?.fecha_intento && ` · ${formatDateTime(incident.intento.fecha_intento)}`}
                </dd>
              </>
            )}

            <dt className="text-muted-foreground">Reportó</dt>
            <dd className="font-medium">
              {incident.reportante.nombre_completo} ({incident.reportante.rol})
            </dd>
          </dl>

          {/* Descripción */}
          {incident.descripcion && (
            <div className="rounded-lg border border-border bg-sunken px-3.5 py-3">
              <p className="sciem-overline mb-1 text-xs text-muted-foreground">Descripción del reporte</p>
              <p className="text-sm">{incident.descripcion}</p>
            </div>
          )}

          {/* Evidencia */}
          {incident.evidencia ? (
            <EvidenceBlock incident={incident} />
          ) : (
            <p className="text-sm text-muted-foreground">Sin evidencia adjunta.</p>
          )}

          {/* Bloque de decisión (revisada) */}
          {!isPending && incident.revisor && (
            <div
              className={
                incident.estado === 'APROBADO'
                  ? 'rounded-lg border border-ok-border bg-ok-soft p-4'
                  : 'rounded-lg border border-border bg-neutral-soft p-4'
              }
            >
              <p className="sciem-overline mb-2 text-xs">Decisión · definitiva</p>
              <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                <dt className="text-muted-foreground">Decisión</dt>
                <dd className="font-medium">
                  {incident.estado === 'APROBADO'
                    ? 'Aprobado · en Central de Riesgo'
                    : 'Rechazado · no entra a la Central de Riesgo'}
                </dd>
                <dt className="text-muted-foreground">Revisor</dt>
                <dd className="font-medium">{incident.revisor.nombre_completo}</dd>
                <dt className="text-muted-foreground">Fecha</dt>
                <dd className="font-medium">{formatDateTime(incident.revisor.fecha_revision)}</dd>
                {incident.revisor.observacion_revision && (
                  <>
                    <dt className="text-muted-foreground">Observación</dt>
                    <dd className="font-medium">{incident.revisor.observacion_revision}</dd>
                  </>
                )}
              </dl>
            </div>
          )}

          {/* Acciones (pendiente) */}
          {isPending && (
            <>
              <Separator />
              <div className="flex gap-2.5">
                <Button
                  variant="outline"
                  className="flex-1 border-danger-border text-danger-fg hover:bg-danger-soft"
                  onClick={() => setRejectOpen(true)}
                >
                  <X className="size-4" />
                  Rechazar
                </Button>
                <Button className="flex-1" onClick={() => setApproveOpen(true)}>
                  <Check className="size-4" />
                  Aprobar
                </Button>
              </div>
            </>
          )}
        </div>
      </Card>

      <ApproveIncidentModal
        open={approveOpen}
        onOpenChange={setApproveOpen}
        incident={incident}
        onConfirm={handleApprove}
      />
      <RejectIncidentModal
        open={rejectOpen}
        onOpenChange={setRejectOpen}
        incident={incident}
        onConfirm={handleReject}
      />
    </>
  )
}

/* ===================================================================== */

function EvidenceBlock({ incident }: { incident: IncidentReview }) {
  const ev = incident.evidencia!
  const { open, status, message } = useIncidentEvidenceDownload(
    incident.id,
    ev.url,
    ev.nombre
  )

  if (!ev.disponible) {
    return (
      <div className="flex flex-col gap-2">
        <p className="sciem-label">Evidencia</p>
        <p className="flex items-start gap-2 text-sm text-muted-foreground">
          <FileX className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <span>
            {ev.nombre}
            <span className="block text-xs">El archivo de la evidencia no está disponible.</span>
          </span>
        </p>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-2">
      <p className="sciem-label">Evidencia</p>
      <button
        type="button"
        onClick={open}
        disabled={status === 'loading'}
        className="flex items-center gap-3 rounded-lg border border-border px-3.5 py-3 text-left hover:bg-sunken disabled:opacity-60"
      >
        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
          <FileImage className="size-4" aria-hidden="true" />
        </span>
        <div className="flex-1 min-w-0">
          <p className="truncate text-sm font-medium">{ev.nombre}</p>
          <p className="text-xs text-muted-foreground">
            {ev.tamano_bytes !== null && `${formatFileSize(ev.tamano_bytes)} · `}
            {ev.tipo} · {status === 'loading' ? 'Abriendo…' : 'ver en tamaño completo'}
          </p>
        </div>
      </button>
      {status === 'error' && message && (
        <p role="alert" className="text-xs text-danger-fg">
          {message}
        </p>
      )}
    </div>
  )
}

function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

function formatDateTime(iso: string): string {
  const d = new Date(iso)
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${day}/${month}/${year} ${hours}:${minutes}`
}

function AvatarPersona({ persona }: { persona: IncidentPerson }) {
  const [error, setError] = useState(false)

  if (!persona.foto_url || error) {
    return (
      <div className="flex size-16 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-2xl font-bold text-brand-deep">
        {getInitials(persona.nombre_completo)}
      </div>
    )
  }

  return (
    <img
      src={persona.foto_url}
      alt={persona.nombre_completo}
      className="size-16 shrink-0 rounded-lg object-cover"
      onError={() => setError(true)}
    />
  )
}
