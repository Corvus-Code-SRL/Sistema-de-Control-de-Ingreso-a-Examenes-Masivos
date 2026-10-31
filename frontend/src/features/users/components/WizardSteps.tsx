import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

interface WizardStepsProps {
  steps: readonly string[]
  /** Posición del paso actual, desde 1. */
  current: number
}

/** Indicador de pasos del asistente de registro: hechos, actual y pendientes. */
export function WizardSteps({ steps, current }: WizardStepsProps) {
  return (
    <ol aria-label="Pasos del registro" className="flex flex-wrap items-center gap-x-4 gap-y-2">
      {steps.map((label, index) => {
        const position = index + 1
        const isCurrent = position === current
        const isDone = position < current

        return (
          <li
            key={label}
            aria-current={isCurrent ? 'step' : undefined}
            className="flex items-center gap-2 text-sm"
          >
            <span
              aria-hidden="true"
              className={cn(
                'flex size-6 items-center justify-center rounded-full border text-xs font-semibold',
                isCurrent && 'border-brand bg-brand text-white',
                isDone && 'border-brand bg-brand-soft text-brand-deep',
                !isCurrent && !isDone && 'border-border-strong text-muted-foreground'
              )}
            >
              {isDone ? <Check className="size-3.5" /> : position}
            </span>
            <span className={cn(isCurrent ? 'font-semibold text-foreground' : 'text-muted-foreground')}>
              <span className="sr-only">Paso {position} de {steps.length}: </span>
              {label}
            </span>
          </li>
        )
      })}
    </ol>
  )
}
