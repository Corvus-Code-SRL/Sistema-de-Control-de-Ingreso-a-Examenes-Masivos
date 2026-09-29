import { useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
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
import type { Assistant } from '../types/assistant.types'

export interface ExamOption {
  id_examen: number
  nombre_examen: string
}

interface EnableAssistantExamModalProps {
  open: boolean
  assistant: Assistant | null
  exams: ExamOption[]
  isLoading?: boolean
  onConfirm: (examId: number) => void
  onCancel: () => void
}

/**
 * Modal de habilitación de un auxiliar para un examen.
 *
 * El docente elige el examen de la lista; el backend verifica que el auxiliar
 * pertenezca a algún grupo vinculado al examen y que no sea estudiante del
 * mismo examen.
 */
export function EnableAssistantExamModal({
  open,
  assistant,
  exams,
  isLoading = false,
  onConfirm,
  onCancel,
}: EnableAssistantExamModalProps) {
  const [examId, setExamId] = useState<number | null>(null)

  function handleConfirm() {
    if (examId !== null) {
      onConfirm(examId)
    }
  }

  function handleOpenChange(next: boolean) {
    if (!next) {
      setExamId(null)
      onCancel()
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>Habilitar auxiliar para examen</DialogTitle>
          {assistant && (
            <DialogDescription>
              {assistant.nombre_completo} ·{' '}
              <span className="font-mono">{assistant.cod_sis}</span>
            </DialogDescription>
          )}
        </DialogHeader>

        <div className="space-y-2">
          <Label htmlFor="exam-select">Examen</Label>
          <Select
            value={examId !== null ? String(examId) : undefined}
            onValueChange={(value) => setExamId(Number(value))}
            disabled={isLoading}
          >
            <SelectTrigger id="exam-select">
              <SelectValue placeholder="Seleccione un examen" />
            </SelectTrigger>
            <SelectContent>
              {exams.map((exam) => (
                <SelectItem key={exam.id_examen} value={String(exam.id_examen)}>
                  {exam.nombre_examen}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          {exams.length === 0 && (
            <p className="text-xs text-muted-foreground">
              No hay exámenes programados con grupos vinculados a este auxiliar.
            </p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={onCancel} disabled={isLoading}>
            Cancelar
          </Button>
          <Button onClick={handleConfirm} disabled={isLoading || examId === null}>
            {isLoading ? 'Habilitando…' : 'Habilitar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}