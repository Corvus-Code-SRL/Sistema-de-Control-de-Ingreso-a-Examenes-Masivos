import { useCurrentUser } from '@/features/auth'
import { assistantsService } from '@/features/assistants'
import { useAsyncResource, type AsyncResource } from '@/hooks/useAsyncResource'

import { examsService } from '../services/examsService'
import { EXAM_STATUS } from '../types/exams.types'
import type { InProgressExamRow } from '../types/examInProgress.types'

/**
 * Exámenes EN_CURSO que controla el usuario: los suyos si es docente y en los que está
 * habilitado con ambiente si es auxiliar. Cada API ya limita los resultados a la cuenta;
 * aquí solo se elige el estado. Alimenta la lista «Registrar estudiante con retraso» (HU-037).
 */
export function useInProgressExams(): AsyncResource<InProgressExamRow[]> {
  const { area } = useCurrentUser()
  const isAssistant = area === 'auxiliar'

  return useAsyncResource<InProgressExamRow[]>(
    async (signal) => {
      if (isAssistant) {
        const exams = await assistantsService.listMyExams(signal)

        return exams
          .filter((exam) => exam.estado === EXAM_STATUS.EN_CURSO && exam.ambiente !== null)
          .map((exam) => ({
            id: exam.id_examen,
            title: exam.nombre_examen,
            subject: exam.materia,
            date: exam.fecha,
            startsAt: exam.hora_inicio,
            endsAt: exam.hora_fin,
            rooms: exam.ambiente?.nro_aula ?? 'Sin ambiente asignado',
          }))
      }

      const exams = await examsService.listExams(signal)

      return exams
        .filter((exam) => exam.estado === EXAM_STATUS.EN_CURSO)
        .map((exam) => ({
          id: exam.id_examen,
          title: exam.nombre_examen,
          subject: exam.materia?.nombre ?? null,
          date: exam.fecha,
          startsAt: exam.hora_inicio,
          endsAt: exam.hora_fin,
          rooms: exam.ambientes?.map((room) => room.nro_aula).join(', ') || 'Sin ambiente',
        }))
    },
    [isAssistant]
  )
}
