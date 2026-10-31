import { RotateCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

/** Cierra el resultado y vuelve a la identificación del siguiente estudiante. */
export function LateEntryNextButton({ onNext }: { onNext: () => void }) {
  return (
    <Button type="button" variant="outline" className="h-11 flex-1" onClick={onNext}>
      <RotateCcw className="size-4" aria-hidden="true" />
      {LATE_ENTRY_MESSAGES.nextStudent}
    </Button>
  )
}
