import { Button } from '@/components/ui/button'

interface Props {
  page: number
  lastPage: number
  onChange: (value: number) => void
  label: string
}

/** Paginación anterior/siguiente compartida por los accesos independientes de HU-057 y HU-042. */
export function ExamPagination({ page, lastPage, onChange, label }: Props) {
  if (lastPage <= 1 && page <= 1) return null
  return <nav aria-label={`Paginación de ${label}`} className="flex flex-wrap items-center gap-3 text-sm">
    <Button type="button" variant="outline" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>Anterior</Button>
    <span>Página {page} de {lastPage}</span>
    <Button type="button" variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>Siguiente</Button>
  </nav>
}
