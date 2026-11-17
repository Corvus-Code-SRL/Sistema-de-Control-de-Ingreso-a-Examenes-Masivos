import { useMemo, useState, type FormEvent } from 'react'
import { FormSelect } from '@/components/common/FormSelect'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type {
  ExceptionalCandidate,
  ExceptionalOptions,
  RegisterExceptionalInput
} from '../types/exceptional-student.types'
import { EXCEPTIONAL_MESSAGES } from './exceptionalStudentMessages'

const MIN_REASON_LENGTH = 5
const MAX_REASON_LENGTH = 255

interface ExceptionalRegistrationFormProps {
  candidate: ExceptionalCandidate
  options: ExceptionalOptions | null
  optionsError: string
  registering: boolean
  /** Error que devolvió el servidor al registrar. */
  error: string
  onSubmit: (input: RegisterExceptionalInput) => Promise<boolean>
  onCancel: () => void
}

/**
 * Grupo en el que se contará al estudiante, carrera (solo si el grupo ofrece más de una), ambiente y motivo.
 * El grupo se preselecciona cuando el examen tiene uno solo; el ambiente se elige siempre.
 */
export function ExceptionalRegistrationForm({
  candidate,
  options,
  optionsError,
  registering,
  error,
  onSubmit,
  onCancel
}: ExceptionalRegistrationFormProps) {
  const singleGroupId = options?.grupos.length === 1 ? options.grupos[0].id_grupo : null
  const [pickedGroupId, setPickedGroupId] = useState<number | null>(null)
  const [pickedCareerId, setPickedCareerId] = useState<number | null>(null)
  const [roomId, setRoomId] = useState<number | null>(null)
  const [reason, setReason] = useState('')
  const [problem, setProblem] = useState('')

  const groupId = pickedGroupId ?? singleGroupId
  const careers = useMemo(
    () => options?.carreras.filter((career) => career.id_grupo === groupId) ?? [],
    [options, groupId]
  )
  const askCareer = careers.length > 1
  const careerId = askCareer ? pickedCareerId : (careers[0]?.id_carrera ?? null)
  const room = options?.ambientes.find((item) => item.id_ambiente === roomId) ?? null

  function validate(): string {
    const trimmed = reason.trim()

    if (groupId === null) return EXCEPTIONAL_MESSAGES.groupRequired
    if (careerId === null) return EXCEPTIONAL_MESSAGES.careerRequired
    if (roomId === null) return EXCEPTIONAL_MESSAGES.roomRequired
    if (trimmed === '') return EXCEPTIONAL_MESSAGES.reasonRequired
    if (trimmed.length < MIN_REASON_LENGTH) return EXCEPTIONAL_MESSAGES.reasonTooShort

    return ''
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()

    const found = validate()

    setProblem(found)

    if (found !== '' || groupId === null || careerId === null || roomId === null) return

    await onSubmit({ studentId: candidate.id_estudiante, reason: reason.trim(), groupId, careerId, roomId })
  }

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className="space-y-4">
      {optionsError && (
        <p role="alert" className="text-sm text-destructive">
          {optionsError}
        </p>
      )}
      {!options && !optionsError && <p role="status">Cargando opciones del examen…</p>}

      {options && (
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="exceptional-group">{EXCEPTIONAL_MESSAGES.groupLabel}</Label>
            <FormSelect
              id="exceptional-group"
              value={groupId === null ? '' : String(groupId)}
              placeholder="Elija un grupo"
              options={options.grupos.map((group) => ({ value: String(group.id_grupo), label: group.nombre }))}
              onValueChange={(value) => {
                setPickedGroupId(Number(value))
                setPickedCareerId(null)
              }}
            />
          </div>

          {askCareer && (
            <div className="space-y-1.5">
              <Label htmlFor="exceptional-career">Carrera</Label>
              <FormSelect
                id="exceptional-career"
                value={careerId === null ? '' : String(careerId)}
                placeholder="Elija una carrera"
                options={careers.map((career) => ({ value: String(career.id_carrera), label: career.nombre }))}
                onValueChange={(value) => setPickedCareerId(Number(value))}
              />
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="exceptional-room">Ambiente</Label>
            <FormSelect
              id="exceptional-room"
              value={roomId === null ? '' : String(roomId)}
              placeholder="Elija un ambiente"
              options={options.ambientes.map((item) => ({
                value: String(item.id_ambiente),
                label: `${item.nombre} (${item.ocupados}/${item.capacidad})`
              }))}
              onValueChange={(value) => setRoomId(Number(value))}
            />
          </div>
        </div>
      )}

      {room !== null && room.ocupados >= room.capacidad && (
        <p role="status" className="text-sm text-warn-fg">
          {EXCEPTIONAL_MESSAGES.roomFull}
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
          aria-invalid={problem !== ''}
        />
      </div>

      {problem && (
        <p role="alert" className="text-sm text-destructive">
          {problem}
        </p>
      )}

      {error && (
        <p role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}

      <div className="flex flex-wrap gap-2">
        <Button type="submit" disabled={registering || !options}>
          {registering ? 'Registrando…' : 'Registrar estudiante'}
        </Button>
        <Button type="button" variant="outline" disabled={registering} onClick={onCancel}>
          Cancelar
        </Button>
      </div>
    </form>
  )
}
