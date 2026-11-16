import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  MAX_REASON_LENGTH,
  MIN_REASON_LENGTH,
  validateReason,
} from '../utils/rectificationValidators'

interface RectifyReasonFormProps {
  disabled?: boolean
  /** Primer paso: el motivo es válido (ya recortado); falta la confirmación. */
  onReview: (reason: string) => void
  onCancel: () => void
}

/** Único dato que se pide para rectificar: el motivo. «Rectificar registro» no guarda: abre la confirmación. */
export function RectifyReasonForm({ disabled = false, onReview, onCancel }: RectifyReasonFormProps) {
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const message = validateReason(reason)
    setError(message)

    if (message === null) onReview(reason.trim())
  }

  return (
    <Card className="p-5">
      <form onSubmit={handleSubmit} noValidate aria-label="Rectificar registro" className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="rectify-reason" className="text-sm font-medium">
            Motivo de la rectificación <span aria-hidden="true">*</span>
          </Label>
          <Textarea
            id="rectify-reason"
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            rows={4}
            maxLength={MAX_REASON_LENGTH + 100}
            disabled={disabled}
            aria-required="true"
            aria-invalid={error !== null || undefined}
            aria-describedby={error === null ? 'rectify-reason-hint' : 'rectify-reason-hint rectify-reason-error'}
          />
          <p id="rectify-reason-hint" className="text-xs text-muted-foreground">
            Obligatorio, entre {MIN_REASON_LENGTH} y {MAX_REASON_LENGTH} caracteres. Explique por qué el registro
            no correspondía; queda en la bitácora.
          </p>
          {error !== null && (
            <p id="rectify-reason-error" role="alert" className="text-xs text-danger-fg">
              {error}
            </p>
          )}
        </div>

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" size="lg" onClick={onCancel} disabled={disabled}>
            Cancelar
          </Button>
          <Button type="submit" variant="danger" size="lg" disabled={disabled}>
            Rectificar registro
          </Button>
        </div>
      </form>
    </Card>
  )
}
