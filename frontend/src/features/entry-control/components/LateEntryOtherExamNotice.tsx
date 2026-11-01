import { ShieldAlert } from 'lucide-react'
import type { OtherExamWarning } from '../types/late-entry.types'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

/** Advertencia de ingreso en otro examen abierto; el mismo aviso en tono warn del control de ingreso. */
export function LateEntryOtherExamNotice({ warning }: { warning: OtherExamWarning }) {
  return (
    <div role="status" className="rounded-lg border border-warn-border bg-warn-soft p-3 text-sm">
      <p className="flex items-center gap-2 font-semibold text-warn-fg">
        <ShieldAlert className="size-4 shrink-0" aria-hidden="true" />
        {LATE_ENTRY_MESSAGES.otherExamTitle}
      </p>
      <p className="mt-1 break-words">{LATE_ENTRY_MESSAGES.otherExamDetail(warning)}</p>
    </div>
  )
}
