import { useEffect } from 'react'
import { CalendarX, Info, Link2 } from 'lucide-react'
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
import { useAcademicPeriods } from '../hooks/useAcademicPeriods'
import { useSubjectCareerAssignments } from '../hooks/useSubjectCareerAssignments'
import type { AcademicPeriod } from '../types/subject.types'
import { SubjectCareerAssignmentForm } from './SubjectCareerAssignmentForm'
import { SubjectCareerAssignmentsTable } from './SubjectCareerAssignmentsTable'

const ALL_CAREERS = 'todas'
const CAREER_PARAM = 'carrera'
const PERIOD_PARAM = 'periodo'
const MAX_INT4 = 2147483647

/** Solo un entero positivo dentro del rango de int4 es un identificador posible; lo demás se descarta. */
function idFrom(value: string | null): number | null {
  if (value === null || !/^[0-9]{1,10}$/.test(value)) {
    return null
  }

  const id = Number(value)

  return id >= 1 && id <= MAX_INT4 ? id : null
}

/** Aviso del formulario cuando el período elegido no es el vigente (solo consulta). */
function readOnlyNoticeFor(currentPeriod: AcademicPeriod | null): string {
  if (currentPeriod === null) {
    return 'Este período es de solo consulta. No hay un período vigente, así que no es posible asignar materias hasta que se marque uno como vigente.'
  }

  return `Este período es de solo consulta. Solo puedes asignar materias en el período vigente (${currentPeriod.etiqueta}).`
}

/**
 * HU-006, vista Asignaciones: ofrece materias a una carrera en un período y lista lo ofrecido.
 *
 * El período y el filtro de carrera viven en la URL (`?periodo=12&carrera=9101`) y se aplican en el
 * servidor. Sin `periodo` o con uno que no existe se usa el vigente (o, si no hay vigente, el más
 * reciente); una carrera que no está entre las del período se descarta. Al confirmar una asignación
 * la lista se vuelve a pedir con los mismos filtros.
 *
 * Solo el período vigente admite asignar: los demás se consultan, con el formulario deshabilitado.
 */
export function SubjectAssignmentsView() {
  const [searchParams, setSearchParams] = useSearchParams()

  const {
    periods,
    status: periodsStatus,
    error: periodsError,
    reload: reloadPeriods,
  } = useAcademicPeriods()

  const requestedPeriod = idFrom(searchParams.get(PERIOD_PARAM))
  const requestedCareer = idFrom(searchParams.get(CAREER_PARAM))

  const periodsReady = periodsStatus === 'success'
  const currentPeriod = periods.find((period) => period.vigente) ?? null
  const chosenPeriod = periods.find((period) => period.id_periodo === requestedPeriod) ?? null
  const period = chosenPeriod ?? currentPeriod ?? periods[0] ?? null

  const periodParamIsInvalid =
    searchParams.has(PERIOD_PARAM) && (requestedPeriod === null || (periodsReady && chosenPeriod === null))
  const noCurrentPeriodNotice = periodsReady && period !== null && currentPeriod === null && chosenPeriod === null

  const { assignments, careers, status, error, reload } = useSubjectCareerAssignments(
    period?.id_periodo ?? null,
    requestedCareer
  )

  const readOnlyNotice = period === null || period.vigente ? null : readOnlyNoticeFor(currentPeriod)

  const careerIsKnown = requestedCareer !== null && careers.some((career) => career.id_carrera === requestedCareer)
  const careerIsUnknown = requestedCareer !== null && status === 'success' && !careerIsKnown
  const careerParamIsInvalid = searchParams.has(CAREER_PARAM) && requestedCareer === null

  const writeParam = (name: string, value: number | null) => {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)

        if (value === null) {
          next.delete(name)
        } else {
          next.set(name, String(value))
        }

        return next
      },
      { replace: true }
    )
  }

  useEffect(() => {
    if (periodParamIsInvalid) {
      writeParam(PERIOD_PARAM, null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [periodParamIsInvalid])

  useEffect(() => {
    if (careerIsUnknown || careerParamIsInvalid) {
      writeParam(CAREER_PARAM, null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [careerIsUnknown, careerParamIsInvalid])

  const careerFilter = careerIsKnown ? requestedCareer : null
  const showLoading = status === 'loading' || careerIsUnknown

  if (periodsStatus === 'loading') {
    return <LoadingState rows={4} label="Cargando períodos" />
  }

  if (periodsStatus === 'error' && periodsError) {
    return (
      <Card className="overflow-hidden p-0">
        <ErrorState error={periodsError} onRetry={reloadPeriods} />
      </Card>
    )
  }

  if (period === null) {
    return (
      <Card className="overflow-hidden p-0">
        <EmptyState
          icon={CalendarX}
          title="No hay períodos académicos"
          description="Sin un período registrado no es posible asignar materias a las carreras."
        />
      </Card>
    )
  }

  return (
    <div className="space-y-6">
      <section aria-labelledby="period-heading" className="space-y-2">
        <div className="space-y-1.5 sm:w-80">
          <Label id="period-heading" htmlFor="period-select">
            Período académico
          </Label>

          <Select
            value={String(period.id_periodo)}
            onValueChange={(value) => writeParam(PERIOD_PARAM, Number(value))}
          >
            <SelectTrigger id="period-select" className="w-full" aria-label="Período académico">
              <SelectValue />
            </SelectTrigger>

            <SelectContent>
              {periods.map((option) => (
                <SelectItem key={option.id_periodo} value={String(option.id_periodo)}>
                  {option.etiqueta}
                  {option.vigente ? ' (vigente)' : ''}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {noCurrentPeriodNotice && (
          <p role="status" className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Info className="size-3.5 shrink-0" aria-hidden="true" />
            No hay un período vigente; se muestra el más reciente.
          </p>
        )}
      </section>

      <SubjectCareerAssignmentForm period={period} readOnlyNotice={readOnlyNotice} onAssigned={reload} />

      <section aria-labelledby="assignments-heading" className="space-y-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <h2 id="assignments-heading" className="text-base font-semibold">
            Materias asignadas en {period.etiqueta}
          </h2>

          <div className="space-y-1.5 sm:w-80">
            <Label htmlFor="career-filter">Filtrar por carrera</Label>

            <Select
              value={careerFilter === null ? ALL_CAREERS : String(careerFilter)}
              onValueChange={(value) => writeParam(CAREER_PARAM, value === ALL_CAREERS ? null : Number(value))}
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
                  ? `Aún no hay materias asignadas en ${period.etiqueta}`
                  : `Esta carrera aún no tiene materias asignadas en ${period.etiqueta}`
              }
              description={
                careerFilter === null
                  ? 'Todavía no hay materias ofrecidas a ninguna carrera en este período.'
                  : readOnlyNotice !== null
                    ? 'Este período es de solo consulta y esta carrera no tuvo materias ofrecidas en él.'
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
