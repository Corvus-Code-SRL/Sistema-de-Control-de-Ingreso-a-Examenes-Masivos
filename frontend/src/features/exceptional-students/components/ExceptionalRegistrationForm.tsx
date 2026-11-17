import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import type { ExceptionalCandidate, ExceptionalOptions, RegisterExceptionalInput } from '../types/exceptional-student.types'
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

/** Motivo del registro excepcional: obligatorio y con un mínimo de caracteres. */
export function ExceptionalRegistrationForm({
  candidate,
  options,
  optionsError,
  registering,
  error,
  onSubmit,
  onCancel
}: ExceptionalRegistrationFormProps) {
  const [groupId, setGroupId] = useState(0)
  const [careerId, setCareerId] = useState(0)
  const [roomId, setRoomId] = useState(0)
  const careers = useMemo(() => options?.carreras.filter((c) => c.id_grupo === groupId) ?? [], [options, groupId])
  useEffect(() => {
    if (!options) return
    setGroupId((previous) => options.grupos.some((g) => g.id_grupo === previous) ? previous : (options.grupos[0]?.id_grupo ?? 0))
    setRoomId((previous) => options.ambientes.some((r) => r.id_ambiente === previous) ? previous :
      (options.ambientes.reduce((best, room) => !best || room.capacidad - room.ocupados > best.capacidad - best.ocupados ? room : best, options.ambientes[0])?.id_ambiente ?? 0))
  }, [options])
  useEffect(() => {
    setCareerId((previous) => careers.some((c) => c.id_carrera === previous) ? previous :
      (careers.find((c) => c.id_carrera === options?.grupos.find((g) => g.id_grupo === groupId)?.carrera_principal)?.id_carrera ?? careers[0]?.id_carrera ?? 0))
  }, [careers, groupId, options])
  const [reason, setReason] = useState('')
  const [reasonError, setReasonError] = useState('')

  function validate(): string {
    const trimmed = reason.trim()

    if (!groupId || !careerId || !roomId) return 'Seleccione grupo, carrera y ambiente.'
    if (trimmed === '') return EXCEPTIONAL_MESSAGES.reasonRequired
    if (trimmed.length < MIN_REASON_LENGTH) return EXCEPTIONAL_MESSAGES.reasonTooShort

    return ''
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()

    const problem = validate()

    setReasonError(problem)

    if (problem !== '') return

    await onSubmit({ studentId: candidate.id_estudiante, reason: reason.trim(), groupId, careerId, roomId })
  }

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className="space-y-4">
      {optionsError && <p role="alert" className="text-sm text-destructive">{optionsError}</p>}
      {!options && !optionsError && <p role="status">Cargando opciones del examen…</p>}
      {options && <div className="grid gap-3 sm:grid-cols-3">
        <label className="space-y-1 text-sm">Grupo
          <select aria-label="Grupo" className="w-full rounded-md border p-2" value={groupId} onChange={(e) => setGroupId(Number(e.target.value))}>
            {options.grupos.map((g) => <option key={g.id_grupo} value={g.id_grupo}>{g.nombre}</option>)}
          </select>
        </label>
        <label className="space-y-1 text-sm">Carrera
          <select aria-label="Carrera" className="w-full rounded-md border p-2" value={careerId} onChange={(e) => setCareerId(Number(e.target.value))}>
            {careers.map((c) => <option key={c.id_carrera} value={c.id_carrera}>{c.nombre}</option>)}
          </select>
        </label>
        <label className="space-y-1 text-sm">Ambiente
          <select aria-label="Ambiente" className="w-full rounded-md border p-2" value={roomId} onChange={(e) => setRoomId(Number(e.target.value))}>
            {options.ambientes.map((r) => <option key={r.id_ambiente} value={r.id_ambiente}>{r.nombre} ({r.ocupados}/{r.capacidad})</option>)}
          </select>
        </label>
      </div>}
      {options?.ambientes.some((r) => r.id_ambiente === roomId && r.ocupados >= r.capacidad) &&
        <p role="status" className="text-sm text-amber-700">Advertencia: el ambiente está lleno; puede registrar igualmente.</p>}
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
        <Button type="submit" disabled={registering || !options || !groupId || !careerId || !roomId}>
          {registering ? 'Registrando…' : 'Registrar estudiante'}
        </Button>
        <Button type="button" variant="outline" disabled={registering} onClick={onCancel}>
          Cancelar
        </Button>
      </div>
    </form>
  )
}
