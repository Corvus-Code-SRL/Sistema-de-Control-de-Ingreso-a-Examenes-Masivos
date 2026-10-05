import { SUPPORT_EMAIL, supportMailto } from '../lib/support'
import { cn } from '@/lib/utils'

export const LINK_CLASSNAME =
  'rounded-sm font-semibold text-brand outline-none hover:underline focus-visible:ring-3 focus-visible:ring-ring/50'

/** «¿Problemas para ingresar?»: la salida real para quien no puede entrar por ningún camino. */
export function SupportFooter({ sis, className }: { sis?: string; className?: string }) {
  return (
    <p className={cn('text-center text-xs leading-[18px] text-muted-foreground', className)}>
      ¿Problemas para ingresar?
      <br />
      Escriba a{' '}
      <a href={supportMailto(sis)} className={LINK_CLASSNAME}>
        {SUPPORT_EMAIL}
      </a>
    </p>
  )
}
