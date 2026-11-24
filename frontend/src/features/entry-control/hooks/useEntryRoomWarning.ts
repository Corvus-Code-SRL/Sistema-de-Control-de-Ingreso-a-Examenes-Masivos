import { useCallback, useState } from 'react'
import type { EntryRoomWarning } from '../types/entry-control.types'
import { roomWarningsOf } from '../utils/entryRoomWarning'

interface PendingRoomWarning {
  warnings: EntryRoomWarning[]
  retry: () => void
}

/** Guarda el aviso de ambiente de un registro y la acción que lo repite ya confirmado. */
export function useEntryRoomWarning() {
  const [pending, setPending] = useState<PendingRoomWarning | null>(null)

  const capture = useCallback((cause: unknown, retry: () => void): boolean => {
    const warnings = roomWarningsOf(cause)
    if (warnings === null) return false
    setPending({ warnings, retry })
    return true
  }, [])

  const cancel = useCallback(() => setPending(null), [])

  const confirm = useCallback(() => {
    const retry = pending?.retry
    setPending(null)
    retry?.()
  }, [pending])

  return { warnings: pending?.warnings ?? null, capture, confirm, cancel }
}

export type EntryRoomWarningGate = ReturnType<typeof useEntryRoomWarning>
