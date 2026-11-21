import { useState } from 'react'
import { ArrowLeft } from 'lucide-react'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { useRectifiableRecord } from '../hooks/useRectifiableRecord'
import { useRectifyWithoutConfirmation, useRequestRectification } from '../hooks/useRectifyRecord'
import type { RectifiableRecordDetail, SentRectificationRequest } from '../types/riskRectification.types'
import { rectifyModeOf } from '../utils/rectificationMode'
import { RectificationStatusPanel } from './RectificationStatusPanel'
import { RectifyConfirmDialog, type ConfirmMode } from './RectifyConfirmDialog'
import { RectifyReasonForm } from './RectifyReasonForm'
import { RectifyRecordCard } from './RectifyRecordCard'

interface RectifyRecordPanelProps {
  recordId: number
  /** Vuelve a la lista de registros del estudiante sin cambiar nada. */
  onBack: () => void
  /** El registro ya quedó anulado (sin confirmación del docente): la página vuelve a la lista y avisa. */
  onRectified: (record: RectifiableRecordDetail) => void
  /** La solicitud ya se envió al docente: la página vuelve a la lista y avisa. */
  onRequested: (record: RectifiableRecordDetail, request: SentRectificationRequest) => void
}

/**
 * Un registro abierto para rectificar: arriba toda su información de solo lectura, el estado de sus solicitudes
 * y, debajo, el motivo.
 *
 * Rectificar es pedirle al docente que aprobó el reporte que lo confirme: el registro sigue activo hasta que
 * lo acepte. Solo si ese docente ya no es docente activo y pasó el plazo configurado se anula directamente.
 * Son dos pasos: el motivo válido abre la confirmación y solo el botón del diálogo envía.
 */
export function RectifyRecordPanel({ recordId, onBack, onRectified, onRequested }: RectifyRecordPanelProps) {
  const { record, status, error, reload } = useRectifiableRecord(recordId)
  const request = useRequestRectification()
  const direct = useRectifyWithoutConfirmation()
  const [pendingReason, setPendingReason] = useState<string | null>(null)

  const mode = record === null ? null : rectifyModeOf(record.rectificacion)
  const confirmMode: ConfirmMode | null = mode === 'request' || mode === 'direct' || mode === 'detect' ? mode : null
  const active = confirmMode === 'request' ? request : direct
  const failedAction = request.error !== null ? 'request' : 'direct'
  const submitError = request.error ?? direct.error

  async function handleConfirm() {
    if (record === null || pendingReason === null || confirmMode === null) return

    const input = { motivo: pendingReason }

    if (confirmMode === 'request') {
      const sent = await request.submit(record.id_cr_estudiante, input)

      setPendingReason(null)
      if (sent !== null) onRequested(record, sent)
      else reload()

      return
    }

    const voided = await direct.submit(record.id_cr_estudiante, input)

    setPendingReason(null)
    if (voided !== null) onRectified(record)
    else reload()
  }

  return (
    <div className="space-y-4">
      <Button type="button" variant="ghost" size="sm" onClick={onBack}>
        <ArrowLeft className="size-4" aria-hidden="true" />
        Volver a los registros
      </Button>

      {record === null && status === 'loading' && (
        <Card className="p-0">
          <LoadingState rows={4} label="Cargando el registro" />
        </Card>
      )}

      {status === 'error' && error !== null && (
        <Card className="p-0">
          <ErrorState error={error} onRetry={reload} />
        </Card>
      )}

      {record !== null && submitError !== null && (
        <Alert variant="destructive" role="alert">
          <AlertTitle>{failedAction === 'request' ? 'No se pudo enviar la solicitud' : 'No se pudo anular el registro'}</AlertTitle>
          <AlertDescription>
            <p>{submitError.message}</p>
            {submitError.status === 409 && (
              <Button type="button" variant="outline" size="sm" className="mt-2" onClick={onBack}>
                Volver a los registros
              </Button>
            )}
          </AlertDescription>
        </Alert>
      )}

      {record !== null && confirmMode !== null && (
        <>
          <RectifyRecordCard record={record} />
          <RectificationStatusPanel summary={record.rectificacion} />

          <RectifyReasonForm
            submitLabel={confirmMode === 'request' ? 'Enviar solicitud al docente' : 'Anular registro'}
            variant={confirmMode === 'request' ? 'default' : 'danger'}
            disabled={active.isSubmitting}
            onReview={(reason) => {
              request.clearError()
              direct.clearError()
              setPendingReason(reason)
            }}
            onCancel={onBack}
          />

          <RectifyConfirmDialog
            record={record}
            mode={confirmMode}
            reason={pendingReason}
            isSubmitting={active.isSubmitting}
            onConfirm={handleConfirm}
            onCancel={() => setPendingReason(null)}
          />
        </>
      )}

      {record !== null && confirmMode === null && (
        <>
          <RectifyRecordCard record={record} />
          <RectificationStatusPanel summary={record.rectificacion} />
        </>
      )}
    </div>
  )
}
