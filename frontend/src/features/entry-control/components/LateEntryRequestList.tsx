import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Card } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { ApiError } from '@/lib/api-client'
import { LATE_ENTRY_REQUEST_STATUS, type LateEntryRequest } from '../types/late-entry.types'
import { LateEntryOtherExamNotice } from './LateEntryOtherExamNotice'
import { LateEntryRequestActions } from './LateEntryRequestActions'
import { LateEntryRequestStatusBadge } from './LateEntryRequestStatusBadge'
import { LATE_ENTRY_MESSAGES } from './lateEntryMessages'

export interface LateEntryResolveActions {
  busyId: number | null
  /** Recibe la solicitud para que se autorice confirmando la advertencia que el docente ve. */
  onApprove: (request: LateEntryRequest) => void
  onReject: (requestId: number) => void
}

interface LateEntryRequestListProps {
  title: string
  requests: LateEntryRequest[] | null
  error: ApiError | null
  /** El docente ve quién pidió cada solicitud; el auxiliar solo ve las suyas. */
  showRequester: boolean
  /** Solo el docente con el examen en curso puede resolver las pendientes. */
  actions?: LateEntryResolveActions
}

/**
 * Solicitudes del examen. Tabla o tarjetas según el ancho de la tarjeta (container query), no
 * de la pantalla: en la columna derecha de un escritorio con menú la tabla no entra.
 */
export function LateEntryRequestList({ title, requests, error, showRequester, actions }: LateEntryRequestListProps) {
  const columns = LATE_ENTRY_MESSAGES.columns
  const canResolve = (request: LateEntryRequest) =>
    actions !== undefined && request.estado === LATE_ENTRY_REQUEST_STATUS.PENDIENTE
  const warning = (request: LateEntryRequest) =>
    request.estado === LATE_ENTRY_REQUEST_STATUS.PENDIENTE && request.otro_examen
      ? <LateEntryOtherExamNotice warning={request.otro_examen} />
      : null

  return (
    <Card className="@container overflow-hidden p-0" aria-busy={requests === null && error === null}>
      <h2 className="sciem-h3 border-b border-border px-4 py-3">{title}</h2>

      {requests === null && error === null && <LoadingState rows={2} label={LATE_ENTRY_MESSAGES.listLoading} />}
      {requests === null && error !== null && <ErrorState error={error} />}
      {requests !== null && error !== null && (
        <p role="status" className="border-b border-warn-border bg-warn-soft px-4 py-2 text-xs text-warn-fg">
          {LATE_ENTRY_MESSAGES.listStale}
        </p>
      )}
      {requests?.length === 0 && (
        <p className="px-4 py-6 text-center text-sm text-muted-foreground">{LATE_ENTRY_MESSAGES.listEmpty}</p>
      )}

      {requests !== null && requests.length > 0 && (
        <>
          <div className="hidden @4xl:block">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{columns.student}</TableHead>
                  <TableHead>{columns.room}</TableHead>
                  <TableHead>{columns.time}</TableHead>
                  {showRequester && <TableHead>{columns.requester}</TableHead>}
                  <TableHead>{columns.status}</TableHead>
                  {actions && <TableHead><span className="sr-only">{columns.actions}</span></TableHead>}
                </TableRow>
              </TableHeader>
              <TableBody>
                {requests.map((request) => (
                  <TableRow key={request.id_solicitud}>
                    <TableCell className="whitespace-normal">
                      <span className="font-medium">{request.estudiante.nombre_completo}</span>
                      <p className="sciem-tnum text-xs text-muted-foreground">{request.estudiante.cod_sis}</p>
                      {warning(request) && <div className="mt-2">{warning(request)}</div>}
                    </TableCell>
                    <TableCell>{request.ambiente.nro_aula}</TableCell>
                    <TableCell className="sciem-tnum">{request.hora_solicitud}</TableCell>
                    {showRequester && (
                      <TableCell className="whitespace-normal">{request.solicitante.nombre_completo}</TableCell>
                    )}
                    <TableCell><LateEntryRequestStatusBadge status={request.estado} /></TableCell>
                    {actions && (
                      <TableCell className="text-right">
                        {canResolve(request) && (
                          <LateEntryRequestActions
                            request={request}
                            busy={actions.busyId !== null}
                            onApprove={actions.onApprove}
                            onReject={actions.onReject}
                          />
                        )}
                      </TableCell>
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <ul aria-label={title} className="@4xl:hidden">
            {requests.map((request) => (
              <li key={request.id_solicitud} className="space-y-3 border-b border-border px-4 py-3 last:border-b-0">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="break-words text-sm font-medium">{request.estudiante.nombre_completo}</p>
                    <p className="sciem-tnum text-xs text-muted-foreground">{request.estudiante.cod_sis}</p>
                  </div>
                  <LateEntryRequestStatusBadge status={request.estado} />
                </div>
                <dl className="grid grid-cols-2 gap-3 text-xs">
                  <div>
                    <dt className="text-muted-foreground">{columns.room}</dt>
                    <dd>{request.ambiente.nro_aula}</dd>
                  </div>
                  <div>
                    <dt className="text-muted-foreground">{columns.time}</dt>
                    <dd className="sciem-tnum">{request.hora_solicitud}</dd>
                  </div>
                  {showRequester && (
                    <div className="col-span-2 min-w-0">
                      <dt className="text-muted-foreground">{columns.requester}</dt>
                      <dd className="break-words">{request.solicitante.nombre_completo}</dd>
                    </div>
                  )}
                </dl>
                {warning(request)}
                {actions && canResolve(request) && (
                  <LateEntryRequestActions
                    request={request}
                    busy={actions.busyId !== null}
                    onApprove={actions.onApprove}
                    onReject={actions.onReject}
                  />
                )}
              </li>
            ))}
          </ul>
        </>
      )}
    </Card>
  )
}
