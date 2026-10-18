import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'

interface Props {
  open: boolean
  studentName: string
  examId: number
  studentId: number
  roomId: number
  attemptId: number | null
  onClose: () => void
}

/** Punto de integración temporal: HU-042 sustituirá este contenido por su formulario. */
export function IncidentReportHandoffDialog({ open, studentName, examId, studentId, roomId, attemptId, onClose }: Props) {
  return <Dialog open={open} onOpenChange={(value) => { if (!value) onClose() }}>
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Reportar incidencia</DialogTitle>
        <DialogDescription>Estudiante: {studentName}</DialogDescription>
      </DialogHeader>
      <p className="text-sm text-muted-foreground">El formulario de la HU-042 todavía no está disponible. El intento de ingreso ya quedó registrado.</p>
      <p className="sr-only">Examen {examId}, estudiante {studentId}, ambiente {roomId}{attemptId ? `, intento ${attemptId}` : ''}</p>
      <DialogFooter><Button type="button" variant="outline" onClick={onClose}>Volver al control de ingreso</Button></DialogFooter>
    </DialogContent>
  </Dialog>
}
