import { useState } from 'react'
import { ArrowLeft, Check, Clock, X } from 'lucide-react'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { cn } from '@/lib/utils'
import {
  canRespond,
  firstName,
  formatExamLong,
  formatMoment,
  groupLabel,
  joinGroups,
  plural,
} from '../lib/invitationFormat'
import { acceptInvitation, getInvitation, rejectInvitation } from '../services/invitationsService'
import type { InvitationDetail, MyInvitationGroup } from '../types/invitation.types'
import { AcceptInvitationDialog } from './AcceptInvitationDialog'
import { CollaboratorPrivileges } from './CollaboratorPrivileges'
import { InvitationStateBadge } from './InvitationStateBadge'
import { RejectInvitationDialog } from './RejectInvitationDialog'

export const NO_GROUP_MESSAGE = 'Elija al menos uno de sus grupos.'

export interface InvitationOutcome {
  kind: 'accepted' | 'rejected' | 'conflict'
  title: string
  description: string
}

interface InvitationDetailPanelProps {
  invitationId: number
  /** Cambia cuando la invitación se respondió: obliga a volver a pedir el detalle. */
  version?: number
  onResponded: (outcome: InvitationOutcome) => void
  onBack?: () => void
}

/** Detalle de la invitación elegida, en la misma vista (CA-3), con sus grupos (CA-4) y privilegios (CA-6). */
export function InvitationDetailPanel({
  invitationId,
  version = 0,
  onResponded,
  onBack,
}: InvitationDetailPanelProps) {
  const resource = useAsyncResource((signal) => getInvitation(invitationId, signal), [invitationId, version])

  if (resource.status === 'loading') {
    return (
      <Card className="p-0">
        <LoadingState rows={4} label="Cargando invitación" />
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

  return (
    <InvitationDetailContent
      key={`${resource.data.id_invitacion}-${resource.data.estado}`}
      invitation={resource.data}
      onResponded={onResponded}
      onBack={onBack}
    />
  )
}

interface ContentProps {
  invitation: InvitationDetail
  onResponded: (outcome: InvitationOutcome) => void
  onBack?: () => void
}

function InvitationDetailContent({ invitation, onResponded, onBack }: ContentProps) {
  const pending = canRespond(invitation.estado)
  const [selectedIds, setSelectedIds] = useState<number[]>(() =>
    pending ? [] : invitation.mis_grupos.filter((group) => group.sumado).map((group) => group.id_grupo)
  )
  const [groupError, setGroupError] = useState<string | null>(null)
  const [acceptOpen, setAcceptOpen] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)

  const { examen } = invitation
  const owner = firstName(invitation.invitante.nombre_completo)
  const selectedGroups = invitation.mis_grupos.filter((group) => selectedIds.includes(group.id_grupo))
  const examStudents = invitation.grupos_examen.reduce((sum, group) => sum + group.inscritos, 0)
  const context = [examen.materia, examen.carrera, examen.periodo].filter(Boolean).join(' · ')

  function toggle(group: MyInvitationGroup, checked: boolean) {
    setGroupError(null)
    setSelectedIds((previous) =>
      checked ? [...previous, group.id_grupo] : previous.filter((id) => id !== group.id_grupo)
    )
  }

  function handleAcceptClick() {
    if (selectedGroups.length === 0) {
      setGroupError(NO_GROUP_MESSAGE)
      return
    }

    setAcceptOpen(true)
  }

  async function handleAccept() {
    await acceptInvitation(invitation.id_invitacion, selectedIds)
    setAcceptOpen(false)
    onResponded({
      kind: 'accepted',
      title: 'Invitación aceptada',
      description: `${capitalize(joinGroups(selectedGroups))} ya ${
        selectedGroups.length === 1 ? 'forma' : 'forman'
      } parte del ${examen.nombre_examen}. ${owner} verá «Aceptada».`,
    })
  }

  async function handleReject(reason: string) {
    await rejectInvitation(invitation.id_invitacion, reason)
    setRejectOpen(false)
    onResponded({
      kind: 'rejected',
      title: 'Invitación rechazada',
      description: `${owner} verá «Rechazada». El examen no cambió.`,
    })
  }

  function handleConflict(message: string) {
    setAcceptOpen(false)
    setRejectOpen(false)
    onResponded({ kind: 'conflict', title: 'La invitación ya no se puede responder', description: message })
  }

  return (
    <>
      <Card className="p-0">
        <div className="flex flex-col gap-5 p-5">
          {onBack && (
            <Button variant="ghost" size="sm" onClick={onBack} className="self-start lg:hidden">
              <ArrowLeft className="size-4" />
              Volver
            </Button>
          )}

          <header className="flex flex-col gap-1">
            {context && <p className="sciem-overline text-xs text-muted-foreground">{context}</p>}
            <h2 className="sciem-h2">{examen.nombre_examen}</h2>
            <p className="text-sm text-muted-foreground">
              {pending ? 'Invita' : 'Invitó'} {invitation.invitante.nombre_completo} (docente responsable) · enviada el{' '}
              {formatMoment(invitation.fecha_invitacion)}
            </p>
          </header>

          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
            <dt className="text-muted-foreground">Fecha y hora</dt>
            <dd className="font-medium">{formatExamLong(examen.fecha, examen.hora_inicio, examen.hora_fin)}</dd>

            <dt className="text-muted-foreground">Ambientes</dt>
            <dd className="font-medium">
              {invitation.ambientes.length === 0
                ? 'Sin ambientes asignados'
                : invitation.ambientes.map((room) => `Aula ${room.nro_aula} (${room.capacidad})`).join(' · ')}
            </dd>

            {invitation.grupos_examen.length > 0 && (
              <>
                <dt className="text-muted-foreground">Grupos del examen</dt>
                <dd className="font-medium">
                  {invitation.grupos_examen.map(groupLabel).join(' · ')} (
                  {plural(examStudents, 'estudiante', 'estudiantes')})
                </dd>
              </>
            )}
          </dl>

          {invitation.mensaje && (
            <div className="rounded-lg border border-border bg-sunken px-3.5 py-3 text-sm">
              <b>Mensaje de {owner}:</b> «{invitation.mensaje}»
            </div>
          )}

          {invitation.estado === 'SIN_EFECTO' ? (
            <div role="status" className="flex items-start gap-2.5 rounded-lg border border-border bg-sunken px-3 py-2.5">
              <Clock className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
              <div className="text-sm">
                <p className="font-semibold">La invitación venció</p>
                <p className="text-muted-foreground">
                  {invitation.fecha_sin_efecto
                    ? `El examen abrió el control de ingreso el ${formatMoment(invitation.fecha_sin_efecto)} sin una respuesta suya.`
                    : 'El examen abrió el control de ingreso sin una respuesta suya.'}
                </p>
              </div>
            </div>
          ) : (
            <MyGroups
              invitation={invitation}
              selectedIds={selectedIds}
              editable={pending}
              error={groupError}
              onToggle={toggle}
            />
          )}

          {invitation.estado === 'RECHAZADA' && invitation.motivo_rechazo && (
            <p className="text-sm">
              <span className="text-muted-foreground">Motivo del rechazo:</span> {invitation.motivo_rechazo}
            </p>
          )}

          {invitation.estado !== 'SIN_EFECTO' && <CollaboratorPrivileges />}
        </div>

        <footer className="flex flex-wrap items-center gap-3 border-t border-border px-5 py-4">
          {pending ? (
            <>
              <Button variant="outline" className="text-danger-fg" onClick={() => setRejectOpen(true)}>
                <X className="size-4" />
                Rechazar
              </Button>
              <span className="flex-1" />
              <Button onClick={handleAcceptClick}>
                <Check className="size-4" />
                Aceptar con {plural(selectedGroups.length, 'grupo', 'grupos')}
              </Button>
            </>
          ) : (
            <>
              <span className="flex-1" />
              <InvitationStateBadge
                estado={invitation.estado}
                suffix={invitation.fecha_respuesta ? `el ${formatMoment(invitation.fecha_respuesta)}` : undefined}
              />
            </>
          )}
        </footer>
      </Card>

      {pending && (
        <>
          <AcceptInvitationDialog
            open={acceptOpen}
            onOpenChange={setAcceptOpen}
            invitation={invitation}
            groups={selectedGroups}
            onConfirm={handleAccept}
            onConflict={handleConflict}
          />
          <RejectInvitationDialog
            open={rejectOpen}
            onOpenChange={setRejectOpen}
            invitation={invitation}
            onConfirm={handleReject}
            onConflict={handleConflict}
          />
        </>
      )}
    </>
  )
}

interface MyGroupsProps {
  invitation: InvitationDetail
  selectedIds: number[]
  editable: boolean
  error: string | null
  onToggle: (group: MyInvitationGroup, checked: boolean) => void
}

/** «Mis grupos de <materia> que sumo al examen»: solo sus grupos de esa materia en el período vigente. */
function MyGroups({ invitation, selectedIds, editable, error, onToggle }: MyGroupsProps) {
  const titleId = `my-groups-${invitation.id_invitacion}`
  const groups = editable ? invitation.mis_grupos : invitation.mis_grupos.filter((group) => group.sumado)

  return (
    <fieldset className="flex flex-col gap-2" aria-labelledby={titleId} aria-describedby={error ? `${titleId}-error` : undefined}>
      <legend id={titleId} className="mb-2 text-sm font-semibold text-foreground">
        {editable
          ? `Mis grupos de ${invitation.examen.materia} que sumo al examen`
          : `Mis grupos de ${invitation.examen.materia} en el examen`}
      </legend>

      {groups.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          {editable
            ? `No tiene grupos de ${invitation.examen.materia} en el período vigente.`
            : 'Ninguno de sus grupos forma parte del examen.'}
        </p>
      ) : (
        groups.map((group) => {
          const checked = selectedIds.includes(group.id_grupo)
          const disabled = !editable || !group.seleccionable

          return (
            <label
              key={group.id_grupo}
              className={cn(
                'flex items-center gap-3 rounded-[10px] border border-border px-3 py-2.5',
                checked && 'border-brand bg-brand-soft',
                !group.seleccionable && 'opacity-50',
                !disabled && 'cursor-pointer hover:bg-sunken'
              )}
            >
              <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(event) => onToggle(group, event.target.checked)}
                className="size-4 rounded border-border-strong accent-brand"
              />
              <span className="flex-1 text-sm font-semibold">{groupLabel(group)}</span>
              <span className="text-sm text-muted-foreground">
                {group.seleccionable || !group.motivo ? plural(group.inscritos, 'inscrito', 'inscritos') : group.motivo}
              </span>
            </label>
          )
        })
      )}

      {error && (
        <p id={`${titleId}-error`} role="alert" className="text-xs font-medium text-danger-fg">
          {error}
        </p>
      )}
    </fieldset>
  )
}

function capitalize(text: string): string {
  return text.charAt(0).toUpperCase() + text.slice(1)
}
