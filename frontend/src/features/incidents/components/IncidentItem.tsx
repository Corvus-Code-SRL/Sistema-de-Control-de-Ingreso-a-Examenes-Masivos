import { cn } from '@/lib/utils'
import { IncidentStateBadge } from './IncidentStateBadge'
import type { IncidentReview } from '../types/incident.types'

interface IncidentItemProps {
  incident: IncidentReview
  isSelected: boolean
  onSelect: () => void
  showDecision: boolean
}

export function IncidentItem({ incident, isSelected, onSelect, showDecision }: IncidentItemProps) {
  const initials = getInitials(incident.persona.nombre_completo)
  const time = formatShortDate(incident.fecha_reporte)

  return (
    <button
      type="button"
      onClick={onSelect}
      className={cn(
        'flex w-full items-center gap-3 border-b border-border px-4 py-3.5 text-left transition-colors last:border-b-0',
        isSelected ? 'bg-brand-soft shadow-[inset_3px_0_0_var(--color-brand)]' : 'hover:bg-sunken'
      )}
    >
      <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-bold text-brand-deep">
        {initials}
      </span>

    <div className="min-w-0 flex-1">
    <p className="truncate text-sm font-semibold text-foreground">
        {incident.persona.nombre_completo}
    </p>
    <p className="truncate text-xs text-muted-foreground">
        {incident.tipo_falta.nombre}
    </p>
    <p className="truncate text-xs text-muted-foreground">
        {showDecision
        ? incident.examen.nombre_examen
        : `Reportó ${incident.reportante.nombre_completo} (${incident.reportante.rol})`}
    </p>
    </div>

      <div className="flex shrink-0 flex-col items-end gap-1">
        {showDecision ? (
          <>
            <IncidentStateBadge estado={incident.estado} />
            {incident.en_central_riesgo && (
              <span className="inline-flex items-center gap-1 rounded-full bg-danger-soft px-2 py-0.5 text-[10px] font-semibold text-danger-fg">
                En Central de Riesgo
              </span>
            )}
          </>
        ) : (
          <span className="sciem-tnum text-xs text-muted-foreground">{time}</span>
        )}
      </div>
    </button>
  )
}

function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

function formatShortDate(iso: string): string {
  const d = new Date(iso)
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${day}/${month} ${hours}:${minutes}`
}