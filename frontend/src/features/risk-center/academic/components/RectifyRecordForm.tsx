import { useState, type FormEvent } from 'react'
import { FormSelect } from '@/components/common/FormSelect'
import { ReadOnlyField } from '@/components/common/ReadOnlyField'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { RectifiableRecordDetail, RectificationFaultType } from '../types/riskRectification.types'
import { formatDate, formatPlainDate } from '../utils/format'
import {
  MAX_DESCRIPTION_LENGTH,
  MAX_REASON_LENGTH,
  MIN_REASON_LENGTH,
  hasErrors,
  normalizeDescription,
  validateRectification,
  type RectificationErrors,
} from '../utils/rectificationValidators'

/** Lo que se confirma en el segundo paso: valores ya limpios, listos para enviar. */
export interface PendingRectification {
  faultTypeId: number
  description: string | null
  reason: string
}

interface RectifyRecordFormProps {
  record: RectifiableRecordDetail
  faultTypes: RectificationFaultType[]
  /** El catálogo no cargó: sin tipos no se puede rectificar. */
  typesUnavailable: boolean
  disabled?: boolean
  /** Primer paso: los datos son válidos y distintos de los vigentes; falta la confirmación. */
  onReview: (pending: PendingRectification) => void
  onCancel: () => void
}

/**
 * Formulario de rectificación: solo el tipo de falta y la descripción se editan, con el motivo obligatorio.
 * El estudiante, el examen y las fechas se muestran bloqueados. «Rectificar» no guarda: abre la confirmación.
 */
export function RectifyRecordForm({
  record,
  faultTypes,
  typesUnavailable,
  disabled = false,
  onReview,
  onCancel,
}: RectifyRecordFormProps) {
  const [faultTypeId, setFaultTypeId] = useState<number | null>(record.tipo_falta.id_falta)
  const [description, setDescription] = useState(record.descripcion ?? '')
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<RectificationErrors>({})

  const options = faultTypes.map((faultType) => ({
    value: String(faultType.id_falta),
    label: faultType.nombre,
  }))

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const found = validateRectification(
      { faultTypeId, description, reason },
      { faultTypeId: record.tipo_falta.id_falta, description: record.descripcion }
    )
    setErrors(found)

    if (hasErrors(found) || faultTypeId === null) return

    onReview({ faultTypeId, description: normalizeDescription(description), reason: reason.trim() })
  }

  return (
    <form onSubmit={handleSubmit} noValidate aria-label="Rectificar registro" className="space-y-5">
      <div className="grid gap-4 sm:grid-cols-2">
        <ReadOnlyField label="Estudiante" value={`${record.estudiante.nombre_completo} (${record.estudiante.cod_sis})`} />
        <ReadOnlyField
          label="Examen"
          value={record.examen.materia === null ? record.examen.nombre : `${record.examen.nombre} · ${record.examen.materia}`}
        />
        <ReadOnlyField label="Fecha del examen" value={formatPlainDate(record.examen.fecha)} />
        <ReadOnlyField label="Fecha del registro" value={formatDate(record.fecha_registro)} />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="rectify-fault-type" className="text-xs font-medium">
          Tipo de falta
        </Label>
        <FormSelect
          id="rectify-fault-type"
          ariaLabel="Tipo de falta"
          value={faultTypeId === null ? undefined : String(faultTypeId)}
          placeholder="Seleccione el tipo de falta"
          options={options}
          onValueChange={(value) => setFaultTypeId(Number(value))}
          disabled={disabled || typesUnavailable}
          invalid={errors.faultType !== undefined}
        />
        {typesUnavailable && (
          <p role="alert" className="text-xs text-danger-fg">
            No se pudo cargar el catálogo de tipos de falta. Recargue la página para volver a intentarlo.
          </p>
        )}
        {errors.faultType !== undefined && (
          <p role="alert" className="text-xs text-danger-fg">
            {errors.faultType}
          </p>
        )}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="rectify-description" className="text-xs font-medium">
          Descripción
        </Label>
        <Textarea
          id="rectify-description"
          value={description}
          onChange={(event) => setDescription(event.target.value)}
          rows={4}
          disabled={disabled}
          aria-invalid={errors.description !== undefined || undefined}
        />
        <p className="text-xs text-muted-foreground">
          Opcional. {description.length}/{MAX_DESCRIPTION_LENGTH} caracteres.
        </p>
        {errors.description !== undefined && (
          <p role="alert" className="text-xs text-danger-fg">
            {errors.description}
          </p>
        )}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="rectify-reason" className="text-xs font-medium">
          Motivo de la rectificación <span aria-hidden="true">*</span>
        </Label>
        <Textarea
          id="rectify-reason"
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          rows={3}
          maxLength={MAX_REASON_LENGTH + 100}
          disabled={disabled}
          aria-required="true"
          aria-invalid={errors.reason !== undefined || undefined}
          aria-describedby="rectify-reason-hint"
        />
        <p id="rectify-reason-hint" className="text-xs text-muted-foreground">
          Obligatorio, entre {MIN_REASON_LENGTH} y {MAX_REASON_LENGTH} caracteres. Queda en el historial y en la
          bitácora.
        </p>
        {errors.reason !== undefined && (
          <p role="alert" className="text-xs text-danger-fg">
            {errors.reason}
          </p>
        )}
      </div>

      {errors.unchanged !== undefined && (
        <p role="alert" className="rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-fg">
          {errors.unchanged}
        </p>
      )}

      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" onClick={onCancel} disabled={disabled}>
          Cancelar
        </Button>
        <Button type="submit" disabled={disabled || typesUnavailable}>
          Rectificar
        </Button>
      </div>
    </form>
  )
}
