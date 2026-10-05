import { Clock3 } from 'lucide-react'
import type { RecentEntry } from '../types/entry-control.types'

export function RecentEntries({ entries }: { entries: RecentEntry[] }) {
  return <section aria-labelledby="recent-heading" className="rounded-xl border bg-card p-4 shadow-sm sm:p-6">
    <h2 id="recent-heading" className="sciem-h2">Últimos ingresos</h2>
    {entries.length === 0 ? <p className="mt-4 text-sm text-muted-foreground">Aún no se registraron ingresos.</p> :
      <ol className="mt-3 divide-y divide-border">
        {entries.map((entry) => <li key={`${entry.id_estudiante}-${entry.registrado_en ?? entry.hora_ingreso}`} className="flex items-start justify-between gap-3 py-3">
          <div className="min-w-0"><p className="font-medium">{[entry.nombre, entry.apellido_paterno, entry.apellido_materno].filter(Boolean).join(' ')}</p><p className="sciem-tnum text-xs text-muted-foreground">{entry.cod_sis} · por {[entry.controlador_nombre, entry.controlador_apellido].filter(Boolean).join(' ') || '—'}</p></div>
          <span className="sciem-tnum flex shrink-0 items-center gap-1 text-xs text-muted-foreground"><Clock3 className="size-3.5" aria-hidden="true" />{entry.hora_ingreso?.slice(0, 5) ?? '—'}</span>
        </li>)}
      </ol>}
  </section>
}
