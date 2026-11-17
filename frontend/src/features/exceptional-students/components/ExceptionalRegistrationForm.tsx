import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { ExceptionalCandidate, RegisterExceptionalInput } from '../types/exceptional-student.types'
import { EXCEPTIONAL_MESSAGES } from './exceptionalStudentMessages'

const MIN_REASON_LENGTH = 5
const MAX_REASON_LENGTH = 255

interface ExceptionalRegistrationFormProps {
  candidate: ExceptionalCandidate
  registering: boolean
  /** Error que devolvió el servidor al registrar. */
  error: string
  onSubmit: (input: RegisterExceptionalInput) => Promise<boolean>
  onCancel: () => void
}

/** Motivo del registro excepcional: obligatorio y con un mínimo de caracteres. */
export function ExceptionalRegistrationForm({
  candidate,
  registering,
  error,
  onSubmit,
  onCancel
}: ExceptionalRegistrationFormProps) {
  const [reason, setReason] = useState('')
  const [reasonError, setReasonError] = useState('')

  function validate(): string {
    const trimmed = reason.trim()

    if (trimmed === '') return EXCEPTIONAL_MESSAGES.reasonRequired
    if (trimmed.length < MIN_REASON_LENGTH) return EXCEPTIONAL_MESSAGES.reasonTooShort

    return ''
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()

    const problem = validate()

    setReasonError(problem)

    if (problem !== '') return

    await onSubmit({ studentId: candidate.id_estudiante, reason: reason.trim() })
  }

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className="space-y-4">
      <div className="space-y-2">
        <Label htmlFor="exceptional-reason">Motivo del registro excepcional</Label>
        <Textarea
          id="exceptional-reason"
          value={reason}
          maxLength={MAX_REASON_LENGTH}
          rows={3}
          onChange={(event) => setReason(event.target.value)}
          aria-invalid={reasonError !== ''}
        />
        {reasonError && (
          <p role="alert" className="text-sm text-destructive">
            {reasonError}
          </p>
        )}
      </div>

      {error && (
        <p role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}

      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={registering}>
          {registering ? 'Registrando…' : 'Registrar estudiante'}
        </Button>
        <Button type="button" variant="outline" disabled={registering} onClick={onCancel}>
          Cancelar
        </Button>
      </div>
    </form>
  )
}
