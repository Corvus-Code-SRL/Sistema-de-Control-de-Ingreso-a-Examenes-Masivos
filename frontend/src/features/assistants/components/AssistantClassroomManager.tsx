import { useState, type ReactNode } from 'react'
import { AlertTriangle, Building2, Loader2, Lock, UserRound, Users } from 'lucide-react'
import { LoadingState } from '@/components/common/LoadingState'
import { ErrorState } from '@/components/common/ErrorState'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { ApiError } from '@/lib/api-client'
import { assistantsService } from '../services/assistantsService'
import { AssistantClassroomRow } from './AssistantClassroomRow'

interface AssistantClassroomManagerProps {
  examId: number
  isProgramado?: boolean
  actions?: ReactNode
}

/**
 * HU-030: modal de asignación y resumen en el detalle del examen.
 * Los cambios se preparan localmente; solo se envían al pulsar «Guardar».
 * La editabilidad la determina el backend, no el navegador.
 */
export function AssistantClassroomManager({examId, isProgramado, actions, }: AssistantClassroomManagerProps) {
  const { status, data, error, reload } = useAsyncResource(
    (signal) => assistantsService.listExamAssistants(examId, signal),
    [examId]
  )
  const [open, setOpen] = useState(false)
  const [draft, setDraft] = useState<Record<string, number>>({})
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)

  const singleRoom = data?.ambientes.length === 1 ? data.ambientes[0] : null
  const unassigned = data && data.ambientes.length > 1
    ? data.auxiliares.filter((assistant) => assistant.id_ambiente === null).length
    : 0

  const changes = data?.auxiliares.filter((assistant) =>
    draft[assistant.id_usuario] !== undefined &&
    draft[assistant.id_usuario] !== assistant.id_ambiente
  ) ?? []

  const setDialogOpen = (next: boolean) => {
    if (saving) return
    setOpen(next)
    setDraft({})
    setSaveError(null)
    if (next) reload()
  }

  const save = async () => {
    if (!data?.editable || changes.length === 0 || saving) return
    setSaving(true)
    setSaveError(null)

    try {
      for (const assistant of changes) {
        await assistantsService.assignClassroom(
          examId,
          assistant.id_usuario,
          draft[assistant.id_usuario]
        )
      }
      setOpen(false)
      setDraft({})
      reload()
    } catch (cause) {
      // El endpoint guarda cada auxiliar por separado. Si falla uno,
      // se vuelve a consultar el estado real antes de permitir reintentar.
      const reason = cause instanceof ApiError ? cause.message : 'No se pudo completar la asignación.'
      setSaveError(`${reason} Revise las asignaciones actuales antes de reintentar.`)
      setDraft({})
      reload()
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        {actions}

        <Button
          type="button"
          variant="outline"
          onClick={() => setDialogOpen(true)}
        >
          <Building2 className="size-4" aria-hidden="true" />
          {(isProgramado ?? data?.editable !== false)
            ? 'Asignar ambientes'
            : 'Ver ambientes'}
        </Button>

        {status === 'success' && data && unassigned > 0 && (
          <p
            role="status"
            className="inline-flex items-center gap-1.5 text-sm font-medium text-warn-fg"
          >
            <AlertTriangle className="size-4" aria-hidden="true" />
            {unassigned}{' '}
            {unassigned === 1
              ? 'auxiliar sin ambiente'
              : 'auxiliares sin ambiente'}
          </p>
        )}
      </div>

      {status === 'success' && data && data.auxiliares.length > 0 && (
        <div className="overflow-hidden rounded-lg border border-border-soft">
          <div className="border-b border-border-soft px-4 py-3">
            <p className="text-sm font-medium text-foreground">
              Auxiliares habilitados ({data.auxiliares.length})
            </p>
          </div>

          <ul className="divide-y divide-border-soft">
            {data.auxiliares.map((assistant) => {
              const room =
                assistant.ambiente?.nro_aula ??
                singleRoom?.nro_aula

              return (
                <li
                  key={assistant.id_usuario}
                  className="flex flex-wrap items-center gap-3 px-4 py-3"
                >
                  <div className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border-soft bg-surface">
                    <UserRound
                      className="size-4 text-muted-foreground"
                      aria-hidden="true"
                    />
                  </div>

                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground">
                      {assistant.nombre_completo}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      Auxiliar habilitado
                    </p>
                  </div>

                  <span
                    className={
                      room
                        ? 'rounded-md border border-border-soft bg-surface px-3 py-1 text-xs font-medium text-foreground'
                        : 'rounded-md border border-warn-border bg-warn-soft px-3 py-1 text-xs font-medium text-warn-fg'
                    }
                  >
                    {room ?? 'Sin ambiente'}
                  </span>
                </li>
              )
            })}
          </ul>
        </div>
      )}

      <Dialog open={open} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl" showCloseButton={!saving}>
          <DialogHeader>
            <DialogTitle>Asignar ambientes</DialogTitle>
            <DialogDescription>
              Consulte los auxiliares habilitados y seleccione el ambiente donde controlará cada uno.
            </DialogDescription>
          </DialogHeader>

          {status === 'loading' && <LoadingState rows={3} label="Cargando los auxiliares" />}
          {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {status === 'success' && data && data.auxiliares.length === 0 && (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
              <Users className="size-4" aria-hidden="true" />
              Este examen no tiene auxiliares habilitados.
            </p>
          )}

          {status === 'success' && data && data.auxiliares.length > 0 && (
            <div className="space-y-3">
              {!data.editable && (
                <p className="flex items-center gap-2 rounded-lg border border-warn-border bg-warn-soft p-3 text-sm text-warn-fg">
                  <Lock className="size-4 shrink-0" aria-hidden="true" />
                  El examen ya no permite cambiar asignaciones. Puede consultarlas.
                </p>
              )}

              {singleRoom ? (
                <div className="space-y-2">
                  <p className="rounded-lg border p-3 text-sm font-medium">
                    Todos los auxiliares controlan en el {singleRoom.nro_aula}.
                  </p>
                  <ul className="list-disc space-y-1 pl-5 text-sm">
                    {data.auxiliares.map((assistant) => (
                      <li key={assistant.id_usuario}>{assistant.nombre_completo}</li>
                    ))}
                  </ul>
                </div>
              ) : (
                <ul className="divide-y overflow-hidden rounded-lg border">
                  {data.auxiliares.map((assistant) => (
                    <AssistantClassroomRow
                      key={assistant.id_usuario}
                      assistant={{
                        ...assistant,
                        id_ambiente: draft[assistant.id_usuario] ?? assistant.id_ambiente,
                      }}
                      classrooms={data.ambientes}
                      editable={data.editable}
                      saving={false}
                      disabled={saving}
                      onAssign={(_, roomId) => setDraft((previous) => ({
                        ...previous,
                        [assistant.id_usuario]: roomId,
                      }))}
                    />
                  ))}
                </ul>
              )}
            </div>
          )}

          {saveError && (
            <p role="alert" className="rounded-lg border border-danger/20 bg-danger-soft p-3 text-sm text-danger-fg">
              {saveError}
            </p>
          )}

          <DialogFooter>
            <Button type="button" variant="outline" disabled={saving} onClick={() => setDialogOpen(false)}>
              {data?.editable && !singleRoom ? 'Cancelar' : 'Cerrar'}
            </Button>
            {data?.editable && !singleRoom && (
              <Button type="button" disabled={saving || changes.length === 0 || status !== 'success'} onClick={() => void save()}>
                {saving ? <><Loader2 className="size-4 animate-spin" aria-hidden="true" /> Guardando...</> : 'Guardar cambios'}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
