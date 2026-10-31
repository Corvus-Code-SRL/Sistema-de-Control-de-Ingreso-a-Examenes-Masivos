import { useEffect, useState } from 'react'
import { CalendarClock, History, Pencil, Upload, UsersRound } from 'lucide-react'
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Toast } from '@/components/ui/toast'
import { CourseAccessDenied } from '../components/CourseAccessDenied'
import { CourseHeader } from '../components/CourseHeader'
import { GroupNotFound } from '../components/GroupNotFound'
import { GroupStudentsTable } from '../components/GroupStudentsTable'
import { useGroupDetail } from '../hooks/useGroupDetail'
import { useGroupStudents } from '../hooks/useGroupStudents'
import { useGroupStudentsFilters } from '../hooks/useGroupStudentsFilters'
import { courseTitle } from '../types/group.types'
import { flashMessageFrom } from '../utils/flash'
import { PAST_PERIOD_MESSAGE } from '../utils/messages'
import {
  groupAssistantsPath,
  groupEditPath,
  groupExamsPath,
  groupIdFrom,
  groupRosterPath,
  MY_COURSES_PATH,
} from '../utils/paths'

/**
 * Detalle del grupo (HU-020): datos del grupo, total de inscritos, estado de la nómina y la lista
 * de estudiantes.
 *
 * Es una sola vista: editar el grupo (HU-019), cargar la nómina (HU-021), sus exámenes y sus
 * auxiliares son botones que llevan a la vista de cada acción. Un grupo de un período anterior o
 * inactivo es de solo lectura.
 */
export function GroupDetailPage() {
  const groupId = groupIdFrom(useParams().idGrupo)

  if (groupId === null) {
    return (
      <AppShell
        mobileTitle="Grupo"
        breadcrumbs={[{ label: 'Mis grupos', to: MY_COURSES_PATH }, { label: 'Detalle del grupo' }]}
      >
        <PageHeader title="Detalle del grupo" backTo={MY_COURSES_PATH} backLabel="Volver a Mis grupos" />
        <Card className="p-0">
          <GroupNotFound />
        </Card>
      </AppShell>
    )
  }

  return <GroupDetailView key={groupId} groupId={groupId} />
}

function GroupDetailView({ groupId }: { groupId: number }) {
  const { detail, isLoading, isForbidden, isNotFound, error, reload } = useGroupDetail(groupId)

  const { filters, searchInput, setSearchInput, setPage, clear } = useGroupStudentsFilters()
  // Se pide en paralelo al detalle: si el grupo no es suyo, el servidor responde 403 y no se muestra.
  const students = useGroupStudents(groupId, filters)

  const location = useLocation()
  const navigate = useNavigate()
  // Aviso que deja otra vista al volver aquí (por ejemplo, «Grupo actualizado» tras editar).
  const [flash, setFlash] = useState(() => flashMessageFrom(location.state))

  // Se muestra una sola vez: se borra del historial para que recargar la página no lo repita.
  useEffect(() => {
    if (flashMessageFrom(location.state) !== null) {
      navigate({ pathname: location.pathname, search: location.search }, { replace: true, state: null })
    }
  }, [location.state, location.pathname, location.search, navigate])

  const correctingPage =
    students.page !== null &&
    !students.isStale &&
    students.status !== 'error' &&
    students.page.items.length === 0 &&
    filters.page > 1

  // Una página fuera de rango (por ejemplo, tras una búsqueda) vuelve a la última que existe.
  useEffect(() => {
    if (correctingPage && students.page) {
      setPage(Math.max(1, students.page.totalPages), true)
    }
  }, [correctingPage, students.page, setPage])

  const isVisible = detail !== null && !isForbidden
  const title = isVisible ? courseTitle(detail.subject, detail.group) : 'Detalle del grupo'
  const canManage =
    isVisible && detail.group.es_mio && detail.group.activo && detail.meta.es_periodo_activo
  const enrolledCount =
    students.status === 'error' || students.page === null ? null : students.page.enrolledTotal

  const reloadAll = () => {
    reload()
    students.reload()
  }

  return (
    <AppShell
      mobileTitle={isVisible ? `Grupo ${detail.group.num_grupo}` : 'Grupo'}
      mobileSubtitle={isVisible ? detail.subject.nombre : undefined}
      breadcrumbs={[{ label: 'Mis grupos', to: MY_COURSES_PATH }, { label: title }]}
    >
      <PageHeader
        title={title}
        backTo={MY_COURSES_PATH}
        backLabel="Volver a Mis grupos"
        subtitle={
          isVisible && (
            <>
              {detail.subject.codigo}
              {' · '}
              {detail.subject.carrera.nombre}
              {' · '}
              {detail.group.periodo.etiqueta}
            </>
          )
        }
        actions={
          isVisible && (
            <>
              {canManage && (
                <>
                  <Button variant="secondary" size="sm" className="gap-2" asChild>
                    <Link to={groupEditPath(groupId)}>
                      <Pencil className="size-4" aria-hidden="true" />
                      Editar grupo
                    </Link>
                  </Button>

                  <Button variant="secondary" size="sm" className="gap-2" asChild>
                    <Link to={groupRosterPath(groupId)}>
                      <Upload className="size-4" aria-hidden="true" />
                      Cargar nómina
                    </Link>
                  </Button>
                </>
              )}

              <Button variant="outline" size="sm" className="gap-2" asChild>
                <Link to={groupExamsPath(groupId)}>
                  <CalendarClock className="size-4" aria-hidden="true" />
                  Ver exámenes
                </Link>
              </Button>

              <Button variant="outline" size="sm" className="gap-2" asChild>
                <Link to={groupAssistantsPath(groupId)}>
                  <UsersRound className="size-4" aria-hidden="true" />
                  Auxiliares
                </Link>
              </Button>
            </>
          )
        }
      />

      {flash && (
        <Toast title={flash.title} description={flash.description} onClose={() => setFlash(null)} />
      )}

      {isLoading && (
        <Card className="p-0">
          <LoadingState rows={3} label="Cargando el grupo" />
        </Card>
      )}

      {!isLoading && isNotFound && (
        <Card className="p-0">
          <GroupNotFound />
        </Card>
      )}

      {!isLoading && !isNotFound && error && (
        <Card className="p-0">
          <ErrorState error={error} onRetry={reloadAll} />
        </Card>
      )}

      {!isLoading && isForbidden && (
        <Card className="p-0">
          <CourseAccessDenied />
        </Card>
      )}

      {!isLoading && !error && isVisible && (
        <>
          {/* CA-6 (HU-019): solo el período vigente admite escritura (HU-006). */}
          {!detail.meta.es_periodo_activo && (
            <Alert variant="info">
              <History aria-hidden="true" />
              <AlertDescription>{PAST_PERIOD_MESSAGE}</AlertDescription>
            </Alert>
          )}

          <CourseHeader
            detail={detail}
            enrolledCount={enrolledCount}
            enrolledCountFailed={students.status === 'error'}
          />

          <GroupStudentsTable
            searchInput={searchInput}
            onSearchChange={setSearchInput}
            query={filters.q}
            onClearSearch={clear}
            page={students.page}
            isStale={students.isStale}
            status={students.status}
            error={students.error}
            onRetry={students.reload}
            onPageChange={(next) => setPage(next)}
            correctingPage={correctingPage}
            rosterTo={canManage ? groupRosterPath(groupId) : undefined}
          />
        </>
      )}
    </AppShell>
  )
}
