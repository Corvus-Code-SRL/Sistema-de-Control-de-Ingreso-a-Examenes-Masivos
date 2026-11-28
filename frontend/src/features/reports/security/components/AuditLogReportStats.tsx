import { Activity, Users } from 'lucide-react'
import { Card } from '@/components/ui/card'

import type { AuditLogReportTotals } from '../types/auditLogReport.types'

interface AuditLogReportStatsProps {
  totals: AuditLogReportTotals | null
}

/** HU-068: global totals for all filtered operations. */
export function AuditLogReportStats({
  totals,
}: AuditLogReportStatsProps) {
  const operations = totals?.total_operaciones ?? 0
  const users = totals?.total_usuarios ?? 0
  const actions = totals?.por_accion ?? []

  return (
    <section
      aria-label="Totales del reporte"
      className="space-y-4"
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Card
          className="p-4"
          data-testid="stat-operaciones"
        >
          <div className="flex items-center justify-between gap-2">
            <p className="text-xs font-medium text-muted-foreground">
              Total de operaciones
            </p>

            <Activity
              className="size-5 text-muted-foreground"
              aria-hidden="true"
            />
          </div>

          <p className="sciem-tnum mt-1 text-2xl font-bold">
            {operations}
          </p>
        </Card>

        <Card
          className="p-4"
          data-testid="stat-usuarios"
        >
          <div className="flex items-center justify-between gap-2">
            <p className="text-xs font-medium text-muted-foreground">
              Usuarios distintos
            </p>

            <Users
              className="size-5 text-muted-foreground"
              aria-hidden="true"
            />
          </div>

          <p className="sciem-tnum mt-1 text-2xl font-bold">
            {users}
          </p>
        </Card>
      </div>

      <Card
        className="space-y-3 p-4"
        data-testid="stat-acciones"
      >
        <h2 className="text-sm font-semibold">
          Operaciones por acción
        </h2>

        {actions.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            No hay operaciones para mostrar.
          </p>
        ) : (
          <ul className="space-y-3">
            {actions.map((action) => {
              const percentage =
                operations > 0
                  ? Math.min(
                      100,
                      Math.max(
                        0,
                        (action.total / operations) * 100
                      )
                    )
                  : 0

              return (
                <li
                  key={action.codigo ?? '__sin_accion__'}
                  className="space-y-1.5"
                >
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-sm">
                      {action.etiqueta}
                    </span>

                    <span className="sciem-tnum text-sm font-semibold">
                      {action.total}
                    </span>
                  </div>

                  <div
                    className="h-2 overflow-hidden rounded-full bg-muted"
                    aria-hidden="true"
                  >
                    <div
                      className="h-full rounded-full bg-primary"
                      style={{ width: `${percentage}%` }}
                    />
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </Card>
    </section>
  )
}