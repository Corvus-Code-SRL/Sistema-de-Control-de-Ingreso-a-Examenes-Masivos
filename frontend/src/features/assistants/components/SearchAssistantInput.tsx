import { useState, type FormEvent } from 'react'
import { Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

interface SearchAssistantInputProps {
  onSearch: (criterion: string) => void
  isLoading?: boolean
  defaultValue?: string
}

/**
 * Buscador por código SIS o nombre.
 *
 * El backend exige al menos 2 caracteres; el botón se deshabilita si no se
 * cumple, para no gastar una petición en un criterio demasiado corto.
 */
export function SearchAssistantInput({
  onSearch,
  isLoading = false,
  defaultValue = '',
}: SearchAssistantInputProps) {
  const [criterion, setCriterion] = useState(defaultValue)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()

    const trimmed = criterion.trim()

    if (trimmed.length >= 2) {
      onSearch(trimmed)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex gap-2">
      <Input
        value={criterion}
        onChange={(event) => setCriterion(event.target.value)}
        placeholder="Buscar por código SIS o nombre"
        disabled={isLoading}
        className="h-9"
      />
      <Button
        type="submit"
        disabled={isLoading || criterion.trim().length < 2}
        className="gap-1.5"
      >
        <Search className="size-4" aria-hidden="true" />
        Buscar
      </Button>
    </form>
  )
}