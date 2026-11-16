import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type {
  ExceptionalCandidate,
  ExceptionalGroup,
  RegisterExceptionalInput
} from '../types/exceptional-student.types'
import { EXCEPTIONAL_MESSAGES } from './exceptionalStudentMessages'

const MAX_REASON_LENGTH = 255

interface ExceptionalRegistrationFormProps {
  group: ExceptionalGroup
  candidate: ExceptionalCandidate
  registering: boolean
  /** Error que devolvió el servidor al registrar. */
  error: string
  onSubmit: (input: RegisterExceptionalInput) => Promise<boolean>
  onCancel: () => void
}

/**
 * Carrera con la que va preseleccionado: la única del grupo o, si hay varias, la de su última
 * inscripción cuando el grupo también la atiende. El docente puede cambiarla.
 */
function initialCareer(group: ExceptionalGroup, candidate: ExceptionalCandidate): number | '' {
  if (group.carreras.length === 1) return group.carreras[0].id_carrera

  const last = candidate.carrera?.id_carrera
  const attended = group.carreras.some((career) => career.id_carrera === last)

  return attended && last !== undefined ? last : ''
}

/** Carrera y motivo del registro excepcional; el motivo es obligatorio. */
export function ExceptionalRegistrationForm({
  group,
  candidate,
  registering,
  error,
  onSubmit,
  onCancel
}: ExceptionalRegistrationFormProps) {
  const [careerId, setCareerId] = useState<number | ''>(() => initialCareer(group, candidate))
  const [reason, setReason] = useState('')
  const [reasonError, setReasonError] = useState('')
  const [careerError, setCareerError] = useState('')
  const hasSeveralCareers = group.carreras.length > 1

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()

    const missingReason = reason.trim() === ''
    const missingCareer = hasSeveralCareers && careerId === ''

    setReasonError(missingReason ? EXCEPTIONAL_MESSAGES.reasonRequired : '')
    setCareerError(missingCareer ? EXCEPTIONAL_MESSAGES.careerRequired : '')

    if (missingReason || missingCareer) return

    await onSubmit({
      studentId: candidate.id_estudiante,
      reason: reason.trim(),
      careerId: hasSeveralCareers && careerId !== '' ? careerId : undefined
    })
  }

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className="space-y-4">
      {hasSeveralCareers ? (
        <div className="space-y-2">
          <Label htmlFor="exceptional-career">Carrera</Label>
          <select
            id="exceptional-career"
            value={careerId}
            onChange={(event) =>
              setCareerId(event.target.value === '' ? '' : Number(event.target.value))
            }
            aria-invalid={careerError !== ''}
            className="h-11 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-96"
          >
            <option value="">Elija una carrera</option>
            {group.carreras.map((career) => (
              <option key={career.id_carrera} value={career.id_carrera}>
                {career.nombre}
              </option>
            ))}
          </select>
          {careerError && (
            <p role="alert" className="text-sm text-destructive">
              {careerError}
            </p>
          )}
        </div>
      ) : (
        <p className="text-sm">
          <span className="text-muted-foreground">Carrera en el grupo: </span>
          {group.carreras[0]?.nombre}
        </p>
      )}

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
