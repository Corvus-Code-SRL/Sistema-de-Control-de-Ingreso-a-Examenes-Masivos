import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '@/lib/api-client'

export type AsyncStatus = 'loading' | 'success' | 'error'

export interface AsyncResource<TData> {
  status: AsyncStatus
  data: TData | null
  error: ApiError | null
  /** Vuelve a lanzar la petición; la vista lo usa en el botón «Reintentar». */
  reload: () => void
}

/**
 * Resuelve una petición a la API exponiendo los tres estados por separado.
 *
 * Carga, vacío y error son estados distintos en todas las vistas de la historia,
 * así que el hook nunca colapsa «sin datos» con «falló»: `data` vacío es un
 * resultado válido y solo `error` indica fallo.
 */
export function useAsyncResource<TData>(
  fetcher: (signal: AbortSignal) => Promise<TData>,
  deps: readonly unknown[]
): AsyncResource<TData> {
  const [status, setStatus] = useState<AsyncStatus>('loading')
  const [data, setData] = useState<TData | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  const [attempt, setAttempt] = useState(0)

  const reload = useCallback(() => setAttempt((previous) => previous + 1), [])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setStatus('loading')
    setError(null)

    fetcher(controller.signal)
      .then((result) => {
        if (!active) return

        setData(result)
        setStatus('success')
      })
      .catch((cause: unknown) => {
        // El aborto lo provoca la limpieza del efecto, no es un fallo que mostrar.
        if (!active || (cause instanceof DOMException && cause.name === 'AbortError')) {
          return
        }

        setError(cause instanceof ApiError ? cause : new ApiError(0, (cause as Error).message))
        setStatus('error')
      })

    return () => {
      active = false
      controller.abort()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, attempt])

  return { status, data, error, reload }
}
