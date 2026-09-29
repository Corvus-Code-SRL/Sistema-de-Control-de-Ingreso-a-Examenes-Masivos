import { useCallback, useState } from 'react'
import { useAsyncResource } from '@/hooks/useAsyncResource'
import { ApiError } from '@/lib/api-client'
import { assistantsService } from '../services/assistantsService'
import type { ExamAssistant } from '../types/assistant.types'

export interface AssignmentNotice {
  title: string
  description: string
}

const ASSIGN_ERROR = 'No se pudo asignar el ambiente.'

/**
 * Auxiliares de un examen y la asignación de su ambiente (HU-09).
 *
 * Cada cambio se guarda al momento. Tanto si se guarda como si el backend lo
 * rechaza, el listado se vuelve a pedir: la pantalla muestra siempre lo que
 * quedó en la base, nunca un estado supuesto.
 */
export function useExamAssistants(examId: number) {
  const { status, data, error, reload } = useAsyncResource(
    (signal) => assistantsService.listExamAssistants(examId, signal),
    [examId]
  )

  const [savingUserId, setSavingUserId] = useState<string | null>(null)
  const [notice, setNotice] = useState<AssignmentNotice | null>(null)
  const [assignError, setAssignError] = useState<string | null>(null)
  const [lockedMessage, setLockedMessage] = useState<string | null>(null)

  const assign = useCallback(
    async (assistant: ExamAssistant, classroomId: number) => {
      setSavingUserId(assistant.id_usuario)
      setAssignError(null)

      try {
        const updated = await assistantsService.assignClassroom(
          examId,
          assistant.id_usuario,
          classroomId
        )

        setNotice({
          title: 'Ambiente asignado',
          description: `${updated.nombre_completo} controlará en ${updated.ambiente?.nro_aula ?? 'el ambiente elegido'}.`,
        })
      } catch (cause) {
        const apiError = cause instanceof ApiError ? cause : null

        // 409: el control de ingreso se abrió mientras la pantalla estaba abierta.
        if (apiError?.status === 409) {
          setLockedMessage(apiError.message)
        } else {
          setAssignError(apiError?.message ?? ASSIGN_ERROR)
        }
      } finally {
        setSavingUserId(null)
        reload()
      }
    },
    [examId, reload]
  )

  const dismissNotice = useCallback(() => setNotice(null), [])
  const dismissLocked = useCallback(() => setLockedMessage(null), [])

  return {
    status,
    data,
    error,
    reload,
    assign,
    savingUserId,
    notice,
    dismissNotice,
    assignError,
    lockedMessage,
    dismissLocked,
  }
}