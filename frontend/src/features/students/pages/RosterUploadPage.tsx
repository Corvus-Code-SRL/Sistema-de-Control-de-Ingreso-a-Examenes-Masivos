import type { ReactNode } from 'react'
import { CalendarX } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  CourseAccessDenied,
  courseTitle,
  groupDetailPath,
  groupIdFrom,
  GroupNotFound,
  MY_COURSES_PATH,
  useGroupDetail,
} from '@/features/groups'

import { RosterUploadPanel } from '../components/RosterUploadPanel'

/**
 * Carga de nómina de un grupo (HU-021), en su propia URL.
 *
 * Vista interina: monta el mismo asistente que antes vivía en la pestaña Nómina del detalle, sin
 * cambios, para que «Cargar nómina» lleve a una vista propia. HU-021 publicará la suya en esta
 * misma URL. Las reglas son las de siempre: grupo propio, activo y del período vigente.
 */
export function RosterUploadPage() {
  const groupId = groupIdFrom(useParams().idGrupo)

  if (groupId === null) {
    return (
      <RosterShell title="Cargar nómina">
        <Card className="p-0">
          <GroupNotFound />
        </Card>
      </RosterShell>
    )
  }

  return <RosterUploadView key={groupId} groupId={groupId} />
}

function RosterUploadView({ groupId }: { groupId: number }) {
  const { detail, isLoading, isForbidden, isNotFound, error, reload } = useGroupDetail(groupId)

  const isVisible = detail !== null && !isForbidden
  const canUpload =
    isVisible && detail.group.es_mio && detail.group.activo && detail.meta.es_periodo_activo
  const backTo = groupDetailPath(groupId)

  return (
    <RosterShell
      title="Cargar nómina"
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

      {!isLoading && !error && isVisible && !canUpload && (
        <Card className="p-0">
          <EmptyState
            icon={CalendarX}
            title="La nómina de este grupo ya no se puede cargar"
            description="Solo se carga la nómina de un grupo activo del período vigente."
            action={
              <Button variant="outline" size="sm" asChild>
                <Link to={backTo}>Volver al grupo</Link>
              </Button>
            }
          />
        </Card>
      )}

      {!isLoading && !error && isVisible && canUpload && (
        <RosterUploadPanel
          group={detail.group}
          subjectName={detail.subject.nombre}
          onReload={reload}
        />
      )}
    </RosterShell>
  )
}

interface RosterShellProps {
  title: string
  courseLabel?: string
  backTo?: string
  children: ReactNode
}

function RosterShell({ title, courseLabel, backTo, children }: RosterShellProps) {
  return (
    <AppShell
      mobileTitle={title}
      mobileSubtitle={courseLabel}
      breadcrumbs={[
        { label: 'Mis grupos', to: MY_COURSES_PATH },
        ...(courseLabel && backTo ? [{ label: courseLabel, to: backTo }] : []),
        { label: title },
      ]}
    >
      <PageHeader
        title={title}
        subtitle={courseLabel}
        backTo={backTo ?? MY_COURSES_PATH}
        backLabel={backTo ? 'Volver al grupo' : 'Volver a Mis grupos'}
      />

      {children}
    </AppShell>
  )
}
