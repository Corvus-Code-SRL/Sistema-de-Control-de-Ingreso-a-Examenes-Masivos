import { useCallback, useState } from 'react'
import { Inbox, Mail } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Card } from '@/components/ui/card'
import { Toast } from '@/components/ui/toast'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { InvitationDetailPanel, type InvitationOutcome } from '../components/InvitationDetailPanel'
import { InvitationsList } from '../components/InvitationsList'
import { notifyInvitationsChanged } from '../hooks/usePendingInvitationsCount'
import { TAB_LABEL } from '../lib/invitationFormat'
import { listInvitations } from '../services/invitationsService'
import type { InvitationListResponse, InvitationTab } from '../types/invitation.types'

export const INVITATIONS_PATH = '/invitaciones'

const TABS: InvitationTab[] = ['pendientes', 'aceptadas', 'cerradas']

const EMPTY: Record<InvitationTab, { title: string; description: string }> = {
  pendientes: {
    title: 'No tiene invitaciones pendientes',
    description: 'Cuando un docente lo invite a un examen de su materia, aparecerá aquí.',
  },
  aceptadas: {
    title: 'No tiene invitaciones aceptadas',
    description: 'Las invitaciones que acepte aparecerán aquí, con los grupos que sumó.',
  },
  cerradas: {
    title: 'No tiene invitaciones cerradas',
    description: 'Aquí aparecen las que rechazó y las que vencieron sin respuesta.',
  },
}

function countOf(response: InvitationListResponse | null, tab: InvitationTab): number {
  const meta = response?.meta

  if (tab === 'pendientes') return meta?.total_pendientes ?? 0
  if (tab === 'aceptadas') return meta?.total_aceptadas ?? 0

  return meta?.total_cerradas ?? 0
}

/**
 * HU-027 · Invitaciones recibidas: bandeja propia del docente invitado, separada de «Invitar docentes»
 * (HU-026). A la izquierda las invitaciones por estado; a la derecha el detalle de la elegida.
 */
export function InvitationsPage() {
  const [tab, setTab] = useState<InvitationTab>('pendientes')
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [toast, setToast] = useState<InvitationOutcome | null>(null)
  // Sube al responder: el detalle se vuelve a pedir aunque siga elegida la misma invitación.
  const [version, setVersion] = useState(0)

  const resource = useAsyncResource(
    useCallback((signal: AbortSignal) => listInvitations(tab, signal), [tab]),
    [tab]
  )

  const invitations = resource.data?.data ?? []
  const isEmpty = resource.status === 'success' && invitations.length === 0
  const selected = invitations.some((invitation) => invitation.id_invitacion === selectedId) ? selectedId : null

  function changeTab(next: InvitationTab) {
    setTab(next)
    setSelectedId(null)
  }

  function handleResponded(outcome: InvitationOutcome) {
    setToast(outcome)
    setVersion((previous) => previous + 1)
    notifyInvitationsChanged()

    if (outcome.kind === 'accepted') {
      // La aceptada se sigue mostrando, ahora en su pestaña.
      setTab('aceptadas')
    } else if (outcome.kind === 'rejected') {
      setSelectedId(null)
    }

    resource.reload()
  }

  return (
    <AppShell
      mobileTitle="Invitaciones recibidas"
      breadcrumbs={[{ label: 'Preparación de exámenes' }, { label: 'Invitaciones recibidas' }]}
    >
      <PageHeader
        title="Invitaciones recibidas"
        subtitle="Exámenes de sus materias a los que otros docentes lo invitaron a colaborar."
      />

      <div role="tablist" aria-label="Estado de las invitaciones" className="flex gap-1 border-b border-border">
        {TABS.map((value) => {
          const active = value === tab
          const count = countOf(resource.data, value)

          return (
            <button
              key={value}
              role="tab"
              type="button"
              aria-selected={active}
              onClick={() => changeTab(value)}
              className={`relative flex h-10 shrink-0 items-center gap-2 px-3.5 text-sm font-medium transition-colors ${
                active ? 'text-brand' : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              {TAB_LABEL[value]}
              {count > 0 && (
                <span
                  className={`rounded-full px-1.5 text-xs font-semibold ${
                    active ? 'bg-brand-soft text-brand-deep' : 'bg-neutral-soft text-neutral-fg'
                  }`}
                >
                  {count}
                </span>
              )}
              {active && <span aria-hidden="true" className="absolute inset-x-0 -bottom-px h-0.5 bg-brand" />}
            </button>
          )
        })}
      </div>

      {resource.status === 'loading' && (
        <Card className="p-0">
          <LoadingState rows={3} label="Cargando invitaciones" />
        </Card>
      )}

      {resource.status === 'error' && resource.error && (
        <Card className="p-0">
          <ErrorState error={resource.error} onRetry={resource.reload} />
        </Card>
      )}

      {isEmpty && (
        <Card className="p-0">
          <EmptyState icon={tab === 'pendientes' ? Inbox : Mail} {...EMPTY[tab]} />
        </Card>
      )}

      {resource.status === 'success' && !isEmpty && (
        // Un solo diseño: en escritorio lista y detalle lado a lado; en móvil el detalle reemplaza la lista.
        <div className="grid gap-5 lg:grid-cols-[440px_1fr]">
          <div className={selected === null ? undefined : 'hidden lg:block'}>
            <InvitationsList invitations={invitations} selectedId={selected} onSelect={setSelectedId} />
          </div>
          <div className={selected === null ? 'hidden lg:block' : undefined}>
            {selected === null ? (
              <Card className="p-0">
                <EmptyState icon={Mail} title="Elija una invitación" description="El detalle aparece aquí." />
              </Card>
            ) : (
              <InvitationDetailPanel
                invitationId={selected}
                version={version}
                onResponded={handleResponded}
                onBack={() => setSelectedId(null)}
              />
            )}
          </div>
        </div>
      )}

      {toast && (
        <Toast
          title={toast.title}
          description={toast.description}
          variant={toast.kind === 'conflict' ? 'error' : 'success'}
          onClose={() => setToast(null)}
        />
      )}
    </AppShell>
  )
}
