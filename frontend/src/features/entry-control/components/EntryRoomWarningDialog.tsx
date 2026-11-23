import type { EntryRoomWarningGate } from '../hooks/useEntryRoomWarning'
import { EntryConfirmDialog } from './EntryConfirmDialog'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

interface EntryRoomWarningDialogProps {
  gate: EntryRoomWarningGate
  busy: boolean
}

/** Aviso de ambiente distinto o lleno: informa y deja registrar de todos modos. */
export function EntryRoomWarningDialog({ gate, busy }: EntryRoomWarningDialogProps) {
  const warnings = gate.warnings ?? []
  return (
    <EntryConfirmDialog
      open={gate.warnings !== null}
      title={LATE_ENTRY_MESSAGES.otherExamConfirmTitle}
      description={<ul className="space-y-1">{warnings.map((warning) => <li key={warning.tipo}>{warning.mensaje}</li>)}</ul>}
      confirmLabel={LATE_ENTRY_MESSAGES.otherExamConfirm}
      cancelLabel={LATE_ENTRY_MESSAGES.cancel}
      busy={busy}
      onConfirm={gate.confirm}
      onOpenChange={(open) => { if (!open) gate.cancel() }}
    />
  )
}
