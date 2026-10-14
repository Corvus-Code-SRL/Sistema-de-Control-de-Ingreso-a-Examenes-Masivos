import { useEffect, useState } from 'react'
import { entryControlService } from '../services/entryControlService'
import type { StudentMatch } from '../types/entry-control.types'

interface Props {
  examId: number
  field: 'sis' | 'ci'
  query: string
  disabled: boolean
  onSelect: (student: StudentMatch) => void
}

export function IdentifierSuggestions({ examId, field, query, disabled, onSelect }: Props) {
  const [matches, setMatches] = useState<StudentMatch[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [searchedQuery, setSearchedQuery] = useState<string | null>(null)
  const [selectedQuery, setSelectedQuery] = useState<string | null>(null)
  const minLength = field === 'sis' ? 3 : 4
  const label = field === 'sis' ? 'código SIS' : 'CI'

  useEffect(() => {
    if (selectedQuery !== null && query !== selectedQuery) setSelectedQuery(null)
  }, [query, selectedQuery])

  useEffect(() => {
    if (disabled || query.length < minLength || query === selectedQuery) {
      setMatches([])
      setSearchedQuery(null)
      setError(null)
      setLoading(false)
      return
    }

    setMatches([])
    setSearchedQuery(null)
    setError(null)
    setLoading(false)
    const controller = new AbortController()
    const timer = window.setTimeout(async () => {
      setLoading(true)
      try {
        const found = await entryControlService.searchByIdentifier(examId, field, query, controller.signal)
        if (!controller.signal.aborted) {
          setMatches(found)
          setSearchedQuery(query)
        }
      } catch (cause) {
        if (!controller.signal.aborted) {
          setError(cause instanceof Error ? cause.message : 'No se pudo buscar.')
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false)
      }
    }, 300)

    return () => { window.clearTimeout(timer); controller.abort() }
  }, [disabled, examId, field, minLength, query, selectedQuery])

  if (disabled || query.length < minLength || query === selectedQuery) return null

  return (
    <div className="space-y-1">
      {loading && <p role="status" className="text-xs text-muted-foreground">Buscando por {label}…</p>}
      {error && <p role="alert" className="text-xs text-danger-fg">{error}</p>}
      {!loading && searchedQuery === query && !error && matches.length === 0 && (
        <p role="status" className="text-xs text-muted-foreground">No hay coincidencias por {label}.</p>
      )}
      {searchedQuery === query && matches.length > 0 && (
        <ul aria-label={`Sugerencias por ${label}`} className="max-h-52 divide-y overflow-y-auto rounded-md border border-border bg-card shadow-sm">
          {matches.map((student) => (
            <li key={student.id_estudiante}>
              <button
                type="button"
                className="flex w-full flex-wrap items-center justify-between gap-1 px-3 py-2.5 text-left hover:bg-brand-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onClick={() => {
                  setSelectedQuery(field === 'sis' ? student.cod_sis : query)
                  setMatches([])
                  onSelect(student)
                }}
              >
                {field === 'ci' ? (
                  <span className="sciem-tnum text-sm font-medium">{student.ci ?? query}</span>
                ) : (
                  <>
                    <span className="text-sm font-medium">{student.nombre_completo}</span>
                    <span className="sciem-tnum text-xs text-muted-foreground">{student.cod_sis}</span>
                  </>
                )}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
