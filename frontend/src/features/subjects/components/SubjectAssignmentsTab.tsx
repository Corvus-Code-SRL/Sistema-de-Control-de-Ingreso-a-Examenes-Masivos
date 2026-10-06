import { useEffect } from 'react'
import { Link2 } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Card } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useSubjectCareerAssignments } from '../hooks/useSubjectCareerAssignments'
import { SubjectCareerAssignmentForm } from './SubjectCareerAssignmentForm'
import { SubjectCareerAssignmentsTable } from './SubjectCareerAssignmentsTable'

const ALL_CAREERS = 'todas'
const CAREER_PARAM = 'carrera'
const MAX_INT4 = 2147483647

/** Solo un entero positivo dentro del rango de int4 es un filtro posible; lo demás es «todas». */
function careerFrom(value: string | null): number | null {
  if (value === null || !/^[0-9]{1,10}$/.test(value)) {
    return null
  }

  const id = Number(value)

  return id >= 1 && id <= MAX_INT4 ? id : null
}

/**
 * Pestaña Asignaciones: formulario para asignar una materia a una carrera y lista de pares,
 * filtrable por carrera.
 *
 * El filtro vive en la URL (`?tab=asignaciones&carrera=9101`) y se aplica en el servidor. Las
 * opciones son las carreras con al menos un par; un valor de la URL que no está entre ellas
 * se descarta. Al confirmar una asignación la lista se vuelve a pedir con el mismo filtro,
 * sin cambiarlo aunque el par nuevo sea de otra carrera.
 */
export function SubjectAssignmentsTab() {
  const [searchParams, setSearchParams] = useSearchParams()
  const requestedCareer = careerFrom(searchParams.get(CAREER_PARAM))

  const { assignments, careers, status, error, reload } = useSubjectCareerAssignments(requestedCareer)

  const careerIsKnown = requestedCareer !== null && careers.some((career) => career.id_carrera === requestedCareer)
  const careerIsUnknown = requestedCareer !== null && status === 'success' && !careerIsKnown
  const hasInvalidParam = searchParams.has(CAREER_PARAM) && requestedCareer === null

  const writeCareer = (careerId: number | null) => {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)

        if (careerId === null) {
          next.delete(CAREER_PARAM)
        } else {
          next.set(CAREER_PARAM, String(careerId))
        }

        return next
      },
      { replace: true }
    )
  }

  useEffect(() => {
    if (careerIsUnknown || hasInvalidParam) {
      writeCareer(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [careerIsUnknown, hasInvalidParam])

  const careerFilter = careerIsKnown ? requestedCareer : null
  const showLoading = status === 'loading' || careerIsUnknown

  return (
    <div className="space-y-6">
      <SubjectCareerAssignmentForm onAssigned={reload} />

      <section aria-labelledby="assignments-heading" className="space-y-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <h2 id="assignments-heading" className="text-base font-semibold">
            Materias asignadas
          </h2>

          <div className="space-y-1.5 sm:w-80">
            <Label htmlFor="career-filter">Filtrar por carrera</Label>

            <Select
              value={careerFilter === null ? ALL_CAREERS : String(careerFilter)}
              onValueChange={(value) => writeCareer(value === ALL_CAREERS ? null : Number(value))}
            >
              <SelectTrigger id="career-filter" className="w-full" aria-label="Filtrar por carrera">
                <SelectValue />
              </SelectTrigger>

              <SelectContent>
                <SelectItem value={ALL_CAREERS}>Todas las carreras</SelectItem>

                {careers.map((career) => (
                  <SelectItem key={career.id_carrera} value={String(career.id_carrera)}>
                    {career.codigo} · {career.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        <Card className="overflow-hidden p-0">
          {showLoading && <LoadingState rows={5} label="Cargando asignaciones" />}

          {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {status === 'success' && !careerIsUnknown && assignments.length === 0 && (
            <EmptyState
              icon={Link2}
              title={
                careerFilter === null
                  ? 'Sin materias asignadas'
                  : 'Esta carrera aún no tiene materias asignadas'
              }
              description={
                careerFilter === null
                  ? 'Todavía no hay materias asignadas a ninguna carrera.'
                  : 'Asigne una materia con el formulario de arriba para verla aquí.'
              }
            />
          )}

          {status === 'success' && !careerIsUnknown && assignments.length > 0 && (
            <SubjectCareerAssignmentsTable assignments={assignments} />
          )}
        </Card>
      </section>
    </div>
  )
}
