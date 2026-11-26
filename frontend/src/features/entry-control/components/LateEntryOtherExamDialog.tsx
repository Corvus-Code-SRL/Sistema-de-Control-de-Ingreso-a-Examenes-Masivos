import { EntryConfirmDialog } from './EntryConfirmDialog'
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
    <EntryConfirmDialog
      open={open}
      title={LATE_ENTRY_MESSAGES.otherExamConfirmTitle}
      description={LATE_ENTRY_MESSAGES.otherExamConfirmDescription(studentName)}
      confirmLabel={LATE_ENTRY_MESSAGES.otherExamConfirm}
      cancelLabel={LATE_ENTRY_MESSAGES.cancel}
      busy={busy}
      onConfirm={onConfirm}
      onOpenChange={onOpenChange}
    />
  )
}
