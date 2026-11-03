import { Cog, Timer } from 'lucide-react'
import type { AuditActor } from '../types/auditLog.types'

interface ActorLabelProps {
  actor: AuditActor
}

/**
 * Autor de una operación. Una persona se muestra con su nombre y su código SIS; «Sistema» y «Automático»
 * llevan icono y texto, nunca solo color.
 */
export function ActorLabel({ actor }: ActorLabelProps) {
  if (actor.tipo === 'automatico') {
    return (
      <span className="inline-flex w-fit items-center gap-1 rounded-full bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand-deep">
        <Timer className="size-3 shrink-0" aria-hidden="true" />
        {actor.etiqueta}
      </span>
    )
  }

  if (actor.tipo === 'sistema') {
    return (
      <span className="inline-flex w-fit items-center gap-1 rounded-full bg-sunken px-2 py-0.5 text-xs font-medium text-foreground">
        <Cog className="size-3 shrink-0" aria-hidden="true" />
        {actor.etiqueta}
      </span>
    )
  }

  return (
    <span className="block min-w-0">
      <span className="block truncate text-sm font-medium text-foreground">{actor.etiqueta}</span>
      {actor.cod_sis && <span className="sciem-tnum block text-xs text-muted-foreground">{actor.cod_sis}</span>}
    </span>
  )
}
