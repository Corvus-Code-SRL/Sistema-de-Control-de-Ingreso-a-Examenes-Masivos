import { SearchX } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { CourseAccessDenied } from '../components/CourseAccessDenied'
import { CourseHeader } from '../components/CourseHeader'
import { CourseTabsShell } from '../components/CourseTabsShell'
import { useGroupDetail } from '../hooks/useGroupDetail'
import { courseTitle } from '../types/group.types'

/**
 * Detalle de un curso (artboards 2.3, 2.4 y 2.11).
 *
 * De esta historia son la cabecera, el armazón de pestañas y el control de
 * acceso. El contenido de cada pestaña llega con sus propias historias.
 */
export function CourseDetailPage() {
  const params = useParams()
  const groupId = Number(params.idGrupo)

  const { detail, isLoading, isForbidden, isNotFound, error, reload } = useGroupDetail(groupId)

  const title =
    detail && !isForbidden ? courseTitle(detail.subject, detail.group) : 'Detalle del curso'

  return (
    <AppShell
      mobileTitle={detail && !isForbidden ? `Grupo ${detail.group.num_grupo}` : 'Curso'}
      mobileSubtitle={detail && !isForbidden ? detail.subject.nombre : undefined}
      breadcrumbs={[{ label: 'Mis cursos', to: '/mis-cursos' }, { label: title }]}
    >
      <PageHeader
        title={title}
        backTo="/mis-cursos"
        backLabel="Volver a Mis cursos"
        subtitle={
          detail &&
          !isForbidden && (
            <span className="flex flex-wrap items-center gap-1.5">
              <span className="font-mono">{detail.subject.codigo}</span>
              <span aria-hidden="true">·</span>
              <span>{detail.subject.carrera.nombre}</span>
              <span aria-hidden="true">·</span>
              <span>{detail.group.periodo.nombre_periodo}</span>
            </span>
          )
        }
      />

      {isLoading && (
        <Card className="p-0">
          <LoadingState rows={3} label="Cargando el curso" />
        </Card>
      )}

      {!isLoading && isNotFound && (
        <Card className="p-0">
          <EmptyState
            icon={SearchX}
            title="El curso no existe"
            description="El grupo que intenta abrir no está registrado o fue dado de baja."
            action={
              <Button variant="outline" size="sm" asChild>
                <Link to="/mis-cursos">Ir a mis cursos</Link>
              </Button>
            }
          />
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

      {!isLoading && !error && !isForbidden && detail && (
        <>
          <CourseHeader detail={detail} />
          <CourseTabsShell group={detail.group} />
        </>
      )}
    </AppShell>
  )
}
