import { useEffect, useState } from 'react'
import { BookOpen, Search, SearchX } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { useAdminSubjects } from '../hooks/useAdminSubjects'
import { AdminSubjectsTable } from './AdminSubjectsTable'

const SEARCH_DEBOUNCE_MS = 300

/**
 * Pestaña Catálogo: lista de solo lectura con búsqueda por código o nombre.
 *
 * No ofrece crear, editar ni borrar: las materias vienen del catálogo institucional.
 */
export function SubjectCatalogTab() {
  const [input, setInput] = useState('')
  const [search, setSearch] = useState('')

  useEffect(() => {
    const timer = window.setTimeout(() => setSearch(input.trim()), SEARCH_DEBOUNCE_MS)

    return () => window.clearTimeout(timer)
  }, [input])

  const { subjects, status, error, reload } = useAdminSubjects(search)

  return (
    <div className="space-y-4">
      <div className="relative sm:w-96">
        <Search
          aria-hidden="true"
          className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          type="search"
          value={input}
          onChange={(event) => setInput(event.target.value)}
          placeholder="Buscar por código o nombre"
          aria-label="Buscar materias"
          className="pl-8"
        />
      </div>

      <Card className="overflow-hidden p-0">
        {status === 'loading' && <LoadingState rows={5} label="Cargando materias" />}

        {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

        {status === 'success' && subjects.length === 0 && search === '' && (
          <EmptyState
            icon={BookOpen}
            title="Aún no hay materias registradas"
            description="El catálogo institucional todavía no contiene materias."
          />
        )}

        {status === 'success' && subjects.length === 0 && search !== '' && (
          <EmptyState
            icon={SearchX}
            title="Ninguna materia coincide"
            description="Pruebe con otro código o nombre."
          />
        )}

        {status === 'success' && subjects.length > 0 && <AdminSubjectsTable subjects={subjects} />}
      </Card>
    </div>
  )
}
