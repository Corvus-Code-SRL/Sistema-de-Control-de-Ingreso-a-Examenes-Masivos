import type { ApiError } from '@/lib/api-client'

/**
 * Primer error de las carreras adicionales (HU-021, multicarrera). Laravel lo devuelve en
 * `carreras_adicionales` o, si es de un elemento, en `carreras_adicionales.N`.
 */
export function careersError(error: ApiError | null): string | undefined {
  if (error === null || !error.isValidation) return undefined

  const key = Object.keys(error.errors).find(
    (field) => field === 'carreras_adicionales' || field.startsWith('carreras_adicionales.')
  )

  return key === undefined ? undefined : error.errors[key]?.[0]
}
