import { useEffect, useRef, useState } from 'react'
import type { ExamInProgress, InProgressStudent } from '@/features/exams'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api-client'
import { examSituationService } from '../services/examSituationService'
import { formatSituationTime, type ExamSituationContext } from '../types/exam-situation.types'
import { ExamSituationDialog } from './ExamSituationDialog'
import { ExamSituationsPanel } from './ExamSituationsPanel'

interface ActionProps {
  exam: ExamInProgress
  student: InProgressStudent
}

/** Acción por fila: usa datos recientes del servidor al abrir el formulario. */
export function InProgressSituationAction({ exam, student }: ActionProps) {
  const [context, setContext] = useState<ExamSituationContext | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const pending = useRef<AbortController | null>(null)
  const canRegister = exam.situaciones_habilitadas !== false && exam.estado === 'EN_CURSO'
    && !student.expulsado_en
    && ['INGRESO', 'CON_RETRASO'].includes(student.estado_ingreso)
    && student.id_ambiente !== null
    && (exam.rol_controlador === 'DOCENTE' || student.id_ambiente === exam.id_ambiente_asignado)

  useEffect(() => () => pending.current?.abort(), [exam.id_examen, student.id_estudiante])

  async function openDialog() {
    if (!canRegister || pending.current) return
    const controller = new AbortController()
    pending.current = controller
    setLoading(true)
    setError('')
    setMessage('')
    try {
      const { data } = await examSituationService.context(
        exam.id_examen, { id_estudiante: student.id_estudiante }, controller.signal,
      )
      if (controller.signal.aborted) return
      const current = data.estudiantes.find((item) => item.id_estudiante === student.id_estudiante)
      if (data.examen.estado !== 'EN_CURSO' || !current) {
        setError('El examen o el estudiante cambió. Espere la actualización de la vista.')
        return
      }
      setContext({
        examId: data.examen.id_examen,
        examName: data.examen.nombre_examen,
        student: current,
        room: current.ambiente,
        rooms: data.ambientes,
        serverTime: data.hora_servidor,
      })
    } catch (cause: unknown) {
      if (!controller.signal.aborted) {
        setError(cause instanceof ApiError && [403, 404, 409].includes(cause.status)
          ? cause.message : 'No se pudo cargar el contexto. Intente nuevamente.')
      }
    } finally {
      if (!controller.signal.aborted) setLoading(false)
      if (pending.current === controller) pending.current = null
    }
  }

  const change = student.situaciones?.ultimo_cambio_ambiente
  const roomChanged = context !== null && context.room.id_ambiente !== student.id_ambiente

  return (
    <div className="space-y-2">
      {change && <p className="text-xs text-muted-foreground">
        Cambio desde {change.ambiente_origen.nro_aula} · {formatSituationTime(change.registrado_en)}
      </p>}
      {student.situaciones?.identidad_observada && <p className="text-xs font-medium text-warn-fg">
        Identidad observada
      </p>}
      {canRegister && <Button type="button" variant="outline" disabled={loading} onClick={() => void openDialog()}>
        {loading ? 'Cargando…' : 'Registrar situación'}
      </Button>}
      {error && <p role="alert" className="text-sm text-danger-fg">{error}</p>}
      {message && <p role="status" className="text-sm text-muted-foreground">{message}</p>}
      {context && <ExamSituationDialog
        key={`${context.examId}-${context.student.id_estudiante}-${context.room.id_ambiente}`}
        context={context}
        readOnly={!canRegister || roomChanged}
        onClose={() => setContext(null)}
        onRegistered={(result) => {
          setContext(null)
          setMessage(result.mensaje)
          // El polling de HU-038-A refresca ambiente, marcas y revisión del historial, también entre dispositivos.
        }}
      />}
    </div>
  )
}

/** Comparte el polling de En curso; no crea un temporizador adicional. */
export function InProgressSituationsPanel({ exam }: { exam: ExamInProgress }) {
  if (exam.situaciones_habilitadas === false) return null
  const roomId = exam.rol_controlador === 'AUXILIAR' ? exam.id_ambiente_asignado ?? undefined : undefined
  // Un auxiliar sin ambiente no debe provocar una consulta sin filtro desde este componente.
  if (exam.rol_controlador === 'AUXILIAR' && roomId === undefined) return null
  return <ExamSituationsPanel
    key={`${exam.id_examen}-${roomId ?? 'todos'}`}
    examId={exam.id_examen}
    roomId={roomId}
    refreshKey={exam.total_situaciones ?? 0}
  />
}
