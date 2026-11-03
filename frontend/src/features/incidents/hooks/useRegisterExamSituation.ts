import { useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { examSituationService } from '../services/examSituationService'
import type {
  ExamSituationContext, SituationCreated, SituationErrors, SituationInput,
} from '../types/exam-situation.types'

export function useRegisterExamSituation(context: ExamSituationContext, onRegistered: (result: SituationCreated) => void) {
  const [busy, setBusy] = useState(false)
  const [errors, setErrors] = useState<SituationErrors>({})
  const [error, setError] = useState<string | null>(null)
  const [blocked, setBlocked] = useState(false)
  const submitting = useRef(false)

  async function submit(input: SituationInput): Promise<void> {
    if (submitting.current || blocked) return
    const next: SituationErrors = {}
    const reasonLength = Array.from(input.reason.trim()).length
    if (input.type === 'CAMBIO_AMBIENTE') {
      if (reasonLength === 0) next.motivo = 'Indique el motivo del cambio.'
      if (!context.rooms.some((room) => room.id_ambiente !== context.room.id_ambiente
        && String(room.id_ambiente) === input.destinationRoomId)) {
        next.id_ambiente_destino = 'Seleccione otro ambiente de este examen.'
      }
    } else if (reasonLength < 10) {
      next.motivo = 'Describa lo ocurrido (mínimo 10 caracteres).'
    }
    if (reasonLength > 500) next.motivo = 'El texto no debe superar los 500 caracteres.'
    setErrors(next)
    setError(null)
    if (Object.keys(next).length) return
    submitting.current = true
    setBusy(true)
    let result: SituationCreated
    try {
      result = await examSituationService.create(context.examId, context.student.id_estudiante, input)
    } catch (cause: unknown) {
      if (cause instanceof ApiError && cause.status === 422) {
        const fields: SituationErrors = {}
        for (const field of ['tipo', 'motivo', 'id_ambiente_destino'] as const) {
          if (cause.errors[field]?.[0]) fields[field] = cause.errors[field][0]
        }
        setErrors(fields)
        if (!Object.keys(fields).length) setError(cause.message)
      } else if (cause instanceof ApiError && [403, 404, 409].includes(cause.status)) {
        setBlocked(true)
        setError(`${cause.message} Cierre el formulario y actualice la vista.`)
      } else {
        setBlocked(true)
        setError('No se pudo confirmar el resultado. Cierre el formulario y revise Situaciones antes de volver a registrar.')
      }
      return
    } finally {
      submitting.current = false
      setBusy(false)
    }
    onRegistered(result)
  }

  return { busy, errors, error, blocked, submit }
}