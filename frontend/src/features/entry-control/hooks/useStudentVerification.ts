import { useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { entryControlService } from '../services/entryControlService'
import type { EntryActionInput, RejectionReason, StudentMatch, Verification, VerificationInput } from '../types/entry-control.types'

export function useStudentVerification(examId: number, roomId: number | null, onChange: () => void) {
  const [sis, setSis] = useState('')
  const [ci, setCi] = useState('')
  const [result, setResult] = useState<Verification | null>(null)
  const [busy, setBusy] = useState<'verify' | 'confirm' | 'reject' | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  useEffect(() => { setResult(null); setError(null) }, [roomId])

  function reset() {
    setSis('')
    setCi('')
    setResult(null)
    setError(null)
  }

  function changeSis(value: string) {
    setSis(value.trim())
    setResult(null)
    setError(null)
  }

  function changeCi(value: string) {
    setCi(value.trim())
    setResult(null)
    setError(null)
  }

  function choose(student: StudentMatch) {
    setSis(student.cod_sis)
    setResult(null)
    setError(null)
  }

  function input(): VerificationInput | null {
    if (!roomId || !sis) {
      setError('Seleccione un ambiente e ingrese el código SIS.')
      return null
    }
    return { cod_sis: sis, id_ambiente: roomId, ...(ci ? { ci } : {}) }
  }

  async function verify() {
    const payload = input()
    if (!payload) return
    setBusy('verify')
    setError(null)
    setNotice(null)
    try {
      setResult(await entryControlService.verify(examId, payload))
    } catch (cause) {
      setResult(null)
      setError(cause instanceof Error ? cause.message : 'No se pudo verificar al estudiante.')
    } finally {
      setBusy(null)
    }
  }

  function actionInput(studentId: number, selectedRoomId: number): EntryActionInput {
    return { id_estudiante: studentId, id_ambiente: selectedRoomId, ...(ci ? { ci } : {}) }
  }

  async function confirm() {
    if (!result?.autorizado || !result.estudiante || !roomId) return
    setBusy('confirm')
    setError(null)
    try {
      await entryControlService.confirm(examId, actionInput(result.estudiante.id_estudiante, roomId))
      setNotice(`Ingreso registrado: ${result.estudiante.nombre_completo}.`)
      reset()
      onChange()
    } catch (cause) {
      await handleConfirmError(cause)
    } finally {
      setBusy(null)
    }
  }

  async function handleConfirmError(cause: unknown) {
    if (cause instanceof ApiError && cause.status === 409) {
      const payload = input()
      if (payload) {
        try { setResult(await entryControlService.verify(examId, payload)) } catch { /* Conserva el error original. */ }
      }
    }
    setError(cause instanceof Error ? cause.message : 'No se pudo registrar el ingreso.')
  }

  async function reject(motivo: RejectionReason, observacion: string) {
    if (!result?.autorizado || !result.estudiante || !roomId) return false
    setBusy('reject')
    setError(null)
    try {
      await entryControlService.reject(examId, {
        ...actionInput(result.estudiante.id_estudiante, roomId),
        motivo, observacion: observacion.trim() || undefined,
      })
      setNotice('Intento rechazado y registrado.')
      reset()
      return true
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'No se pudo registrar el rechazo.')
      return false
    } finally {
      setBusy(null)
    }
  }

  return { sis, ci, result, busy, error, notice, setNotice, changeSis, changeCi, choose, verify, confirm, reject, reset }
}
