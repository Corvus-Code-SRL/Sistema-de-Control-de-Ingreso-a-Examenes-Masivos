import { useCallback, useEffect, useState } from 'react'
import { CalendarX, GraduationCap, Lock, Search, Unlink, XCircle } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { DataPagination } from '@/components/common/DataPagination'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Toast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api-client'
import { UnassignSubjectDialog } from '../components/UnassignSubjectDialog'
import { UnassignSubjectsTable } from '../components/UnassignSubjectsTable'
import { ReactivateSubjectDialog } from '../components/ReactivateSubjectDialog'
import { useAcademicPeriods } from '../hooks/useAcademicPeriods'
import { useAdminCareers } from '../hooks/useAdminCareers'
import { useUnassignableSubjects } from '../hooks/useUnassignableSubjects'
import { reactivateSubject, unassignSubject } from '../services/unassignSubjectService'
import type { UnassignableSubject } from '../types/unassignSubject.types'

const CAREER_PARAM = 'carrera'
const PERIOD_PARAM = 'periodo'
const MAX_INT4 = 2147483647
const SEARCH_DEBOUNCE_MS = 300

/** Solo un entero positivo dentro del rango de int4 es un identificador posible; lo demás se descarta. */
function idFrom(value: string | null): number | null {
  if (value === null || !/^[0-9]{1,10}$/.test(value)) return null

  const id = Number(value)

  return id >= 1 && id <= MAX_INT4 ? id : null
}

interface Notice {
  title: string
  description: string
}

interface DoneToast {
  title: string
  description: string
}

/**
 * HU-008 · Desvincular materia de carrera.
 *
 * La única escritura es desvincular (la oferta pasa a INACTIVO, no se borra). Carrera y período
 * viven en la URL; el período vigente es el predeterminado. Búsqueda y paginación son del servidor.
 */
