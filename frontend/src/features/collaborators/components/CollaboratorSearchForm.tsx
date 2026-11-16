import { useState, type FormEvent } from 'react'
import { Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

/** El servidor exige al menos 2 caracteres: con menos no se gasta una petición. */
export const MIN_SEARCH_LENGTH = 2

interface CollaboratorSearchFormProps {
  onSearch: (criteria: string) => void
  /** Sin examen elegido no hay materia en la que buscar. */
  disabled: boolean
  searching: boolean
}

/** Buscador por nombre o código SIS; la materia sale del examen elegido, no se escribe aquí. */
export function CollaboratorSearchForm({ onSearch, disabled, searching }: CollaboratorSearchFormProps) {
  const [criteria, setCriteria] = useState('')
  const canSearch = !disabled && !searching && criteria.trim().length >= MIN_SEARCH_LENGTH

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    if (canSearch) onSearch(criteria.trim())
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-2">
      <Label htmlFor="collaborator-search">Docente</Label>
      <div className="flex gap-2">
        <Input
          id="collaborator-search"
          value={criteria}
          onChange={(event) => setCriteria(event.target.value)}
          placeholder="Buscar por nombre o código SIS"
          disabled={disabled}
          autoComplete="off"
          className="h-11"
        />
        <Button type="submit" disabled={!canSearch} className="h-11 gap-1.5">
          <Search className="size-4" aria-hidden="true" />
          Buscar
        </Button>
      </div>
      {disabled && <p className="text-xs text-muted-foreground">Elija primero un examen para buscar docentes de su materia.</p>}
    </form>
  )
}
