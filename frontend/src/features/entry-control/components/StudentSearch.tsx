import { useEffect, useState } from 'react'
import { Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { entryControlService } from '../services/entryControlService'
import type { StudentMatch } from '../types/entry-control.types'

interface Props { examId: number; onSelect: (student: StudentMatch) => void; disabled: boolean }

export function StudentSearch({ examId, onSelect, disabled }: Props) {
  const [query, setQuery] = useState('')
  const [matches, setMatches] = useState<StudentMatch[]>([])
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [searchedQuery, setSearchedQuery] = useState<string | null>(null)

  useEffect(() => {
    if (disabled || query.trim().length < 2) {
      setMatches([])
      setError(null)
      setSearchedQuery(null)
      setLoading(false)
      return
    }
    setMatches([])
    setSearchedQuery(null)
    setLoading(false)
    const controller = new AbortController()
    const timer = window.setTimeout(async () => {
      setLoading(true)
      try {
        const found = await entryControlService.search(examId, query.trim(), controller.signal)
        if (!controller.signal.aborted) {
          setMatches(found)
          setSearchedQuery(query.trim())
          setError(null)
        }
      } catch (cause) {
        if (!controller.signal.aborted) setError(cause instanceof Error ? cause.message : 'No se pudo buscar.')
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }, 300)
    return () => { window.clearTimeout(timer); controller.abort() }
  }, [examId, query, disabled])

  return (
    <div className="space-y-2">
      <label htmlFor="student-name" className="block text-sm font-medium">Buscar estudiante por nombre</label>
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" aria-hidden="true" />
        <Input id="student-name" value={query} onChange={(event) => { setQuery(event.target.value); setSearchedQuery(null); setMatches([]); setError(null) }} placeholder="Escriba al menos 2 caracteres" className="h-11 pl-9" autoComplete="off" aria-describedby="student-search-hint" disabled={disabled} />
      </div>
      {!disabled && loading && <p role="status" className="text-sm text-muted-foreground">Buscando…</p>}
      {!disabled && error && <p role="alert" className="text-sm text-danger-fg">{error}</p>}
      {!disabled && !loading && searchedQuery === query.trim() && !error && matches.length === 0 && <p role="status" className="text-sm text-muted-foreground">No hay coincidencias en este examen.</p>}
      {!disabled && matches.length > 0 && <ul aria-label="Coincidencias de estudiantes" className="max-h-52 divide-y overflow-y-auto rounded-md border border-border bg-card">
        {matches.map((student) => <li key={student.id_estudiante}>
          <button type="button" className="flex w-full flex-wrap items-center justify-between gap-1 px-3 py-2.5 text-left hover:bg-brand-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onClick={() => { onSelect(student); setQuery(''); setMatches([]) }}>
            <span className="font-medium">{student.nombre_completo}</span><span className="sciem-tnum text-xs text-muted-foreground">{student.cod_sis}</span>
          </button>
        </li>)}
      </ul>}
    </div>
  )
}
