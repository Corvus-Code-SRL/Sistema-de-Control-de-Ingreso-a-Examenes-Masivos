import { useEffect, useState } from 'react'
import { Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { entryControlService } from '../services/entryControlService'
import type { StudentMatch } from '../types/entry-control.types'

interface Props { examId: number; onSelect: (student: StudentMatch) => void }

export function StudentSearch({ examId, onSelect }: Props) {
  const [query, setQuery] = useState('')
  const [matches, setMatches] = useState<StudentMatch[]>([])
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (query.trim().length < 2) {
      setMatches([])
      setError(null)
      return
    }
    setMatches([])
    const controller = new AbortController()
    const timer = window.setTimeout(async () => {
      setLoading(true)
      try {
        setMatches(await entryControlService.search(examId, query.trim(), controller.signal))
        setError(null)
      } catch (cause) {
        if (!controller.signal.aborted) setError(cause instanceof Error ? cause.message : 'No se pudo buscar.')
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }, 300)
    return () => { window.clearTimeout(timer); controller.abort() }
  }, [examId, query])

  return (
    <div className="space-y-3 rounded-lg border border-border bg-sunken p-4">
      <label htmlFor="student-name" className="text-sm font-medium">Buscar estudiante por nombre</label>
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" aria-hidden="true" />
        <Input id="student-name" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Nombre o apellido" className="h-11 pl-9" autoComplete="off" />
      </div>
      <p className="text-xs text-muted-foreground">Seleccione a la persona para completar su código SIS y verificar.</p>
      {loading && <p className="text-sm text-muted-foreground">Buscando…</p>}
      {error && <p role="alert" className="text-sm text-danger-fg">{error}</p>}
      {!loading && query.trim().length >= 2 && !error && matches.length === 0 && <p className="text-sm text-muted-foreground">No hay coincidencias en este examen.</p>}
      {matches.length > 0 && <ul className="max-h-64 divide-y overflow-y-auto rounded-md border bg-card">
        {matches.map((student) => <li key={student.id_estudiante}>
          <button type="button" className="flex w-full flex-wrap items-center justify-between gap-1 px-3 py-2.5 text-left hover:bg-brand-soft focus-visible:bg-brand-soft" onClick={() => { onSelect(student); setQuery(''); setMatches([]) }}>
            <span className="font-medium">{student.nombre_completo}</span><span className="sciem-tnum text-xs text-muted-foreground">{student.cod_sis}</span>
          </button>
        </li>)}
      </ul>}
    </div>
  )
}
