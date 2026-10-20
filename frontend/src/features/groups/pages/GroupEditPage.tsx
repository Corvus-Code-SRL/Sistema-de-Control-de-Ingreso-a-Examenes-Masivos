import type { ReactNode } from 'react'
import { CalendarX, Lock } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { CourseAccessDenied } from '../components/CourseAccessDenied'
import { GroupNotFound } from '../components/GroupNotFound'
import { ActualizarGrupoForm } from '../components/GroupUpdateForm'
import { useGroupDetail } from '../hooks/useGroupDetail'
import { useGroupStudents } from '../hooks/useGroupStudents'
import { courseTitle } from '../types/group.types'
import type { FlashState } from '../utils/flash'
import { PAST_PERIOD_MESSAGE } from '../utils/messages'
import { groupDetailPath, groupIdFrom, MY_COURSES_PATH } from '../utils/paths'

const TITLE = 'Editar grupo'

/** El mensaje de CA-5 es el mismo que devuelve el servidor si se intenta guardar igual. */
const EDIT_LOCKED_MESSAGE = 'No se puede editar mientras un examen del grupo está en curso'

/** Sin búsqueda y en la primera página: solo interesa el total de inscritos activos. */
const ENROLLED_TOTAL_QUERY = { q: '', page: 1 }

/**
 * Edición de un grupo propio (HU-019): número y período; materia, carrera y docente son de solo
 * lectura.
 *
 * Solo se edita un grupo activo del período vigente, y nunca mientras un examen suyo está en ingreso
 * o en curso. Guardar vuelve al detalle con el aviso «Grupo actualizado»; Cancelar vuelve sin cambios.
 */
export function GroupEditPage() {
  const groupId = groupIdFrom(useParams().idGrupo)

  if (groupId === null) {
    return (
      <GroupEditShell>
        <Card className="p-0">
          <GroupNotFound />
        </Card>
      </GroupEditShell>
    )
  }

  return <GroupEditView key={groupId} groupId={groupId} />
}

function GroupEditView({ groupId }: { groupId: number }) {
  const navigate = useNavigate()
  const { detail, isLoading, isForbidden, isNotFound, error, reload } = useGroupDetail(groupId)
  // El mismo total que muestra el detalle (solo inscripciones activas), para que ambas vistas coincidan.
  const students = useGroupStudents(groupId, ENROLLED_TOTAL_QUERY)

  const isVisible = detail !== null && !isForbidden
  const isEditable = isVisible && detail.group.activo && detail.meta.es_periodo_activo
  const backTo = groupDetailPath(groupId)
  const enrolledCount =
    students.status === 'error' || students.page === null ? null : students.page.enrolledTotal

  const goBack = () => navigate(backTo)

  const handleUpdated = () => {
    const state: FlashState = {
      flash: { title: 'Grupo actualizado', description: 'Los cambios se guardaron correctamente.' },
    }

    navigate(backTo, { state })
  }

  return (
    <GroupEditShell
      courseLabel={isVisible ? courseTitle(detail.subject, detail.group) : undefined}
      backTo={isVisible ? backTo : undefined}
    >
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
          <ErrorState error={error} onRetry={reload} />
        </Card>
      )}

      {!isLoading && isForbidden && (
        <Card className="p-0">
          <CourseAccessDenied />
        </Card>
      )}

      {!isLoading && !error && isVisible && !isEditable && (
        <Card className="p-0">
          <EmptyState
            icon={CalendarX}
            title={
              detail.meta.es_periodo_activo
                ? 'Este grupo ya no se puede editar'
                : PAST_PERIOD_MESSAGE
            }
            description="Solo se edita un grupo activo del período vigente."
            action={
              <Button variant="outline" size="sm" asChild>
                <Link to={backTo}>Volver al grupo</Link>
              </Button>
            }
          />
        </Card>
      )}

      {!isLoading && !error && isVisible && isEditable && (
        <>
          {detail.meta.edicion_bloqueada && (
            <Alert className="border-warn-fg/30 bg-warn-soft text-warn-fg">
              <Lock aria-hidden="true" />
              <AlertTitle>{EDIT_LOCKED_MESSAGE}</AlertTitle>
              <AlertDescription className="text-warn-fg">
                Podrá editarlo cuando el examen termine.
              </AlertDescription>
            </Alert>
          )}

          <Card className="p-0">
            <ActualizarGrupoForm
              group={detail.group}
              subjectCareerLabel={`${detail.subject.nombre} · ${detail.subject.carrera.nombre}`}
              teacherName={detail.group.docente.nombre_completo}
              studentCount={enrolledCount}
              onCancel={goBack}
              onUpdated={handleUpdated}
              hideTitle
              locked={detail.meta.edicion_bloqueada}
            />
          </Card>
        </>
      )}
    </GroupEditShell>
  )
}

interface GroupEditShellProps {
  courseLabel?: string
  backTo?: string
  children: ReactNode
}

function GroupEditShell({ courseLabel, backTo, children }: GroupEditShellProps) {
  return (
    <AppShell
      mobileTitle={TITLE}
      mobileSubtitle={courseLabel}
      breadcrumbs={[
        { label: 'Mis cursos', to: MY_COURSES_PATH },
        ...(courseLabel && backTo ? [{ label: courseLabel, to: backTo }] : []),
        { label: TITLE },
      ]}
    >
      <PageHeader
        title={TITLE}
        subtitle={courseLabel}
        backTo={backTo ?? MY_COURSES_PATH}
        backLabel={backTo ? 'Volver al grupo' : 'Volver a Mis cursos'}
      />

      {children}
    </AppShell>
  )
}
