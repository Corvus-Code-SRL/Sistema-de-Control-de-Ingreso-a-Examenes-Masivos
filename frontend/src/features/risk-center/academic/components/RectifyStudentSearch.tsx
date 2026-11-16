import { useState, type FormEvent } from 'react'
import { Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { validateSearchTerm } from '../utils/rectificationValidators'

interface RectifyStudentSearchProps {
  onSearch: (term: string) => void
  isSearching: boolean
}

/** Un solo cuadro para buscar al estudiante por código SIS, carnet de identidad o nombre. */
export function RectifyStudentSearch({ onSearch, isSearching }: RectifyStudentSearchProps) {
  const [term, setTerm] = useState('')
  const [error, setError] = useState<string | null>(null)

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const message = validateSearchTerm(term)
    setError(message)

    if (message === null) onSearch(term.trim())
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-2" aria-label="Buscar estudiante">
      <Label htmlFor="rectify-student-search" className="text-xs font-medium">
        Código SIS, carnet de identidad o nombre del estudiante
      </Label>

      <div className="flex flex-col gap-2 sm:flex-row">
        <div className="relative flex-1">
          <Search
            className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <Input
            id="rectify-student-search"
            value={term}
            onChange={(event) => setTerm(event.target.value)}
            maxLength={60}
            autoComplete="off"
            placeholder="Ej.: 202312345, 7654321 o Pérez"
            aria-invalid={error !== null || undefined}
            aria-describedby={error === null ? undefined : 'rectify-student-search-error'}
            className="h-11 pl-9"
          />
        </div>

        <Button type="submit" className="h-11 sm:w-32" disabled={isSearching}>
          {isSearching ? 'Buscando…' : 'Buscar'}
        </Button>
      </div>

      {error !== null && (
        <p id="rectify-student-search-error" role="alert" className="text-xs text-danger-fg">
          {error}
        </p>
      )}
    </form>
  )
}
