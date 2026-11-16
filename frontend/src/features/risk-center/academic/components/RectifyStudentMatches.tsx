import { ChevronRight } from 'lucide-react'
import type { RectifiableStudent } from '../types/riskRectification.types'

interface RectifyStudentMatchesProps {
  students: RectifiableStudent[]
  limit: number
  hasMore: boolean
  onSelect: (student: RectifiableStudent) => void
}

/** Varios estudiantes coinciden con la búsqueda: se elige uno (nombre completo, código SIS y carnet). */
export function RectifyStudentMatches({ students, limit, hasMore, onSelect }: RectifyStudentMatchesProps) {
  return (
    <div className="space-y-3">
      <p className="text-sm text-muted-foreground">
        Hay {hasMore ? `más de ${limit}` : students.length} estudiantes que coinciden. Elija uno para ver sus
        registros.
      </p>

      <ul aria-label="Estudiantes encontrados" className="divide-y divide-border rounded-lg border border-border">
        {students.map((student) => (
          <li key={student.id_estudiante}>
            <button
              type="button"
              onClick={() => onSelect(student)}
              className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
            >
              <span className="min-w-0">
                <span className="block truncate text-sm font-medium text-foreground">
                  {student.nombre_completo}
                </span>
                <span className="block text-xs text-muted-foreground">
                  Código SIS {student.cod_sis} · Carnet {student.ci ?? 'sin dato'}
                </span>
              </span>
              <ChevronRight className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            </button>
          </li>
        ))}
      </ul>

      {hasMore && (
        <p role="note" className="text-xs text-muted-foreground">
          Se muestran las primeras {limit} coincidencias. Afine la búsqueda con el código SIS, el carnet o más
          letras del nombre.
        </p>
      )}
    </div>
  )
}
