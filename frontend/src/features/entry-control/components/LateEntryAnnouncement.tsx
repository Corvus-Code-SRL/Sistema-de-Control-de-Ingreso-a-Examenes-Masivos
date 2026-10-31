import { X } from 'lucide-react'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

interface LateEntryAnnouncementProps {
  message: string | null
  error?: string | null
  onClose: () => void
}

/**
 * Avisos del ingreso con retraso con los mismos bloques del control de ingreso. La región
 * aria-live queda montada aunque esté vacía, así el lector de pantalla anuncia cada aviso nuevo.
 */
export function LateEntryAnnouncement({ message, error = null, onClose }: LateEntryAnnouncementProps) {
  return (
    <div className="space-y-2">
      <div aria-live="polite" aria-atomic="true">
        {message && (
          <div className="flex items-center justify-between gap-2 rounded-lg border border-ok/30 bg-ok-soft p-3 text-sm font-medium text-ok-fg">
            <span className="min-w-0 break-words">{message}</span>
            <button
              type="button"
              onClick={onClose}
              aria-label={LATE_ENTRY_MESSAGES.closeNotice}
              className="flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-ok/10"
            >
              <X className="size-4" aria-hidden="true" />
            </button>
          </div>
        )}
      </div>
      {error && (
        <p role="alert" className="rounded-lg border border-danger/30 bg-danger-soft p-3 text-sm text-danger-fg">
          {error}
        </p>
      )}
    </div>
  )
}
