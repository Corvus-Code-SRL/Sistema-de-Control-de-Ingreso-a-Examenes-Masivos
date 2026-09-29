import { useState, useEffect } from 'react'
import { AlertTriangle } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import type { AssistantWithGroups, GroupOption } from '../types/assistant.types'

interface MoveAssistantModalProps {
  open: boolean
  assistant: AssistantWithGroups | null
  /** Todos los grupos del docente en el período activo. */
  availableGroups: GroupOption[]
  onConfirm: (sourceGroupId: number, targetGroupId: number) => Promise<void>
  onCancel: () => void
}

/**
 * Modal para mover un auxiliar de un grupo a otro.
 *
 * El grupo de origen sale de los grupos donde el auxiliar YA está.
 * El grupo de destino sale de TODOS los grupos del docente que no
 * sean el origen (el auxiliar puede no estar aún en ellos).
 */
export function MoveAssistantModal({
  open,
  assistant,
  availableGroups,
  onConfirm,
  onCancel,
}: MoveAssistantModalProps) {
  const [sourceId, setSourceId] = useState<number | null>(null)
  const [targetId, setTargetId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    if (assistant && assistant.grupos.length > 0) {
      setSourceId(assistant.grupos[0].id_grupo)
    } else {
      setSourceId(null)
    }
    setTargetId(null)
  }, [assistant])

  async function handleConfirm() {
    if (sourceId === null || targetId === null || sourceId === targetId) return

    setIsSubmitting(true)
    try {
      await onConfirm(sourceId, targetId)
    } finally {
      setIsSubmitting(false)
    }
  }

  const sourceGroup = assistant?.grupos.find((g) => g.id_grupo === sourceId)

  // Grupos de destino: todos los del docente, excepto el de origen
  // y excepto los que el auxiliar ya tiene.
  const targetGroups = availableGroups.filter(
    (g) =>
      g.id_grupo !== sourceId &&
      !assistant?.grupos.some((ag) => ag.id_grupo === g.id_grupo)
  )

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onCancel()}>
      <DialogContent className="sm:max-w-[560px]">
        <DialogHeader>
          <DialogTitle>
            Mover a {assistant?.nombre_completo ?? 'auxiliar'}
          </DialogTitle>
        </DialogHeader>

        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1">
            <Label>Grupo de origen</Label>
            <Select
              value={sourceId !== null ? String(sourceId) : undefined}
              onValueChange={(v) => {
                setSourceId(Number(v))
                setTargetId(null)
              }}
              disabled={isSubmitting}
            >
              <SelectTrigger>
                <SelectValue placeholder="Seleccione" />
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

          <div className="space-y-1">
            <Label>Grupo de destino</Label>
            <Select
              value={targetId !== null ? String(targetId) : undefined}
              onValueChange={(v) => setTargetId(Number(v))}
              disabled={isSubmitting || sourceId === null}
            >
              <SelectTrigger>
                <SelectValue placeholder="Seleccione" />
              </SelectTrigger>
              <SelectContent>
                {targetGroups.map((g) => (
                  <SelectItem key={g.id_grupo} value={String(g.id_grupo)}>
                    {g.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {targetGroups.length === 0 && (
              <p className="text-xs text-muted-foreground">
                No hay otros grupos disponibles.
              </p>
            )}
          </div>
        </div>

        {sourceGroup?.tiene_examen_programado && (
          <Alert className="border-warn-border bg-warn-soft text-warn-fg">
            <AlertTriangle className="size-4" />
            <AlertTitle>Tiene un examen programado en el grupo de origen</AlertTitle>
            <AlertDescription>
              Seguirá asignada a ese examen salvo que la quite en Programados.
            </AlertDescription>
          </Alert>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="secondary" onClick={onCancel} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button
            onClick={handleConfirm}
            disabled={
              isSubmitting ||
              sourceId === null ||
              targetId === null ||
              sourceId === targetId
            }
          >
            {isSubmitting ? 'Moviendo…' : 'Mover'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}