export function UnassignSubjectCareerPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const { periods, status: periodsStatus, error: periodsError, reload: reloadPeriods } = useAcademicPeriods()
  const { careers, status: careersStatus, error: careersError, reload: reloadCareers } = useAdminCareers()

  const requestedPeriod = idFrom(searchParams.get(PERIOD_PARAM))
  const requestedCareer = idFrom(searchParams.get(CAREER_PARAM))

  const periodsReady = periodsStatus === 'success'
  const careersReady = careersStatus === 'success'

  const currentPeriod = periods.find((p) => p.vigente) ?? null
  const chosenPeriod = periods.find((p) => p.id_periodo === requestedPeriod) ?? null
  const period = chosenPeriod ?? currentPeriod ?? periods[0] ?? null

  const career = careers.find((c) => c.id_carrera === requestedCareer) ?? null

  const periodParamIsInvalid =
    searchParams.has(PERIOD_PARAM) && (requestedPeriod === null || (periodsReady && chosenPeriod === null))
  const careerParamIsInvalid =
    searchParams.has(CAREER_PARAM) && (requestedCareer === null || (careersReady && career === null))

  const [searchInput, setSearchInput] = useState('')
  const [searchTerm, setSearchTerm] = useState('')
  const [page, setPage] = useState(1)
  const [target, setTarget] = useState<UnassignableSubject | null>(null)
  const [reactivateTarget, setReactivateTarget] = useState<UnassignableSubject | null>(null)
  const [reactivating, setReactivating] = useState(false)
  const [reactivateError, setReactivateError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [dialogError, setDialogError] = useState<string | null>(null)
  const [blocked, setBlocked] = useState<Notice | null>(null)
  const [toast, setToast] = useState<DoneToast | null>(null)

  const careerId = career?.id_carrera ?? null
  const periodId = period?.id_periodo ?? null

  const { subjects, total, perPage, status, error, reload } = useUnassignableSubjects(
    careerId,
    periodId,
    searchTerm,
    page
  )

  const writeParam = (name: string, value: number | null) => {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)

        if (value === null) next.delete(name)
        else next.set(name, String(value))

        return next
      },
      { replace: true }
    )
  }

  const changeCareer = (value: string) => {
    setPage(1)
    writeParam(CAREER_PARAM, Number(value))
  }

  const changePeriod = (value: string) => {
    setPage(1)
    writeParam(PERIOD_PARAM, Number(value))
  }

  useEffect(() => {
    if (periodParamIsInvalid) writeParam(PERIOD_PARAM, null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [periodParamIsInvalid])

  useEffect(() => {
    if (careerParamIsInvalid) writeParam(CAREER_PARAM, null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [careerParamIsInvalid])

  // La búsqueda se manda al servidor tras una pausa y siempre vuelve a la primera página.
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearchTerm(searchInput.trim())
      setPage(1)
    }, SEARCH_DEBOUNCE_MS)

    return () => clearTimeout(timer)
  }, [searchInput])

  // El aviso de bloqueo pertenece a la lista que se estaba viendo.
  useEffect(() => {
    setBlocked(null)
  }, [careerId, periodId, searchTerm, page])

  const closeToast = useCallback(() => setToast(null), [])

  const canUnassign = period?.vigente === true

  const handleAction = (subject: UnassignableSubject) => {
    if (career === null || period === null) return

    if (subject.grupos_count > 0) {
      setBlocked({
        title: `No se puede desvincular: la materia tiene ${subject.grupos_count} grupos registrados en este período`,
        description: `${subject.nombre} tiene grupos en ${career.nombre} en ${period.etiqueta}. Primero deben darse de baja esos grupos.`,
      })

      return
    }

    setBlocked(null)
    setDialogError(null)
    setTarget(subject)
  }

  const cancelDialog = () => {
    setTarget(null)
    setDialogError(null)
  }

  const confirm = async () => {
    if (target === null || career === null || period === null) return

    setSubmitting(true)
    setDialogError(null)

    try {
      await unassignSubject({
        id_carrera: career.id_carrera,
        id_periodo: period.id_periodo,
        id_materia: target.id_materia,
      })

      setTarget(null)
      setToast({
        title: 'Materia desvinculada',
        description: `${target.nombre} ya no se oferta en ${career.nombre} en ${period.etiqueta}.`,
      })
      reload()
    } catch (cause) {
      if (cause instanceof ApiError && cause.isValidation) {
        // Regla de negocio (grupos nuevos, período ya no vigente, ya desvinculada): se muestra tal cual.
        const message = cause.errors.id_materia?.[0] ?? cause.errors.id_periodo?.[0] ?? cause.message

        setTarget(null)
        setBlocked({ title: message, description: `${target.nombre} en ${career.nombre} (${period.etiqueta}).` })
        reload()
      } else {
        setDialogError(cause instanceof ApiError ? cause.message : 'No se pudo desvincular. Intente de nuevo.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleReactivate = (subject: UnassignableSubject) => {
    setBlocked(null)
    setReactivateError(null)
    setReactivateTarget(subject)
  }

  const cancelReactivate = () => {
    setReactivateTarget(null)
    setReactivateError(null)
  }

  const confirmReactivate = async () => {
    if (reactivateTarget === null || career === null || period === null) return

    setReactivating(true)
    setReactivateError(null)

    try {
      await reactivateSubject({
        id_carrera: career.id_carrera,
        id_periodo: period.id_periodo,
        id_materia: reactivateTarget.id_materia,
      })

      setReactivateTarget(null)
      setToast({
        title: 'Materia reactivada',
        description: `${reactivateTarget.nombre} vuelve a ofertarse en ${career.nombre} en ${period.etiqueta}.`,
      })
      reload()
    } catch (cause) {
      if (cause instanceof ApiError && cause.isValidation) {
        const message = cause.errors.id_materia?.[0] ?? cause.errors.id_periodo?.[0] ?? cause.message

        setReactivateTarget(null)
        setBlocked({
          title: message,
          description: `${reactivateTarget.nombre} en ${career.nombre} (${period.etiqueta}).`,
        })
        reload()
      } else {
        setReactivateError(cause instanceof ApiError ? cause.message : 'No se pudo reactivar. Intente de nuevo.')
      }
    } finally {
      setReactivating(false)
    }
  }

  const header = (
    <PageHeader
      title="Desvincular/Reactivar materia de carrera"
      subtitle="Quite una materia de la oferta de una carrera en el período vigente, o vuelva a ofertarla. Nada se borra."    />
  )

  let body: JSX.Element

  if (periodsStatus === 'loading' || careersStatus === 'loading') {
    body = <LoadingState rows={4} label="Cargando carreras y períodos" />
  } else if (periodsStatus === 'error' && periodsError) {
    body = (
      <Card className="overflow-hidden p-0">
        <ErrorState error={periodsError} onRetry={reloadPeriods} />
      </Card>
    )
  } else if (careersStatus === 'error' && careersError) {
    body = (
      <Card className="overflow-hidden p-0">
        <ErrorState error={careersError} onRetry={reloadCareers} />
      </Card>
    )
  } else if (period === null) {
    body = (
      <Card className="overflow-hidden p-0">
        <EmptyState
          icon={CalendarX}
          title="No hay períodos académicos"
          description="Sin un período registrado no hay materias que desvincular."
        />
      </Card>
    )
  } else {
    const totalPages = Math.max(1, Math.ceil(total / perPage))

    body = (
      <div className="space-y-4">
        <Card className="p-4">
          <div className="grid gap-4 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.2fr)]">
            <div className="space-y-1.5">
              <Label htmlFor="career-select">Carrera</Label>
              <FormSelect
                id="career-select"
                ariaLabel="Carrera"
                value={career === null ? undefined : String(career.id_carrera)}
                placeholder="Elija una carrera"
                options={careers.map((c) => ({ value: String(c.id_carrera), label: `${c.codigo} · ${c.nombre}` }))}
                onValueChange={changeCareer}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="period-select">Período</Label>
              <FormSelect
                id="period-select"
                ariaLabel="Período"
                value={String(period.id_periodo)}
                placeholder="Elija un período"
                options={periods.map((p) => ({
                  value: String(p.id_periodo),
                  label: `${p.etiqueta}${p.vigente ? ' · vigente' : ''}`,
                }))}
                onValueChange={changePeriod}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="subject-search">Buscar</Label>
              <div className="relative">
                <Search
                  className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                  aria-hidden="true"
                />
                <Input
                  id="subject-search"
                  type="search"
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                  placeholder="Nombre o código"
                  className="h-11 pl-9"
                />
              </div>
            </div>
          </div>
        </Card>

        {!canUnassign && (
          <Alert variant="info">
            <Lock aria-hidden="true" />
            <AlertTitle>Período anterior: solo consulta</AlertTitle>
            <AlertDescription>
              {currentPeriod === null
                ? 'No hay un período vigente, así que no es posible desvincular ni reactivar materias.'
                : `Solo se puede desvincular o reactivar en el período vigente (${currentPeriod.etiqueta}).`}
            </AlertDescription>
          </Alert>
        )}

        {blocked && (
          <Alert variant="destructive">
            <XCircle aria-hidden="true" />
            <AlertTitle>{blocked.title}</AlertTitle>
            <AlertDescription>{blocked.description}</AlertDescription>
          </Alert>
        )}

        <Card className="overflow-hidden p-0">
          {career === null && (
            <EmptyState
              icon={GraduationCap}
              title="Elija una carrera"
              description={`Elija una carrera para ver sus materias en ${period.etiqueta}.`}
            />
          )}

          {career !== null && status === 'loading' && <LoadingState rows={5} label="Cargando materias" />}

          {career !== null && status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {career !== null && status === 'success' && total === 0 && (
            <EmptyState
              icon={searchTerm === '' ? Unlink : Search}
              title={
                searchTerm === ''
                  ? `Esta carrera no tiene materias asignadas en ${period.etiqueta}`
                  : `Sin resultados para «${searchTerm}»`
              }
              description={
                searchTerm === ''
                  ? 'Cuando se le asignen materias en este período las verá aquí.'
                  : 'Pruebe con otro nombre o código.'
              }
            />
          )}

          {career !== null && status === 'success' && subjects.length > 0 && (
            <>
              <UnassignSubjectsTable
                subjects={subjects}
                periodLabel={period.etiqueta}
                canChange={canUnassign}
                onUnassign={handleAction}
                onReactivate={handleReactivate}
              />

              <DataPagination
                page={page}
                perPage={perPage}
                total={total}
                totalPages={totalPages}
                onPageChange={setPage}
                itemLabel="materias"
                summaryPrefix=""
              />

              {canUnassign && (
                <p className="border-t border-border-soft px-4 py-2 text-xs text-muted-foreground">
                  Las materias que tienen grupos no se pueden desvincular.
                </p>
              )}
            </>
          )}
        </Card>

        <UnassignSubjectDialog
          subject={target}
          careerName={career?.nombre ?? ''}
          periodLabel={period.etiqueta}
          submitting={submitting}
          error={dialogError}
          onCancel={cancelDialog}
          onConfirm={confirm}
        />

        <ReactivateSubjectDialog
          subject={reactivateTarget}
          careerName={career?.nombre ?? ''}
          periodLabel={period.etiqueta}
          submitting={reactivating}
          error={reactivateError}
          onCancel={cancelReactivate}
          onConfirm={confirmReactivate}
        />
      </div>
    )
  }

  return (
    <AppShell
      mobileTitle="Desvincular/Reactivar materia"
      breadcrumbs={[
        { label: 'Catálogo académico' },
        { label: 'Desvincular materia de carrera' },
      ]}
    >
      {header}
      {body}
      {toast && <Toast title={toast.title} description={toast.description} onClose={closeToast} />}
    </AppShell>
  )
}
