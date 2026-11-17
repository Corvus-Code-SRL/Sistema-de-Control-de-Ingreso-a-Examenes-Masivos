import { Clock3, MapPin } from 'lucide-react'
import type { EntryGroupProgress, RecentEntry } from '../types/entry-control.types'

interface RecentEntriesProps {
  entries: RecentEntry[]
  /**
   * Avance por grupo cuando quien invitó pidió la lista separada por grupo (HU-027). Sin él, la lista va junta
   * como siempre.
   */
  groups?: EntryGroupProgress[]
}

export function RecentEntries({ entries, groups }: RecentEntriesProps) {
  const byGroup = groups !== undefined && groups.length > 0

  return <section aria-labelledby="recent-heading" className="rounded-xl border bg-card p-4 shadow-sm sm:p-6">
    <h2 id="recent-heading" className="sciem-h2">Últimos ingresos</h2>
    {byGroup && <ul aria-label="Avance por grupo" className="mt-3 grid gap-2 sm:grid-cols-2">
      {groups.map((group) => <li key={group.id_grupo} className="rounded-lg bg-sunken px-3 py-2 text-sm">
        <p className="font-medium">{groupTitle(group)}</p>
        <p className="sciem-tnum text-xs text-muted-foreground">{group.ingresados} de {group.total} ingresados</p>
      </li>)}
    </ul>}
    {entries.length === 0 ? <p className="mt-4 text-sm text-muted-foreground">Aún no se registraron ingresos.</p>
      : byGroup ? <div tabIndex={0} className="mt-3 max-h-80 space-y-4 overflow-y-auto overscroll-contain pr-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:max-h-96">
        {sections(entries, groups).map(({ key, title, items }) => <section key={key} aria-label={title}>
          <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{title}</h3>
          <EntryList entries={items} />
        </section>)}
      </div>
      : <EntryList
        entries={entries}
        label="recent-heading"
        className="mt-3 max-h-80 overflow-y-auto overscroll-contain pr-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:max-h-96"
      />}
  </section>
}

function groupTitle(group: EntryGroupProgress): string {
  return `Grupo ${group.num_grupo}${group.docente ? ` · ${group.docente}` : ''}`
}

/** Los últimos ingresos repartidos por grupo, en el orden de los grupos; los que no tienen grupo, al final. */
function sections(entries: RecentEntry[], groups: EntryGroupProgress[]) {
  const result = groups
    .map((group) => ({
      key: String(group.id_grupo),
      title: groupTitle(group),
      items: entries.filter((entry) => entry.id_grupo === group.id_grupo),
    }))
    .filter((section) => section.items.length > 0)
  const known = new Set(groups.map((group) => group.id_grupo))
  const others = entries.filter((entry) => entry.id_grupo == null || !known.has(entry.id_grupo))
  if (others.length > 0) result.push({ key: 'otros', title: 'Sin grupo', items: others })
  return result
}

function EntryList({ entries, label, className }: { entries: RecentEntry[]; label?: string; className?: string }) {
  // La lista junta conserva su desplazamiento propio (y su foco con teclado), como antes de HU-027.
  return <ol
    aria-labelledby={label}
    tabIndex={className ? 0 : undefined}
    className={`divide-y divide-border${className ? ` ${className}` : ''}`}
  >
    {entries.map((entry) => <li key={`${entry.id_estudiante}-${entry.registrado_en ?? entry.hora_ingreso}`} className="flex items-start justify-between gap-3 py-3">
      <div className="min-w-0">
        <p className="font-medium">{[entry.nombre, entry.apellido_paterno, entry.apellido_materno].filter(Boolean).join(' ')}</p>
        <p className="sciem-tnum text-xs text-muted-foreground">{entry.cod_sis} · por {[entry.controlador_nombre, entry.controlador_apellido].filter(Boolean).join(' ') || '—'}</p>
        <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground"><MapPin className="size-3.5" aria-hidden="true" />Ambiente {entry.nro_aula ?? 'no registrado'}</p>
      </div>
      <span className="sciem-tnum flex shrink-0 items-center gap-1 text-xs text-muted-foreground"><Clock3 className="size-3.5" aria-hidden="true" />{entry.hora_ingreso?.slice(0, 5) ?? '—'}</span>
    </li>)}
  </ol>
}
