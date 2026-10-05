import { useState } from 'react'
import { CalendarMinus } from 'lucide-react'
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
import type { AssistantWithGroups } from '../types/assistant.types'

interface RemoveAssistantFromExamModalProps {
  open: boolean
  assistant: AssistantWithGroups | null
  onConfirm: (examId: number) => Promise<void>
  onCancel: () => void
}

/**
 * Modal para quitar un auxiliar de un examen.
 *
 * Solo se listan los exámenes PROGRAMADOS donde el auxiliar está habilitado.
 */
export function RemoveAssistantFromExamModal({
  open,
  assistant,
  onConfirm,
  onCancel,
}: RemoveAssistantFromExamModalProps) {
  const [examId, setExamId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const exam = assistant?.examenes.find((e) => e.id_examen === examId)

  async function handleConfirm() {
    if (examId === null) return

    setIsSubmitting(true)
    try {
      await onConfirm(examId)
      setExamId(null)
    } finally {
      setIsSubmitting(false)
    }
  }

  function handleClose() {
    setExamId(null)
    onCancel()
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
      <DialogContent className="sm:max-w-[520px]">
        <DialogHeader>
          <div className="flex items-center gap-3">
            <span className="flex size-10 items-center justify-center rounded-full bg-destructive/10 text-destructive">
              <CalendarMinus className="size-5" />
            </span>
            <DialogTitle>
              ¿Quitar a {assistant?.nombre_completo ?? 'auxiliar'} de un examen?
            </DialogTitle>
          </div>
        </DialogHeader>

        <div className="space-y-2">
          <Label>
            Examen <span className="text-destructive" aria-hidden="true">*</span>
          </Label>
          <Select
            value={examId !== null ? String(examId) : undefined}
            onValueChange={(v) => setExamId(Number(v))}
            disabled={isSubmitting}
          >
            <SelectTrigger>
              <SelectValue placeholder="Seleccione un examen" />
            </SelectTrigger>
            <SelectContent>
              {(assistant?.examenes ?? []).map((e) => (
                <SelectItem key={e.id_examen} value={String(e.id_examen)}>
                  {e.nombre_examen}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {exam && (
          <p className="text-sm text-muted-foreground">
            Dejará de estar habilitada para <strong>{exam.nombre_examen}</strong>
            {' '}({formatDate(exam.fecha)}).
          </p>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button
            variant="danger"
            onClick={handleConfirm}
            disabled={isSubmitting || examId === null}
          >
            {isSubmitting ? 'Quitando…' : 'Quitar del examen'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

/** Convierte "2026-09-29" en "29/09". */
function formatDate(isoDate: string): string {
  const [year, month, day] = isoDate.split('-')
  return `${day}/${month}`
}