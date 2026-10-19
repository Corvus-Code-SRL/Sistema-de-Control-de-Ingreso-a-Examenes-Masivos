import type { ReactNode } from 'react'
import { Card } from '@/components/ui/card'
import { RosterStatusBadge } from './RosterStatusBadge'
import { hasRoster, type GroupDetail } from '../types/group.types'

interface CourseHeaderProps {
  detail: GroupDetail
  /**
   * Inscripciones activas según la lista de estudiantes (`total_inscritos`); null mientras carga o
   * si no se pudo leer. Sale de la misma consulta que la tabla para que ambas siempre coincidan.
   */
  enrolledCount: number | null
  /** La lista no se pudo leer: el total se marca como no disponible en lugar de «cargando». */
  enrolledCountFailed?: boolean
}

/**
 * Resumen del grupo abierto (HU-020): inscritos, nómina, período y docente.
 *
 * Solo datos del grupo: exámenes y auxiliares tienen sus propias vistas, a las que se llega con
 * los botones del encabezado.
 */
export function CourseHeader({ detail, enrolledCount, enrolledCountFailed = false }: CourseHeaderProps) {
  const { group, meta } = detail
  const counted = enrolledCount === null ? null : { ...group, cantidad_estudiantes: enrolledCount }
  const pending = enrolledCountFailed ? 'No disponible' : 'Cargando'
  const teacherName = group.docente.nombre_completo.trim()

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <StatCard label="Inscritos">
        <p className="sciem-tnum text-2xl font-semibold text-brand">
          {counted === null ? (
            <>
              <span aria-hidden="true">…</span>
              <span className="sr-only">{pending}: inscritos</span>
            </>
          ) : hasRoster(counted) ? (
            counted.cantidad_estudiantes
          ) : (
            <>
              <span aria-hidden="true">—</span>
              <span className="sr-only">Sin inscritos</span>
            </>
          )}
        </p>
      </StatCard>

      <StatCard label="Nómina">
        {counted === null ? (
          <span className="text-sm text-muted-foreground">
            <span aria-hidden="true">…</span>
            <span className="sr-only">{pending}: estado de la nómina</span>
          </span>
        ) : (
          <RosterStatusBadge group={counted} />
        )}
      </StatCard>

      <StatCard label="Período">
        <div className="flex flex-wrap items-center gap-1.5">
          <span
            className="text-sm font-medium text-foreground"
            title={group.periodo.nombre_periodo}
          >
            {group.periodo.etiqueta}
          </span>

          {!meta.es_periodo_activo && (
            <span className="inline-flex items-center rounded-full bg-warn-soft px-2 py-0.5 text-xs font-medium text-warn-fg">
              Período anterior
            </span>
          )}
        </div>
      </StatCard>

      <StatCard label="Docente">
        {teacherName === '' ? (
          <p className="text-sm text-muted-foreground">
            <span aria-hidden="true">—</span>
            <span className="sr-only">Sin docente registrado</span>
          </p>
        ) : (
          <p className="truncate text-sm font-medium text-foreground" title={teacherName}>
            {teacherName}
          </p>
        )}
      </StatCard>
    </div>
  )
}

interface StatCardProps {
  label: string
  children: ReactNode
}

/** Un dato del resumen con su rótulo. */
function StatCard({ label, children }: StatCardProps) {
  return (
    <Card className="gap-1 p-4">
      <p className="sciem-overline text-muted-foreground">{label}</p>
      {children}
    </Card>
  )
}
