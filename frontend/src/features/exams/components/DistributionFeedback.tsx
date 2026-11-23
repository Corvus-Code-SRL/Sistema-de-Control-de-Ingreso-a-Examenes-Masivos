import { AlertTriangle, Info, Lock } from 'lucide-react'
import type { ApiError } from '@/lib/api-client'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import type { RoomDistributionView } from '../types/roomDistribution.types'
import { DISTRIBUTION_MESSAGES as messages } from '../utils/roomDistributionMessages'

interface Props {
  data: RoomDistributionView
  missingSeats: number
  unassigned: number
  error: ApiError | null
  mustReload: boolean
  busy: boolean
  onReload: () => void
}

export function DistributionFeedback({ data, missingSeats, unassigned, error, mustReload, busy, onReload }: Props) {
  const fieldMessages = [...new Set(Object.values(error?.errors ?? {}).flat())]
  return (
    <>
      {error && (
        <Alert className="border-danger-border bg-danger-soft text-danger-fg">
          <AlertTriangle aria-hidden="true" />
          <AlertTitle>{mustReload ? messages.reloadTitle : messages.validationTitle}</AlertTitle>
          <AlertDescription className="space-y-2">
            {fieldMessages.length > 0 ? (
              <ul className="list-inside list-disc">{fieldMessages.map((message) => <li key={message}>{message}</li>)}</ul>
            ) : <p>{error.message}</p>}
            {(mustReload || error.isValidation) && <Button variant="outline" className="h-11" disabled={busy} onClick={onReload}>
              Recargar distribución
            </Button>}
          </AlertDescription>
        </Alert>
      )}
      {!data.editable && (
        <Alert className="border-info-border bg-info-soft text-info">
          <Lock aria-hidden="true" /><AlertTitle>Solo lectura</AlertTitle>
          <AlertDescription>{data.motivo_solo_lectura ?? 'Esta distribución solo se puede consultar.'}</AlertDescription>
        </Alert>
      )}
      {missingSeats > 0 && (
        <Alert className="border-warn-border bg-warn-soft text-warn-fg">
          <AlertTriangle aria-hidden="true" /><AlertTitle>Capacidad insuficiente</AlertTitle>
          <AlertDescription>
            Lugares faltantes: {missingSeats}. {messages.unassignedCapacity}
          </AlertDescription>
        </Alert>
      )}
      {!mustReload && unassigned > 0 && missingSeats === 0 && (
        <Alert className="border-info-border bg-info-soft text-info">
          <Info aria-hidden="true" /><AlertTitle>{messages.unassigned}</AlertTitle>
          <AlertDescription>
            {unassigned} {unassigned === 1 ? 'estudiante sin ambiente' : 'estudiantes sin ambiente'}:
            {data.editable ? (
              <> {messages.unassignedEditable}</>
            ) : (
              <> consulte estos casos con el docente responsable del examen.</>
            )}
          </AlertDescription>
        </Alert>
      )}
      {data.total_habilitados === 0 && data.editable && (
        <Alert className="border-info-border bg-info-soft text-info">
          <Info aria-hidden="true" /><AlertTitle>Sin estudiantes habilitados</AlertTitle>
          <AlertDescription>{messages.zero}</AlertDescription>
        </Alert>
      )}
    </>
  )
}
