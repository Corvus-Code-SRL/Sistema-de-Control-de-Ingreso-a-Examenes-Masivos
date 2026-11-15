import { useState } from 'react'
import { ArrowLeft, CheckCircle2, RefreshCw } from 'lucide-react'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { useRectifiableRecord } from '../hooks/useRectifiableRecord'
import { useRectificationFaultTypes } from '../hooks/useRectificationFaultTypes'
import { useRectifyRecord } from '../hooks/useRectifyRecord'
import { RecordVersionHistory } from './RecordVersionHistory'
import { RectificationStateBadge } from './RectificationStateBadge'
import { RectifyConfirmDialog } from './RectifyConfirmDialog'
import { RectifyRecordForm, type PendingRectification } from './RectifyRecordForm'

interface RectifyRecordPanelProps {
  recordId: number
  /** Vuelve a la lista de registros del estudiante sin cambiar nada. */
  onBack: () => void
}

/**
 * Un registro abierto para rectificar: el formulario, la confirmación en dos pasos y el historial.
 *
 * Cancelar en el formulario o en el diálogo no guarda nada. Tras confirmar, el historial muestra la versión
 * anterior como «Anterior» (fecha, autor, motivo) y la nueva como «Vigente». No hay forma de borrar aquí.
 */
export function RectifyRecordPanel({ recordId, onBack }: RectifyRecordPanelProps) {
  const { record, status, error, reload, replace } = useRectifiableRecord(recordId)
  const types = useRectificationFaultTypes()
  const rectification = useRectifyRecord()
  const [pending, setPending] = useState<PendingRectification | null>(null)
  const [saved, setSaved] = useState(false)

  async function handleConfirm() {
    if (record === null || pending === null) return

    const updated = await rectification.submit(record.id_cr_estudiante, {
      id_tipo_falta: pending.faultTypeId,
      descripcion: pending.description,
      motivo: pending.reason,
      version_base: record.version_vigente,
    })

    setPending(null)

    if (updated !== null) {
      replace(updated)
      setSaved(true)
    }
  }

  function handleReload() {
    rectification.clearError()
    setSaved(false)
    reload()
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
          {saved && (
            <Alert variant="info" role="status">
              <CheckCircle2 aria-hidden="true" />
              <AlertTitle>Rectificación registrada</AlertTitle>
              <AlertDescription>
                La versión anterior quedó en el historial como «Anterior» y la nueva es la «Vigente».
              </AlertDescription>
            </Alert>
          )}

          {submitError !== null && (
            <Alert variant="destructive" role="alert">
              <AlertTitle>No se pudo rectificar el registro</AlertTitle>
              <AlertDescription>
                <p>{submitError.message}</p>
                {submitError.status === 409 && (
                  <Button type="button" variant="outline" size="sm" className="mt-2" onClick={handleReload}>
                    <RefreshCw className="size-4" aria-hidden="true" />
                    Recargar el registro
                  </Button>
                )}
              </AlertDescription>
            </Alert>
          )}

          <Card className="space-y-5 p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="sciem-h3">Rectificar registro</h2>
                <p className="text-sm text-muted-foreground">
                  Solo se pueden corregir el tipo de falta y la descripción. El motivo es obligatorio.
                </p>
              </div>
              <RectificationStateBadge
                state={record.estado}
                count={record.numero_rectificaciones}
                lastAt={record.ultima_rectificacion}
              />
            </div>

            <RectifyRecordForm
              key={record.version_vigente}
              record={record}
              faultTypes={types.faultTypes}
              typesUnavailable={types.status === 'error'}
              disabled={rectification.isSubmitting}
              onReview={(values) => {
                rectification.clearError()
                setSaved(false)
                setPending(values)
              }}
              onCancel={onBack}
            />
          </Card>

          <Card className="p-5">
            <RecordVersionHistory versions={record.historial} />
          </Card>

          <RectifyConfirmDialog
            pending={pending}
            current={{ faultTypeName: record.tipo_falta.nombre, description: record.descripcion }}
            faultTypes={types.faultTypes}
            isSubmitting={rectification.isSubmitting}
            onConfirm={handleConfirm}
            onCancel={() => setPending(null)}
          />
        </>
      )}
    </div>
  )
}
