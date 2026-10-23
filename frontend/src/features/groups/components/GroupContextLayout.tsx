import type { ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { LoadingState } from '@/components/common/LoadingState'
import { ErrorState } from '@/components/common/ErrorState'
import { Card } from '@/components/ui/card'
import { CourseAccessDenied } from './CourseAccessDenied'
import { GroupNotFound } from './GroupNotFound'
import { useGroupDetail } from '../hooks/useGroupDetail'
import { courseTitle, type GroupDetail } from '../types/group.types'
import { groupDetailPath, groupIdFrom, MY_COURSES_PATH } from '../utils/paths'

interface Props {
  title: string
  children: (detail: GroupDetail) => ReactNode
}

/** Resuelve el contexto y el acceso antes de montar las consultas propias de cada página. */
export function GroupContextLayout(props: Props) {
  const groupId = groupIdFrom(useParams().idGrupo)
  if (groupId === null) {
    return (
      <AppShell
        mobileTitle={props.title}
        breadcrumbs={[{ label: 'Mis cursos', to: MY_COURSES_PATH }, { label: props.title }]}
      >
        <PageHeader title={props.title} backTo={MY_COURSES_PATH} backLabel="Volver a Mis cursos" />
        <Card className="p-0"><GroupNotFound /></Card>
      </AppShell>
    )
  }
  return <ResolvedGroupContext key={groupId} groupId={groupId} {...props} />
}

function ResolvedGroupContext({ groupId, title, children }: Props & { groupId: number }) {
  const { detail, isLoading, isForbidden, isNotFound, error, reload } = useGroupDetail(groupId)
  const visible = !isLoading && !error && !isForbidden && !isNotFound && detail !== null
  return (
    <AppShell
      mobileTitle={title}
      breadcrumbs={[
        { label: 'Mis cursos', to: MY_COURSES_PATH },
        { label: visible ? courseTitle(detail.subject, detail.group) : 'Grupo', to: groupDetailPath(groupId) },
        { label: title },
      ]}
    >
      {visible ? children(detail) : (
        <>
          <PageHeader title={title} backTo={groupDetailPath(groupId)} backLabel="Volver al grupo" />
          <Card className="p-0">
            {isLoading && <LoadingState rows={3} label="Cargando el grupo" />}
            {!isLoading && isForbidden && <CourseAccessDenied />}
            {!isLoading && isNotFound && <GroupNotFound />}
            {!isLoading && !isNotFound && error && <ErrorState error={error} onRetry={reload} />}
          </Card>
        </>
      )}
    </AppShell>
  )
}
