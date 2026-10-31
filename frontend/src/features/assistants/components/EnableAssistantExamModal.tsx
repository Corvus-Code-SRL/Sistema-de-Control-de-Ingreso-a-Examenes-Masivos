import { useState } from 'react'
import { AlertCircle } from 'lucide-react'
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
import type { AssistantWithGroups } from '../types/assistant.types'

interface EnableAssistantExamModalProps {
  open: boolean
  assistant: AssistantWithGroups | null
  onConfirm: (examId: number) => Promise<void>
  onCancel: () => void
  error?: string | null
}

/**
 * Modal para habilitar un auxiliar para un examen.
 *
 * Solo muestra exámenes donde el auxiliar PUEDE ser habilitado (el backend
 * ya filtró: grupo vinculado, no habilitado ya, no es estudiante).
 */
export function EnableAssistantExamModal({
  open,
  assistant,
  onConfirm,
  onCancel,
  error = null,
}: EnableAssistantExamModalProps) {
  const [examId, setExamId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const availableExams = assistant?.examenes_disponibles ?? []

  async function handleConfirm() {
    if (examId === null) return

    setIsSubmitting(true)
    try {
      await onConfirm(examId)
      setExamId(null)
    } catch {
      // El error lo maneja el padre.
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
      <DialogContent className="sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>Habilitar para examen</DialogTitle>
        </DialogHeader>

        {assistant && (
          <div className="flex items-center gap-3 rounded-md border border-input bg-muted/30 px-3 py-2">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand text-brand-foreground text-xs font-medium">
              {initialsOf(assistant)}
            </span>
            <div className="min-w-0">
              <p className="text-sm font-medium truncate">{assistant.nombre_completo}</p>
              <p className="text-xs text-muted-foreground font-mono">
                {assistant.cod_sis}
              </p>
            </div>
          </div>
        )}

        <div className="space-y-2">
          <Label>
            Examen <span className="text-destructive" aria-hidden="true">*</span>
          </Label>
          <Select
            value={examId !== null ? String(examId) : undefined}
            onValueChange={(v) => setExamId(Number(v))}
            disabled={isSubmitting || availableExams.length === 0}
          >
            <SelectTrigger>
              <SelectValue placeholder="Seleccione un examen" />
            </SelectTrigger>
            <SelectContent>
              {availableExams.map((exam) => (
                <SelectItem key={exam.id_examen} value={String(exam.id_examen)}>
                  {exam.nombre_examen}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {availableExams.length === 0 && (
            <Alert className="border-info-border bg-info-soft text-info py-2 px-3">
              <AlertCircle className="size-4" />
              <AlertTitle>No hay exámenes disponibles</AlertTitle>
              <AlertDescription>
                Este auxiliar no tiene exámenes donde pueda ser habilitado.
                Asegúrese de que su grupo esté vinculado a un examen programado
                y que no sea estudiante del mismo.
              </AlertDescription>
            </Alert>
          )}

          {error && (
            <Alert className="border-danger-border bg-danger-soft text-danger-fg py-2 px-3">
              <AlertDescription className="text-xs font-medium">
                {error}
              </AlertDescription>
            </Alert>
          )}
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
            Cancelar
          </Button>
          <Button
            variant="default"
            onClick={handleConfirm}
            disabled={isSubmitting || examId === null}
          >
            {isSubmitting ? 'Habilitando…' : 'Habilitar'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

function initialsOf(assistant: AssistantWithGroups): string {
  const n = assistant.nombre?.[0] ?? ''
  const a = assistant.apellido_paterno?.[0] ?? ''
  return (n + a).toUpperCase()
}