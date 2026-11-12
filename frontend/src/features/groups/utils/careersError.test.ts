import { describe, expect, it } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { careersError } from './careersError'

describe('careersError', () => {
  it('devuelve el error de la lista o de uno de sus elementos', () => {
    expect(
      careersError(new ApiError(422, 'Datos inválidos', { carreras_adicionales: ['No se puede quitar'] }))
    ).toBe('No se puede quitar')
    expect(
      careersError(new ApiError(422, 'Datos inválidos', { 'carreras_adicionales.1': ['Se repite'] }))
    ).toBe('Se repite')
  })

  it('sin error de carreras devuelve undefined', () => {
    expect(careersError(null)).toBeUndefined()
    expect(careersError(new ApiError(422, 'Datos inválidos', { num_grupo: ['Falta'] }))).toBeUndefined()
    expect(careersError(new ApiError(500, 'Error'))).toBeUndefined()
  })
})
