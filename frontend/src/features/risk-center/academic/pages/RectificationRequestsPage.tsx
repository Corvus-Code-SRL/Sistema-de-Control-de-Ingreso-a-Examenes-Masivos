import { useState } from 'react'
import { CheckCircle2, Inbox } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Card } from '@/components/ui/card'
import { RectificationRequestItem } from '../components/RectificationRequestItem'
import { useRectificationInbox } from '../hooks/useRectificationInbox'

/**
 * Solicitudes de rectificación (HU-062) para quien tiene `riesgo.confirmar_rectificacion`: el docente que aprobó
 * un reporte decide si el registro sale de la Central. Arriba las pendientes vigentes, con el tiempo que queda;
 * debajo, las ya respondidas y las vencidas, de solo lectura. Cada solicitud usa los endpoints de esta vista:
 * no enlaza a la consulta de la Central.
 */
export function RectificationRequestsPage() {
  const { inbox, status, error, reload, reloadAll } = useRectificationInbox()
  const [notice, setNotice] = useState<string | null>(null)

  const pending = inbox?.solicitudes.filter((request) => request.estado === 'PENDIENTE') ?? []
  const answered = inbox?.solicitudes.filter((request) => request.estado !== 'PENDIENTE') ?? []

  function handleResponded(message: string) {
    setNotice(message)
    reloadAll()
  }

  return (
    <AppShell
      mobileTitle="Solicitudes de rectificación"
      breadcrumbs={[{ label: 'Central de riesgo' }, { label: 'Solicitudes de rectificación' }]}
    >
      <PageHeader
        title="Solicitudes de rectificación"
        subtitle="El administrador pide retirar de la Central un registro cuyo reporte usted aprobó. Acepte para retirarlo o rechácelo con un motivo."
      />

      <div className="space-y-6">
        {notice !== null && (
          <div
            role="status"
            className="flex items-start gap-2 rounded-lg border border-ok-border bg-ok-soft px-3 py-2.5 text-sm font-medium text-ok-fg"
          >
            <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>{notice}</p>
          </div>
        )}

        {inbox === null && status === 'loading' && (
          <Card className="p-0">
            <LoadingState rows={3} label="Cargando solicitudes" />
          </Card>
        )}

        {status === 'error' && error !== null && inbox === null && (
          <Card className="p-0">
            <ErrorState error={error} onRetry={reload} />
          </Card>
        )}

        {inbox !== null && inbox.solicitudes.length === 0 && (
          <Card className="p-0">
            <EmptyState
              icon={Inbox}
              title="No tiene solicitudes de rectificación"
              description="Cuando el administrador pida retirar un registro que usted aprobó, aparecerá aquí."
            />
          </Card>
        )}

        {inbox !== null && pending.length > 0 && (
          <section aria-labelledby="pending-requests" className="space-y-4">
            <h2 id="pending-requests" className="sciem-h3">
              Pendientes ({pending.length})
            </h2>
            {pending.map((request) => (
              <RectificationRequestItem
                key={request.id_solicitud_rect}
                request={request}
                onResponded={handleResponded}
              />
            ))}
          </section>
        )}

        {inbox !== null && inbox.solicitudes.length > 0 && pending.length === 0 && (
          <Card className="p-0">
            <EmptyState
              icon={CheckCircle2}
              title="No tiene solicitudes pendientes"
              description="Ya respondió todas las solicitudes vigentes."
            />
          </Card>
        )}

        {inbox !== null && answered.length > 0 && (
          <section aria-labelledby="answered-requests" className="space-y-4">
            <h2 id="answered-requests" className="sciem-h3">
              Respondidas y vencidas
            </h2>
            {answered.map((request) => (
              <RectificationRequestItem
                key={request.id_solicitud_rect}
                request={request}
                onResponded={handleResponded}
              />
            ))}
          </section>
        )}
      </div>
    </AppShell>
  )
}
