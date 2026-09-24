import { Lock } from 'lucide-react'
import { Label } from '@/components/ui/label'

interface ReadOnlyFieldProps {
  label: string
  value: string
  hint?: string
}

/** Dato de contexto que se muestra pero no se puede editar (materia, docente). */
export function ReadOnlyField({ label, value, hint }: ReadOnlyFieldProps) {
  return (
    <div className="flex flex-col gap-1">
      <Label className="text-xs font-medium text-foreground">{label}</Label>
      <div className="flex h-8.5 items-center gap-2 rounded-md border border-input bg-sunken px-3 py-1.5 text-xs text-muted-foreground">
        <Lock className="size-3.5 shrink-0" aria-hidden="true" />
        <span className="truncate font-normal">{value}</span>
      </div>
      {hint && <p className="text-xs text-muted-foreground leading-normal">{hint}</p>}
    </div>
  )
}
