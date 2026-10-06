import { useState } from 'react'
import { Link2 } from 'lucide-react'
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
import { useAdminCareers } from '../hooks/useAdminCareers'
import { useSubjectCareerAssignments } from '../hooks/useSubjectCareerAssignments'
import { SubjectCareerAssignmentForm } from './SubjectCareerAssignmentForm'
import { SubjectCareerAssignmentsTable } from './SubjectCareerAssignmentsTable'

const ALL_CAREERS = 'todas'

/**
 * Pestaña Asignaciones: formulario para asignar una materia a una carrera y lista de pares,
 * filtrable por carrera. Al confirmar una asignación la lista se vuelve a pedir, sin recargar la página.
 */
export function SubjectAssignmentsTab() {
  const [careerFilter, setCareerFilter] = useState<number | null>(null)

  const { careers } = useAdminCareers()
  const { assignments, status, error, reload } = useSubjectCareerAssignments(careerFilter)

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
              onValueChange={(value) => setCareerFilter(value === ALL_CAREERS ? null : Number(value))}
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
          {status === 'loading' && <LoadingState rows={5} label="Cargando asignaciones" />}

          {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {status === 'success' && assignments.length === 0 && (
            <EmptyState
              icon={Link2}
              title="Sin materias asignadas"
              description={
                careerFilter === null
                  ? 'Todavía no hay materias asignadas a ninguna carrera.'
                  : 'Esta carrera todavía no tiene materias asignadas.'
              }
            />
          )}

          {status === 'success' && assignments.length > 0 && (
            <SubjectCareerAssignmentsTable assignments={assignments} />
          )}
        </Card>
      </section>
    </div>
  )
}
