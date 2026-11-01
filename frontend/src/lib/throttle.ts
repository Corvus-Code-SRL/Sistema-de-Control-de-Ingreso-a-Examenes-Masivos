import type { ApiError } from '@/lib/api-client'

const MIN_WAIT_SECONDS = 2
const MAX_WAIT_SECONDS = 30
const DEFAULT_WAIT_SECONDS = 5

/**
 * Segundos que una consulta periódica espera tras un 429: lo que pide `Retry-After`, entre 2 y 30 s,
 * para no dejar la pantalla sin datos durante minutos ni insistir antes de tiempo. Sin la cabecera, 5 s.
 */
export function throttleWaitSeconds(error: ApiError): number {
  const requested = error.retryAfter ?? DEFAULT_WAIT_SECONDS

  return Math.min(Math.max(Math.ceil(requested), MIN_WAIT_SECONDS), MAX_WAIT_SECONDS)
}

export function throttleNotice(seconds: number): string {
  return `Muchas consultas seguidas, reintentando en ${seconds} s.`
}
