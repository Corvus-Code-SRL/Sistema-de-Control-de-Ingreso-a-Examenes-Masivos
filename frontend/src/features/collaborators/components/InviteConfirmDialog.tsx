import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import type { ApiError } from '@/lib/api-client'
import { formatExamDate, formatTimeRange } from '../lib/collaboratorFormat'
import type { CollaboratorCandidate, InvitableExam } from '../types/collaborator.types'

interface InviteConfirmDialogProps {
  /** El docente a invitar; null mantiene el diálogo cerrado. */
  teacher: CollaboratorCandidate | null
  exam: InvitableExam | null
  sending: boolean
  /** Rechazo del servidor del último envío (409, 403…), con su mensaje. */
  error: ApiError | null
  onConfirm: (allowGroups: boolean, listByGroup: boolean) => void
  onCancel: () => void
}

/**
 * Confirmación del envío: muestra el docente, el examen, si podrá agregar sus grupos y, en ese caso, si la
 * lista del examen se verá dividida por grupo. La invitación solo se envía al confirmar; cancelar o cerrar
 * no envía nada.
 */
export function InviteConfirmDialog({ teacher, exam, sending, error, onConfirm, onCancel }: InviteConfirmDialogProps) {
  const open = teacher !== null && exam !== null

  return (
    <Dialog open={open} onOpenChange={(next) => !next && !sending && onCancel()}>
      <DialogContent className="sm:max-w-md">
        {teacher && exam && (
          <InviteForm
            key={teacher.id_usuario}
            teacher={teacher}
            exam={exam}
            sending={sending}
            error={error}
            onConfirm={onConfirm}
            onCancel={onCancel}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

interface InviteFormProps extends Omit<InviteConfirmDialogProps, 'teacher' | 'exam'> {
  teacher: CollaboratorCandidate
  exam: InvitableExam
}

function InviteForm({ teacher, exam, sending, error, onConfirm, onCancel }: InviteFormProps) {
  // La opción vuelve a nacer marcada cada vez que se abre el diálogo para un docente.
  const [allowGroups, setAllowGroups] = useState(true)
  // Lista dividida por grupo (HU-027): solo se habilita si permite agregar grupos; nace marcada.
  const [listByGroup, setListByGroup] = useState(true)

  function changeAllowGroups(checked: boolean) {
    setAllowGroups(checked)
    if (!checked) setListByGroup(false)
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>Invitar docente colaborador</DialogTitle>
        <DialogDescription>
          Revise los datos. La invitación se enviará solo cuando la confirme y quedará en estado Pendiente.
        </DialogDescription>
      </DialogHeader>

      <dl className="grid gap-3 text-sm">
        <div>
          <dt className="text-xs font-medium text-muted-foreground">Docente</dt>
          <dd className="font-medium">{teacher.nombre_completo}</dd>
          {teacher.cod_sis && <dd className="text-xs text-muted-foreground">Código SIS {teacher.cod_sis}</dd>}
        </div>
        <div>
          <dt className="text-xs font-medium text-muted-foreground">Examen</dt>
          <dd className="font-medium">{exam.nombre_examen}</dd>
          <dd className="text-xs text-muted-foreground">
            {[exam.materia?.nombre, formatExamDate(exam.fecha), formatTimeRange(exam.hora_inicio, exam.hora_fin)]
              .filter(Boolean)
              .join(' · ')}
          </dd>
        </div>
      </dl>

      <div className="flex items-start gap-2">
        <Checkbox
          id="collaborator-allow-groups"
          checked={allowGroups}
          onCheckedChange={(checked) => changeAllowGroups(checked === true)}
          disabled={sending}
          className="mt-0.5"
        />
        <Label htmlFor="collaborator-allow-groups" className="text-sm font-normal leading-snug">
          Permitir que agregue sus grupos al examen
        </Label>
      </div>
      <p className="-mt-2 text-xs text-muted-foreground">
        Si acepta, podrá sumar sus propios grupos de esta materia para unir las listas de estudiantes del control de ingreso.
      </p>

      <div className="flex items-start gap-2">
        <Checkbox
          id="collaborator-list-by-group"
          checked={listByGroup}
          onCheckedChange={(checked) => setListByGroup(checked === true)}
          disabled={sending || !allowGroups}
          className="mt-0.5"
        />
        <Label
          htmlFor="collaborator-list-by-group"
          className={`text-sm font-normal leading-snug${allowGroups ? '' : ' text-muted-foreground'}`}
        >
          Mostrar la lista separada por grupo
        </Label>
      </div>
      <p className="-mt-2 text-xs text-muted-foreground">
        {allowGroups
          ? 'En el control de ingreso y durante el examen la lista se verá dividida por grupo, con su docente. Si no la marca, la lista va junta.'
          : 'Se habilita al permitir que agregue sus grupos.'}
      </p>

      {error && (
        <p role="alert" className="rounded-lg border border-danger-border bg-danger-soft p-3 text-sm text-danger-fg">
          {error.message}
        </p>
      )}

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={sending}>
          Cancelar
        </Button>
        <Button type="button" onClick={() => onConfirm(allowGroups, allowGroups && listByGroup)} disabled={sending}>
          {sending ? 'Enviando…' : 'Enviar invitación'}
        </Button>
      </DialogFooter>
    </>
  )
}
