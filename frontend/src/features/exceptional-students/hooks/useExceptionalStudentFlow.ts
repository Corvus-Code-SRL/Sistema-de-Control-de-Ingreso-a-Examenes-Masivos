import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { EXCEPTIONAL_MESSAGES } from '../components/exceptionalStudentMessages'
import { exceptionalStudentService } from '../services/exceptionalStudentService'
import type {
  ExceptionalCandidate,
  ExceptionalExam,
  RegisterExceptionalInput
} from '../types/exceptional-student.types'

export type SearchState =
  | { status: 'idle' }
  | { status: 'searching' }
  | { status: 'found'; candidate: ExceptionalCandidate }
  | { status: 'notFound' }
  | { status: 'error'; message: string }

/** El primer error de campo de un 422 o el mensaje del servidor; un 5xx o una caída, el genérico. */
function failureMessage(cause: unknown, fallback: string): string {
  if (!(cause instanceof ApiError) || cause.isUnavailable) return fallback

  const [first] = Object.values(cause.errors).flat()

  return first ?? cause.message
}

/**
 * Búsqueda y registro de un estudiante excepcional en un examen.
 *
 * Una búsqueda nueva cancela la anterior y una respuesta tardía nunca pisa a la más nueva. Registrar
 * no se repite mientras hay un registro en curso. Si el servidor responde que el examen ya no está En curso
 * (409), avisa con `onExamChanged` y el mensaje del servidor para que la vista vuelva a cargar la lista.
 */
export function useExceptionalStudentFlow(
  exam: ExceptionalExam,
  onExamChanged: (message: string) => void
) {
  const [search, setSearch] = useState<SearchState>({ status: 'idle' })
  const [registering, setRegistering] = useState(false)
  const [formError, setFormError] = useState('')
  const [success, setSuccess] = useState('')
  const request = useRef<AbortController | null>(null)
  const busy = useRef(false)

  useEffect(
    () => () => {
      request.current?.abort()
    },
    []
  )

  const find = useCallback(
    async (code: string) => {
      request.current?.abort()
      const controller = new AbortController()
      request.current = controller
      setSuccess('')
      setFormError('')
      setSearch({ status: 'searching' })

      try {
        const { data } = await exceptionalStudentService.search(
          exam.id_examen,
          code.trim(),
          controller.signal
        )

        if (!controller.signal.aborted) setSearch({ status: 'found', candidate: data })
      } catch (cause) {
        if (controller.signal.aborted) return

        if (
          cause instanceof ApiError &&
          cause.isNotFound &&
          cause.message === EXCEPTIONAL_MESSAGES.notFound
        ) {
          setSearch({ status: 'notFound' })
          return
        }

        setSearch({
          status: 'error',
          message: failureMessage(cause, EXCEPTIONAL_MESSAGES.searchFailed)
        })
      }
    },
    [exam.id_examen]
  )

  /** Devuelve si el registro se hizo, para que la vista limpie lo que escribió. */
  const register = useCallback(
    async (input: RegisterExceptionalInput): Promise<boolean> => {
      if (busy.current) return false

      busy.current = true
      setRegistering(true)
      setFormError('')
      setSuccess('')

      try {
        const result = await exceptionalStudentService.register(exam.id_examen, input)

        setSuccess(result.mensaje ?? EXCEPTIONAL_MESSAGES.registered)
        setSearch({ status: 'idle' })

        return true
      } catch (cause) {
        const message = failureMessage(cause, EXCEPTIONAL_MESSAGES.registerFailed)

        setFormError(message)

        if (cause instanceof ApiError && cause.status === 409) onExamChanged(message)

        return false
      } finally {
        busy.current = false
        setRegistering(false)
      }
    },
    [exam.id_examen, onExamChanged]
  )

  /** Descarta la búsqueda sin agregar nada. */
  const cancel = useCallback(() => {
    request.current?.abort()
    setSearch({ status: 'idle' })
    setFormError('')
  }, [])

  return { search, registering, formError, success, find, register, cancel }
}
