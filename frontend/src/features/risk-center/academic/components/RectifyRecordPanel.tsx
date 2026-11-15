import { useState } from 'react'
import { ArrowLeft } from 'lucide-react'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { useRectifiableRecord } from '../hooks/useRectifiableRecord'
import { useRectifyRecord } from '../hooks/useRectifyRecord'
import type { RectifiableRecordDetail } from '../types/riskRectification.types'
import { RectifyConfirmDialog } from './RectifyConfirmDialog'
import { RectifyReasonForm } from './RectifyReasonForm'
import { RectifyRecordCard } from './RectifyRecordCard'

interface RectifyRecordPanelProps {
  recordId: number
  /** Vuelve a la lista de registros del estudiante sin cambiar nada. */
  onBack: () => void
  /** El registro ya quedó rectificado: la página vuelve a la lista y avisa. */
  onRectified: (record: RectifiableRecordDetail) => void
}

/**
 * Un registro abierto para rectificar: arriba toda su información de solo lectura y debajo el motivo.
 *
 * Son dos pasos: el motivo válido abre la confirmación, y solo «Sí, rectificar» envía. Cancelar en cualquiera
 * de los dos no guarda nada. No hay forma de editar ni borrar el registro desde aquí.
 */
export function RectifyRecordPanel({ recordId, onBack, onRectified }: RectifyRecordPanelProps) {
  const { record, status, error, reload } = useRectifiableRecord(recordId)
  const rectification = useRectifyRecord()
  const [pendingReason, setPendingReason] = useState<string | null>(null)

  async function handleConfirm() {
    if (record === null || pendingReason === null) return

    const rectified = await rectification.submit(record.id_cr_estudiante, { motivo: pendingReason })

    setPendingReason(null)

    if (rectified !== null) onRectified(record)
  }

  const submitError = rectification.error

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

      {record !== null && (
        <>
          {submitError !== null && (
            <Alert variant="destructive" role="alert">
              <AlertTitle>No se pudo rectificar el registro</AlertTitle>
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

          <RectifyRecordCard record={record} />

          <RectifyReasonForm
            disabled={rectification.isSubmitting}
            onReview={(reason) => {
              rectification.clearError()
              setPendingReason(reason)
            }}
            onCancel={onBack}
          />

          <RectifyConfirmDialog
            record={record}
            reason={pendingReason}
            isSubmitting={rectification.isSubmitting}
            onConfirm={handleConfirm}
            onCancel={() => setPendingReason(null)}
          />
        </>
      )}
    </div>
  )
}
