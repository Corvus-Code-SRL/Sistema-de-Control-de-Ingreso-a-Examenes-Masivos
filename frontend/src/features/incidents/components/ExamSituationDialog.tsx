import { useId, useState } from 'react'
import { FormSelect } from '@/components/common/FormSelect'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useRegisterExamSituation } from '../hooks/useRegisterExamSituation'
import {
  formatSituationTime, SITUATION_LABELS,
  type ExamSituationContext, type SituationCreated, type SituationType,
} from '../types/exam-situation.types'

interface Props {
  context: ExamSituationContext
  readOnly?: boolean
  onClose: () => void
  onRegistered: (result: SituationCreated) => void
}

/** Registra cambios de ambiente y observaciones de identidad durante el examen. */
export function ExamSituationDialog({ context, onClose, onRegistered, readOnly = false }: Props) {
  const id = useId()
  const destinations = context.rooms.filter((room) => room.id_ambiente !== context.room.id_ambiente)
  const [type, setType] = useState<SituationType>(destinations.length ? 'CAMBIO_AMBIENTE' : 'IDENTIFICACION')
  const [reason, setReason] = useState('')
  const [destinationRoomId, setDestinationRoomId] = useState('')
  const registration = useRegisterExamSituation(context, onRegistered)
  const disabled = registration.busy || registration.blocked || readOnly

  return (
    <Dialog open onOpenChange={(open) => { if (!open && !registration.busy) onClose() }}>
      <DialogContent className="flex max-h-[92dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-xl" showCloseButton={false}>
        <DialogHeader className="shrink-0 border-b p-4 text-left">
          <DialogTitle>Registrar situación excepcional</DialogTitle>
          <DialogDescription>Registre un cambio de ambiente o un problema de identificación del estudiante.</DialogDescription>
        </DialogHeader>
        <form className="flex min-h-0 flex-col" noValidate onSubmit={(event) => {
          event.preventDefault()
          if (!disabled) void registration.submit({ type, reason, destinationRoomId })
        }}>
          <div className="min-h-0 space-y-4 overflow-y-auto p-4">
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2 rounded-lg border bg-muted/50 p-3 text-sm">
              <dt className="text-muted-foreground">Estudiante</dt>
              <dd className="min-w-0 break-words">{context.student.nombre_completo} · {context.student.cod_sis}</dd>
              <dt className="text-muted-foreground">Examen</dt><dd className="break-words">{context.examName}</dd>
              <dt className="text-muted-foreground">Ambiente</dt><dd>{context.room.nro_aula}</dd>
              <dt className="text-muted-foreground">Hora</dt><dd>{formatSituationTime(context.serverTime)}</dd>
            </dl>
            <p className="text-xs text-muted-foreground">La hora definitiva se registra al confirmar en el servidor.</p>
            <div className="space-y-1.5">
              <Label htmlFor={`${id}-type`}>Tipo de situación</Label>
              <FormSelect id={`${id}-type`} value={type} placeholder="Seleccione una situación" disabled={disabled}
                invalid={!!registration.errors.tipo}
                options={Object.entries(SITUATION_LABELS).map(([value, label]) => ({
                  value, label, disabled: value === 'CAMBIO_AMBIENTE' && destinations.length === 0,
                }))}
                onValueChange={(value) => {
                  setType(value as SituationType)
                  setDestinationRoomId('')
                  setReason('')
                }} />
              {registration.errors.tipo && <p role="alert" className="text-sm text-danger-fg">{registration.errors.tipo}</p>}
              {!destinations.length && <p className="text-xs text-muted-foreground">El examen no tiene otro ambiente disponible.</p>}
            </div>
            {type === 'CAMBIO_AMBIENTE' && <div className="space-y-1.5">
              <Label htmlFor={`${id}-room`}>Ambiente de destino</Label>
              <FormSelect id={`${id}-room`} value={destinationRoomId} placeholder="Seleccione otro ambiente"
                disabled={disabled} invalid={!!registration.errors.id_ambiente_destino} onValueChange={setDestinationRoomId}
                options={destinations.map((room) => ({ value: String(room.id_ambiente), label: room.nro_aula }))} />
              {registration.errors.id_ambiente_destino && <p role="alert" className="text-sm text-danger-fg">{registration.errors.id_ambiente_destino}</p>}
            </div>}
            {type === 'IDENTIFICACION' && <p className="text-sm text-muted-foreground">
              Se marcará «Identidad observada». El estudiante seguirá presente.
            </p>}
            <div className="space-y-1.5">
              <Label htmlFor={`${id}-reason`}>{type === 'CAMBIO_AMBIENTE' ? 'Motivo' : 'Descripción'}</Label>
              <Textarea id={`${id}-reason`} value={reason} onChange={(event) => setReason(event.target.value)}
                disabled={disabled} rows={3} maxLength={500} aria-required="true"
                aria-invalid={!!registration.errors.motivo} aria-describedby={`${id}-reason-help`}
                placeholder={type === 'CAMBIO_AMBIENTE' ? 'Indique el motivo del cambio' : 'Describa lo ocurrido'} />
              <p id={`${id}-reason-help`} className="text-xs text-muted-foreground">
                {type === 'IDENTIFICACION' ? 'Mínimo 10 caracteres. ' : 'Campo obligatorio. '}
                {Array.from(reason.trim()).length}/500
              </p>
              {registration.errors.motivo && <p role="alert" className="text-sm text-danger-fg">{registration.errors.motivo}</p>}
            </div>
            {readOnly && <p role="alert" className="text-sm text-warn-fg">El examen cambió o no se pudo actualizar. Cierre el formulario y revise la vista.</p>}
            {registration.error && <p role="alert" className="text-sm text-danger-fg">{registration.error}</p>}
          </div>
          <DialogFooter className="m-0 shrink-0 rounded-none p-4">
            <Button type="button" variant="outline" disabled={registration.busy} onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={disabled}>
              {registration.busy ? 'Registrando…' : 'Registrar situación'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}