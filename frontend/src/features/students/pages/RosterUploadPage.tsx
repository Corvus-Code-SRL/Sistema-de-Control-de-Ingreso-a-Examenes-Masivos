import { useEffect, useId } from 'react'
import { CalendarX, Users } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import {
  CourseAccessDenied,
  groupIdFrom,
  GroupNotFound,
  useGroupDetail,
  useMyCourses,
} from '@/features/groups'
import type { Course } from '@/features/groups'
import type { ApiError } from '@/lib/api-client'

import { RosterUploadPanel } from '../components/RosterUploadPanel'
import { courseOptionLabel, uploadableCourses } from '../utils/rosterGroups'

const TITLE = 'Cargar nómina de estudiantes'

/**
 * Carga de nómina (HU-021), independiente de las demás vistas.
 *
 * Se entra desde el menú y el grupo se elige aquí mismo: no hace falta pasar por Mis grupos ni por
 * Materias. `?grupo=` deja un grupo preseleccionado (enlaces viejos o tras registrar un grupo); si
 * el docente dicta un solo grupo, se elige solo. Las reglas de siempre las comprueba el detalle del
 * grupo elegido: grupo propio, activo y del período vigente.
 */
export function RosterUploadPage() {
  const [params, setParams] = useSearchParams()
  const rawGroup = params.get('grupo')
  const groupId = rawGroup === null ? null : groupIdFrom(rawGroup)
  const { courses, isLoading, error, reload } = useMyCourses()
  const options = uploadableCourses(courses)

  // Un solo grupo para elegir: se elige solo, sin dejar una entrada extra en el historial.
  const onlyGroupId = !isLoading && options.length === 1 ? options[0].group.id_grupo : null

  useEffect(() => {
    if (rawGroup === null && onlyGroupId !== null) {
      setParams({ grupo: String(onlyGroupId) }, { replace: true })
    }
  }, [rawGroup, onlyGroupId, setParams])

  const selectGroup = (id: number | null): void => {
    setParams(id === null ? {} : { grupo: String(id) })
  }

  return (
    <AppShell mobileTitle={TITLE} breadcrumbs={[{ label: TITLE }]}>
      <PageHeader title={TITLE} subtitle="Elija el grupo y suba su lista de estudiantes en CSV o XLSX." />

      <GroupPicker
        options={options}
        selectedId={groupId}
        isLoading={isLoading}
        error={error}
        onRetry={reload}
        onSelect={selectGroup}
      />

      {rawGroup !== null && groupId === null && (
        <Card className="p-0">
          <GroupNotFound />
        </Card>
      )}

      {/* La clave reinicia la carga al cambiar de grupo: nunca se confirma en el grupo equivocado. */}
      {groupId !== null && <RosterUploadView key={groupId} groupId={groupId} />}
    </AppShell>
  )
}

interface GroupPickerProps {
  options: Course[]
  selectedId: number | null
  isLoading: boolean
  error: ApiError | null
  onRetry: () => void
  onSelect: (groupId: number | null) => void
}

/** Combo con los grupos activos del docente. */
function GroupPicker({ options, selectedId, isLoading, error, onRetry, onSelect }: GroupPickerProps) {
  const selectId = useId()

  if (isLoading) {
    return (
      <Card className="p-0">
        <LoadingState rows={2} label="Cargando sus grupos" />
      </Card>
    )
  }

  // Sin la lista igual se puede cargar el grupo de la URL: el error solo se muestra si no hay uno.
  if (error && selectedId === null) {
    return (
      <Card className="p-0">
        <ErrorState error={error} onRetry={onRetry} />
      </Card>
    )
  }

  if (options.length === 0 && selectedId === null) {
    return (
      <Card className="p-0">
        <EmptyState
          icon={Users}
          title="No tiene grupos para cargar"
          description="La nómina se carga en un grupo activo que usted dicta en el período vigente."
        />
      </Card>
    )
  }

  // Un grupo de la URL que no está en la lista (de otro docente, inactivo…) se deja elegido: su
  // detalle explica por qué no se puede cargar.
  const listed = selectedId === null || options.some((course) => course.group.id_grupo === selectedId)

  return (
    <Card className="space-y-2 p-4">
      <label htmlFor={selectId} className="sciem-label block text-foreground">
        Grupo
      </label>

      <select
        id={selectId}
        value={selectedId === null ? '' : String(selectedId)}
        onChange={(event) => onSelect(groupIdFrom(event.currentTarget.value))}
        className="h-10 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
      >
        <option value="">Seleccione un grupo</option>
        {!listed && <option value={String(selectedId)}>Grupo seleccionado</option>}
        {options.map((course) => (
          <option key={course.group.id_grupo} value={String(course.group.id_grupo)}>
            {courseOptionLabel(course)}
          </option>
        ))}
      </select>

      <p className="sciem-supporting text-muted-foreground">
        Aparecen sus grupos activos. Al cambiar de grupo, la carga empieza de nuevo.
      </p>
    </Card>
  )
}

function RosterUploadView({ groupId }: { groupId: number }) {
  const { detail, isLoading, isForbidden, isNotFound, error, reload } = useGroupDetail(groupId)

  if (isLoading) {
    return (
      <Card className="p-0">
        <LoadingState rows={3} label="Cargando el grupo" />
      </Card>
    )
  }

  if (isNotFound) {
    return (
      <Card className="p-0">
        <GroupNotFound />
      </Card>
    )
  }

  if (error) {
    return (
      <Card className="p-0">
        <ErrorState error={error} onRetry={reload} />
      </Card>
    )
  }

  if (isForbidden) {
    return (
      <Card className="p-0">
        <CourseAccessDenied />
      </Card>
    )
  }

  if (detail === null) return null

  if (!detail.group.es_mio || !detail.group.activo || !detail.meta.es_periodo_activo) {
    return (
      <Card className="p-0">
        <EmptyState
          icon={CalendarX}
          title="La nómina de este grupo ya no se puede cargar"
          description="Solo se carga la nómina de un grupo activo del período vigente. Elija otro grupo."
        />
      </Card>
    )
  }

  return (
    <RosterUploadPanel
      group={detail.group}
      subjectName={detail.subject.nombre}
      onReload={reload}
      careers={detail.meta.carreras ?? []}
    />
  )
}
