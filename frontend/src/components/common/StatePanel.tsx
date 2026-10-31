import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Panel de estado que ocupa el lugar de un formulario cuando reintentar no sirve de nada.
 *
 * El color dice qué puede hacer la persona: gris para lo que no depende de ella, azul para lo que tiene
 * una acción concreta, verde para lo que salió bien.
 */
type StatePanelTone = 'neutral' | 'info' | 'ok'

const TONES: Record<StatePanelTone, { panel: string; icon: string; title: string; body: string }> = {
  neutral: {
    panel: 'border-border-soft bg-neutral-soft',
    icon: 'bg-neutral-mid text-neutral-fg',
    title: 'text-foreground',
    body: 'text-neutral-fg',
  },
  info: {
    panel: 'border-info-border bg-info-soft',
    icon: 'bg-info-mid text-info',
    title: 'text-info',
    body: 'text-info/90',
  },
  ok: {
    panel: 'border-ok-border bg-ok-soft',
    icon: 'bg-ok-mid text-ok-fg',
    title: 'text-ok-fg',
    body: 'text-ok-fg/90',
  },
}

interface StatePanelProps {
  tone: StatePanelTone
  icon: LucideIcon
  title: string
  children?: ReactNode
  className?: string
  /** `status` para un estado que se anuncia solo (éxito); sin rol para un panel estático. */
  role?: 'status'
}

export function StatePanel({ tone, icon: Icon, title, children, className, role }: StatePanelProps) {
  const styles = TONES[tone]

  return (
    <div
      role={role}
      className={cn(
        'flex flex-col items-center gap-3.5 rounded-xl border px-6 py-7 text-center',
        styles.panel,
        className
      )}
    >
      <span
        aria-hidden="true"
        className={cn('flex size-14 items-center justify-center rounded-full', styles.icon)}
      >
        <Icon className="size-7" />
      </span>

      <h2 className={cn('text-xl leading-7 font-semibold', styles.title)}>{title}</h2>

      {children && <div className={cn('space-y-2 text-sm leading-[21px]', styles.body)}>{children}</div>}
    </div>
  )
}
