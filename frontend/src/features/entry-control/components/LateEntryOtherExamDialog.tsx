import { Button } from '@/components/ui/button'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

interface LateEntryOtherExamDialogProps {
  open: boolean
  studentName: string
  busy: boolean
  onConfirm: () => void
  onOpenChange: (open: boolean) => void
}

/** Confirmación del docente antes de registrar pese a la advertencia de otro examen. */
export function LateEntryOtherExamDialog({
  open, studentName, busy, onConfirm, onOpenChange,
}: LateEntryOtherExamDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{LATE_ENTRY_MESSAGES.otherExamConfirmTitle}</DialogTitle>
          <DialogDescription>{LATE_ENTRY_MESSAGES.otherExamConfirmDescription(studentName)}</DialogDescription>
        </DialogHeader>
        <DialogFooter className="flex-col gap-2 sm:flex-row">
          <Button type="button" variant="outline" className="h-11 w-full sm:w-auto" disabled={busy}
            onClick={() => onOpenChange(false)}>
            {LATE_ENTRY_MESSAGES.cancel}
          </Button>
          <Button type="button" className="h-11 w-full sm:w-auto" disabled={busy} onClick={onConfirm}>
            {LATE_ENTRY_MESSAGES.otherExamConfirm}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
