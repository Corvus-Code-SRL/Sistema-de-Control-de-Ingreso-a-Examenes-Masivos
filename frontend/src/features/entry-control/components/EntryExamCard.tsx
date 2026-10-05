import { Clock3, MapPin, Users } from 'lucide-react'
import { FormSelect } from '@/components/common/FormSelect'
import { Label } from '@/components/ui/label'
import type { EntryContext, EntryStatus } from '../types/entry-control.types'

interface Props {
  context: EntryContext
  status: EntryStatus | null
  roomId: number | null
  onRoomChange: (id: number) => void
  roomDisabled?: boolean
}

export function EntryExamCard({ context, status, roomId, onRoomChange, roomDisabled = false }: Props) {
  const room = context.ambientes.find((item) => item.id_ambiente === roomId)
  const isAssistant = context.rol_controlador === 'AUXILIAR'

  return (
    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm" aria-label="Examen y progreso">
      <div className="border-b border-border bg-brand-soft/60 px-4 py-4 sm:px-6">
        <div className="flex flex-wrap items-start justify-between gap-2">
          <div>
            <p className="sciem-overline text-brand-deep">Examen</p>
            <h2 className="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">{context.nombre_examen}</h2>
            <p className="mt-1 text-sm text-muted-foreground">{[context.materia, context.grupos.length ? `Grupo ${context.grupos.join(', ')}` : null].filter(Boolean).join(' · ')}</p>
          </div>
          <span className={`rounded-full px-3 py-1 text-xs font-semibold ${context.estado === 'EN_INGRESO' ? 'bg-ok-soft text-ok-fg' : 'bg-warn-soft text-warn-fg'}`}>{context.estado === 'EN_INGRESO' ? 'Ingreso abierto' : context.estado.replace(/_/g, ' ')}</span>
        </div>
        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-brand-deep">
          <span className="flex items-center gap-1.5"><Clock3 className="size-4" aria-hidden="true" />{context.hora_inicio ?? '—'}–{context.hora_fin ?? '—'}</span>
          {room && <span className="flex items-center gap-1.5"><MapPin className="size-4" aria-hidden="true" />{room.nro_aula}</span>}
        </div>
      </div>
      {context.estado === 'EN_INGRESO' && <div className="grid gap-5 px-4 py-4 sm:grid-cols-[1fr_auto] sm:items-center sm:px-6">
        <div>
          <div className="flex items-baseline gap-2">
            <span className="sciem-tnum text-3xl font-semibold text-brand-deep">{status?.ingresados ?? '—'}</span>
            <span className="sciem-tnum text-lg text-muted-foreground">/ {status?.total ?? '—'}</span>
            <span className="text-sm text-muted-foreground">ingresados</span>
          </div>
          <div className="mt-2 h-2 overflow-hidden rounded-full bg-sunken" role="progressbar" aria-label="Estudiantes ingresados" aria-valuenow={status?.ingresados ?? 0} aria-valuemax={status?.total ?? 0}>
            <div className="h-full rounded-full bg-brand transition-all" style={{ width: `${status?.total ? Math.min(100, status.ingresados / status.total * 100) : 0}%` }} />
          </div>
          <p className="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"><Users className="size-3.5" aria-hidden="true" />{status?.pendientes ?? '—'} pendientes</p>
        </div>
        <div className="min-w-44">
          {isAssistant ? (
            <p className="rounded-lg bg-sunken px-3 py-2 text-sm text-muted-foreground">Usted controla en <strong className="text-foreground">{room?.nro_aula ?? 'su aula asignada'}</strong>.</p>
          ) : (
            <div className="space-y-1.5">
              <Label htmlFor="entry-room">Ambiente de control</Label>
              <FormSelect
                id="entry-room"
                disabled={roomDisabled}
                value={roomId === null ? undefined : String(roomId)}
                placeholder="Seleccionar ambiente"
                options={context.ambientes.map((item) => ({
                  value: String(item.id_ambiente),
                  label: item.nro_aula,
                }))}
                onValueChange={(value) => onRoomChange(Number(value))}
                className="text-sm"
              />
            </div>
          )}
        </div>
      </div>}
    </section>
  )
}
