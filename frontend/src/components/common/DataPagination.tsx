import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface DataPaginationProps {
  page: number
  perPage: number
  total: number
  totalPages: number
  onPageChange: (page: number) => void
  /** Sustantivo de lo que se lista, para el resumen: «de 64 materias». */
  itemLabel: string
  disabled?: boolean
}

/**
 * Paginador de una consulta servida por páginas.
 *
 * Solo mueve el número de página: quien decide qué llega es el servidor, así
 * que este componente nunca recorta ni reordena los datos que recibe.
 */
export function DataPagination({
  page,
  perPage,
  total,
  totalPages,
  onPageChange,
  itemLabel,
  disabled = false,
}: DataPaginationProps) {
  if (total === 0) return null

  const first = (page - 1) * perPage + 1
  const last = Math.min(page * perPage, total)

  return (
    <nav
      aria-label="Paginación"
      className="flex flex-col gap-3 border-t border-border-soft px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <p className="text-sm text-muted-foreground" aria-live="polite">
        Mostrando {first}–{last} de {total} {itemLabel}
      </p>

      <div className="flex items-center gap-1">
        <Button
          variant="outline"
          size="sm"
          disabled={disabled || page <= 1}
          onClick={() => onPageChange(page - 1)}
        >
          <ChevronLeft className="size-4" />
          <span className="sr-only sm:not-sr-only">Anterior</span>
        </Button>

        <span className="sciem-tnum px-2 text-sm text-muted-foreground">
          Página {page} de {totalPages}
        </span>

        <Button
          variant="outline"
          size="sm"
          disabled={disabled || page >= totalPages}
          onClick={() => onPageChange(page + 1)}
        >
          <span className="sr-only sm:not-sr-only">Siguiente</span>
          <ChevronRight className="size-4" />
        </Button>
      </div>
    </nav>
  )
}
