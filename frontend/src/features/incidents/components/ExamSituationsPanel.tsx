import { useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { LoadingState } from '@/components/common/LoadingState'
import { ErrorState } from '@/components/common/ErrorState'
import { Button } from '@/components/ui/button'
import { examSituationService } from '../services/examSituationService'
import { formatSituationTime, SITUATION_LABELS, type SituationPage } from '../types/exam-situation.types'

interface Props {
  examId: number
  roomId?: number
  /** Cambia tras un registro o una actualización del estado de En curso. */
  refreshKey: number
}

export function ExamSituationsPanel({ examId, roomId, refreshKey }: Props) {
  const [page, setPage] = useState(1)
  const [retry, setRetry] = useState(0)
  const [result, setResult] = useState<SituationPage | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [loading, setLoading] = useState(true)
  useEffect(() => {
    const controller = new AbortController()
    setLoading(true)
    setError(null)
    examSituationService.list(examId, page, roomId, controller.signal)
      .then((response) => { if (!controller.signal.aborted) setResult(response) })
      .catch((cause: unknown) => {
        if (!controller.signal.aborted) setError(cause instanceof ApiError
          ? cause : new ApiError(0, 'No se pudieron consultar las situaciones.'))
      })
      .finally(() => { if (!controller.signal.aborted) setLoading(false) })
    return () => controller.abort()
  }, [examId, roomId, page, refreshKey, retry])

  if (error) return <ErrorState error={error} onRetry={() => setRetry((value) => value + 1)} />
  if (!result) return <LoadingState rows={3} label="Cargando situaciones" />

  return (
    <section aria-label="Situaciones del examen" aria-busy={loading} className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">Situaciones · {result.meta.total}</h2>
        <Button type="button" variant="outline" disabled={loading} onClick={() => setRetry((value) => value + 1)}>
          {loading ? 'Actualizando…' : 'Actualizar'}
        </Button>
      </div>
      {!result.data.length && <p className="p-4 text-sm text-muted-foreground">No se registraron situaciones en este examen.</p>}
      <ol className="space-y-3">
        {result.data.map((item) => <li key={item.id_situacion} className="space-y-2 rounded-xl border bg-card p-4 text-card-foreground">
          <div className="flex flex-wrap justify-between gap-2">
            <h3 className="font-medium">{SITUATION_LABELS[item.tipo]}</h3>
            <time dateTime={item.registrado_en} className="text-sm text-muted-foreground">{formatSituationTime(item.registrado_en)}</time>
          </div>
          <p className="break-words text-sm">{item.estudiante.nombre_completo} · {item.estudiante.cod_sis}</p>
          <p className="text-sm text-muted-foreground">{item.ambiente_destino
            ? `Cambio desde ${item.ambiente_origen.nro_aula} a ${item.ambiente_destino.nro_aula}`
            : item.ambiente_origen.nro_aula}</p>
          <p className="whitespace-pre-wrap break-words text-sm">{item.motivo}</p>
          <p className="text-xs text-muted-foreground">Registrado por {item.registrante.nombre_completo}</p>

        </li>)}
      </ol>
      <div className="flex flex-wrap items-center justify-end gap-3">
        <Button type="button" variant="outline" disabled={loading || page <= 1} onClick={() => setPage((value) => value - 1)}>Anterior</Button>
        <span className="text-sm">Página {result.meta.current_page} de {result.meta.last_page}</span>
        <Button type="button" variant="outline" disabled={loading || page >= result.meta.last_page} onClick={() => setPage((value) => value + 1)}>Siguiente</Button>
      </div>
    </section>
  )
}