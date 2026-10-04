import { useState } from 'react'
import { Trash2 } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { ApiError } from '@/lib/api-client'
import type { AssistantWithGroups } from '../types/assistant.types'

interface RemoveAssistantFromGroupModalProps {
  open: boolean
  assistant: AssistantWithGroups | null
  onConfirm: (groupId: number) => Promise<void>
  onCancel: () => void
}

/**
 * Modal para quitar un auxiliar de un grupo.
 *
 * Si el grupo tiene un examen PROGRAMADO, se nombra en el mensaje:
 * "Dejará de estar asignada a <materia> · Grupo <n>. Examen afectado:
 *  <nombre del examen> (<dd/mm>)."
 */
export function RemoveAssistantFromGroupModal({
  open,
  assistant,
  onConfirm,
  onCancel,
}: RemoveAssistantFromGroupModalProps) {
  const [groupId, setGroupId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const group = assistant?.grupos.find((g) => g.id_grupo === groupId)

  async function handleConfirm() {
    if (groupId === null) return

    setIsSubmitting(true)
    setError(null)
    try {
      await onConfirm(groupId)
    } catch (e) {
      setError(errorMessage(e))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onCancel()}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <div className="flex items-center gap-3">
            <span className="flex size-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
              <Trash2 className="size-5" />
            </span>
            <DialogTitle>
              ¿Quitar a {assistant?.nombre_completo ?? 'auxiliar'} de un grupo?
            </DialogTitle>
          </div>
        </DialogHeader>

        <div className="space-y-2">
          <Label>Grupo</Label>
          <Select
            value={groupId !== null ? String(groupId) : undefined}
            onValueChange={(v) => {
              setGroupId(Number(v))
              setError(null)
            }}
            disabled={isSubmitting}
          >
            <SelectTrigger>
              <SelectValue placeholder="Seleccione un grupo" />
            </SelectTrigger>
            <SelectContent>
              {(assistant?.grupos ?? []).map((g) => (
                <SelectItem key={g.id_grupo} value={String(g.id_grupo)}>
                  {g.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {group && (
          <p className="text-sm text-muted-foreground">
            Dejará de estar asignada a {group.label}.
            {group.tiene_examen_programado && group.examen_programado && (
              <>
                {' '}
                Examen afectado:{' '}
                <strong>
                  {group.examen_programado.nombre_examen} (
                  {formatDate(group.examen_programado.fecha)})
                </strong>
                .
                {' '}
                Si era su único grupo vinculado a ese examen, también se le quitará la habilitación.
              </>
            )}
          </p>
        )}

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" onClick={onCancel} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button
            variant="danger"
            onClick={handleConfirm}
            disabled={isSubmitting || groupId === null}
          >
            {isSubmitting ? 'Quitando…' : 'Quitar del grupo'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

/** Primer motivo de rechazo del backend, o un mensaje genérico si no hubo respuesta útil. */
function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return Object.values(error.errors)[0]?.[0] ?? error.message
  }

  return 'No se pudo quitar al auxiliar del grupo.'
}

/** Convierte "2026-09-29" en "29/09". */
function formatDate(isoDate: string): string {
  const [year, month, day] = isoDate.split('-')
  return `${day}/${month}`
}