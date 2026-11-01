import { useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { normalizeSisCode, sisCodeError } from '../lib/sisCode'
import { verifySisCode } from '../services/usersService'
import type { SisPerson } from '../types/users.types'

export const CODE_CHECK_DEBOUNCE_MS = 400

export type CodeCheck =
  /** Aún no hay un código que revisar (vacío, con el formato mal o el registro es de un invitado). */
  | { status: 'idle'; formatError: string | null }
  | { status: 'checking' }
  /** Sin cuenta con ese código. `person` trae los nombres del SIS, si los reconoció. */
  | { status: 'available'; person: SisPerson | null }
  /** El SIS no respondió; no impide el registro. */
  | { status: 'sis-unavailable' }
  | { status: 'exists'; message: string; accountId: string | null }
  | { status: 'error'; message: string }

type Settled = Exclude<CodeCheck, { status: 'idle' } | { status: 'checking' }>

const SIS_UNAVAILABLE: Settled = { status: 'sis-unavailable' }

/**
 * Revisa el código SIS escrito en el paso 1 contra las cuentas de SCIEM (¿ya existe?) y valida su
 * formato. Consulta tras una pausa en la escritura y recuerda el último resultado, de modo que volver
 * al paso 1 no repite la consulta si el código no cambió.
 *
 * Si hay una persona en el SIS, `onPerson` recibe sus nombres para precargar el paso 2.
 */
export function useSisCodeCheck(
  rawCode: string,
  enabled: boolean,
  onPerson: (person: SisPerson) => void
): CodeCheck {
  const code = normalizeSisCode(rawCode)
  const formatError = enabled && code !== '' ? sisCodeError(code, null) : null
  const shouldCheck = enabled && code !== '' && formatError === null

  const [result, setResult] = useState<{ code: string; outcome: Settled } | null>(null)
  const onPersonRef = useRef(onPerson)

  useEffect(() => {
    onPersonRef.current = onPerson
  })

  const alreadyChecked = result?.code === code

  useEffect(() => {
    if (!shouldCheck || alreadyChecked) return

    const controller = new AbortController()

    const timer = window.setTimeout(async () => {
      let outcome: Settled

      try {
        const person = await verifySisCode(code, controller.signal)

        outcome = { status: 'available', person }
        if (person) onPersonRef.current(person)
      } catch (cause) {
        if (controller.signal.aborted) return

        outcome = outcomeOf(cause)
      }

      setResult({ code, outcome })
    }, CODE_CHECK_DEBOUNCE_MS)

    return () => {
      window.clearTimeout(timer)
      controller.abort()
    }
  }, [code, shouldCheck, alreadyChecked])

  if (!shouldCheck) return { status: 'idle', formatError }
  if (!alreadyChecked || result === null) return { status: 'checking' }

  return result.outcome
}

function outcomeOf(cause: unknown): Settled {
  if (cause instanceof ApiError && cause.isValidation) {
    return {
      status: 'exists',
      message: cause.errors.cod_sis?.[0] ?? cause.message,
      accountId: cause.cuentaExistenteId ?? null,
    }
  }

  // Sin SIS (503) o sin conexión, el servidor tampoco pudo decir que el código esté libre ni ocupado:
  // el registro igual lo comprueba, así que no se bloquea el paso.
  if (cause instanceof ApiError && (cause.isUnavailable || cause.status === 503)) return SIS_UNAVAILABLE

  return {
    status: 'error',
    message: cause instanceof ApiError ? cause.message : 'No se pudo verificar el código. Intente nuevamente.',
  }
}
