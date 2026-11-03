import type { ReactNode } from 'react'

interface ExamSummaryCardProps {
  title: string
  subject: string | null
  /** Fecha ya formateada, o el texto que la reemplaza cuando falta. */
  dateLabel: string
  startsAt: string | null
  endsAt: string | null
  roomsLabel: string
  rooms: string
  action: ReactNode
}

/** Resumen de un examen en una lista: nombre, materia, fecha y horario, ambientes y una acción. */
export function ExamSummaryCard({
  title,
  subject,
  dateLabel,
  startsAt,
  endsAt,
  roomsLabel,
  rooms,
  action,
}: ExamSummaryCardProps) {
  return (
    <article className="rounded-xl border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 space-y-1">
          <h2 className="sciem-h3">{title}</h2>
          {subject && (
            <p className="text-sm text-muted-foreground">{subject}</p>
          )}
          <p className="text-sm text-muted-foreground">
            {dateLabel}
            {startsAt && endsAt
              ? ` · ${startsAt}–${endsAt}`
              : ''}
          </p>
          <p className="text-sm text-muted-foreground">
            {roomsLabel}: {rooms}
          </p>
        </div>
        {action}
      </div>
    </article>
  )
}
