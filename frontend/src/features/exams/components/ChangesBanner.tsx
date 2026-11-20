import { AlertTriangle, Check, Undo2 } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface ChangesBannerProps {
  count: number
  isSaving: boolean
  onSave: () => void
  onDiscard: () => void
}

export function ChangesBanner({ count, isSaving, onSave, onDiscard }: ChangesBannerProps) {
  if (count === 0) return null

  return (
    <div
      className="flex flex-wrap items-center gap-3 rounded-xl border border-warn-border bg-warn-soft px-4 py-3"
      aria-live="polite"
    >
      <AlertTriangle className="size-5 text-warn-fg" aria-hidden="true" />
      <span className="flex-1 text-sm font-medium text-warn-fg">
        {count} {count === 1 ? 'cambio sin guardar' : 'cambios sin guardar'}. La puerta sigue usando la lista anterior hasta que guarde.
      </span>
      <Button variant="ghost" size="sm" onClick={onDiscard} disabled={isSaving}>
        <Undo2 aria-hidden="true" className="size-4" />
        Descartar
      </Button>
      <Button size="sm" onClick={onSave} disabled={isSaving}>
        <Check aria-hidden="true" className="size-4" />
        {isSaving ? 'Guardando…' : 'Guardar cambios'}
      </Button>
    </div>
  )
}
