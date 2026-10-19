import { useEffect, useState } from 'react'
import { CalendarClock, Pencil, Upload, UsersRound } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { Toast } from '@/components/ui/toast'
import { CourseAccessDenied } from '../components/CourseAccessDenied'
import { CourseHeader } from '../components/CourseHeader'
import { GroupNotFound } from '../components/GroupNotFound'
import { ActualizarGrupoForm } from '../components/GroupUpdateForm'
import { GroupStudentsTable } from '../components/GroupStudentsTable'
import { useGroupDetail } from '../hooks/useGroupDetail'
import { useGroupStudents } from '../hooks/useGroupStudents'
import { useGroupStudentsFilters } from '../hooks/useGroupStudentsFilters'
import { courseTitle } from '../types/group.types'
import {
  groupAssistantsPath,
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
        breadcrumbs={[{ label: 'Mis cursos', to: MY_COURSES_PATH }, { label: 'Detalle del grupo' }]}
      >
        <PageHeader title="Detalle del grupo" backTo={MY_COURSES_PATH} backLabel="Volver a Mis cursos" />
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

  const [toast, setToast] = useState<{ show: boolean; title: string; description: string }>({
    show: false,
    title: '',
    description: '',
  })

  const [isEditOpen, setIsEditOpen] = useState(false)

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
      breadcrumbs={[{ label: 'Mis cursos', to: MY_COURSES_PATH }, { label: title }]}
    >
      <PageHeader
        title={title}
        backTo={MY_COURSES_PATH}
        backLabel="Volver a Mis cursos"
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
                  <Button
                    variant="secondary"
                    size="sm"
                    className="gap-2"
                    onClick={() => setIsEditOpen(true)}
                  >
                    <Pencil className="size-4" aria-hidden="true" />
                    <span>Editar grupo</span>
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

      {toast.show && (
        <Toast
          title={toast.title}
          description={toast.description}
          onClose={() => setToast((prev) => ({ ...prev, show: false }))}
        />
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

      {/* Editar grupo (HU-019) se abre en un modal hasta que su historia publique su propia vista. */}
      <Dialog open={isEditOpen} onOpenChange={setIsEditOpen}>
        <DialogContent className="sm:max-w-[640px] p-0 overflow-hidden">
          {detail && (
            <ActualizarGrupoForm
              group={detail.group}
              subjectCareerLabel={`${detail.subject.nombre} · ${detail.subject.carrera.nombre}`}
              studentCount={enrolledCount ?? detail.group.cantidad_estudiantes}
              teacherName={detail.group.docente.nombre_completo}
              onCancel={() => setIsEditOpen(false)}
              onUpdated={() => {
                setIsEditOpen(false)
                reload()
                setToast({
                  show: true,
                  title: 'Grupo actualizado',
                  description: 'Los cambios se guardaron correctamente.',
                })
              }}
            />
          )}
        </DialogContent>
      </Dialog>
    </AppShell>
  )
}
