import { describe, expect, it } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { DEFAULT_RETRY_AFTER_SECONDS, safeDestination, toLoginFailure } from './useLoginForm'

/**
 * La pantalla decide con el estado y el código `motivo`, nunca con el texto del mensaje:
 * reescribir un mensaje no puede cambiar a qué resultado corresponde.
 */
describe('toLoginFailure', () => {
  it('401 → credenciales', () => {
    const failure = toLoginFailure(
      new ApiError(401, 'cualquier texto', {}, 'credenciales_invalidas')
    )

    expect(failure.kind).toBe('credenciales')
  })

  it('403 con cuenta_inactiva → cuenta-inactiva, aunque el mensaje diga otra cosa', () => {
    const failure = toLoginFailure(new ApiError(403, 'Mensaje reescrito', {}, 'cuenta_inactiva'))

    expect(failure.kind).toBe('cuenta-inactiva')
  })

  it('403 con sin_rol_vigente → sin-rol, aunque el mensaje diga otra cosa', () => {
    const failure = toLoginFailure(new ApiError(403, 'Mensaje reescrito', {}, 'sin_rol_vigente'))

    expect(failure.kind).toBe('sin-rol')
  })

  it('un 403 sin código conocido no se adivina por el texto: queda desconocido', () => {
    const failure = toLoginFailure(new ApiError(403, 'Su cuenta está deshabilitada.'))

    expect(failure.kind).toBe('desconocido')
  })

  it('429 → limitado con los segundos de Retry-After', () => {
    const failure = toLoginFailure(new ApiError(429, 'Too Many Attempts.', {}, undefined, { retryAfter: 37 }))

    expect(failure.kind).toBe('limitado')
    expect(failure.retryAfter).toBe(37)
  })

  it('429 sin Retry-After asume 60 segundos', () => {
    const failure = toLoginFailure(new ApiError(429, 'Too Many Attempts.'))

    expect(failure.retryAfter).toBe(DEFAULT_RETRY_AFTER_SECONDS)
    expect(DEFAULT_RETRY_AFTER_SECONDS).toBe(60)
  })

  it.each([
    ['sin conexión', new ApiError(0, 'sin red')],
    ['tiempo agotado', new ApiError(0, 'lento', {}, undefined, { kind: 'timeout' })],
    ['500', new ApiError(500, 'Server Error')],
    ['503', new ApiError(503, 'Service Unavailable')],
  ])('%s → indisponible, nunca credenciales', (_label, error) => {
    expect(toLoginFailure(error).kind).toBe('indisponible')
  })

  it('un fallo que no es de la API queda desconocido', () => {
    expect(toLoginFailure(new Error('boom')).kind).toBe('desconocido')
  })
})

describe('safeDestination', () => {
  it('acepta rutas internas y descarta lo demás', () => {
    expect(safeDestination('/examenes/programados?x=1')).toBe('/examenes/programados?x=1')
    expect(safeDestination('//evil.example')).toBe('/')
    expect(safeDestination('https://evil.example')).toBe('/')
    expect(safeDestination('/login')).toBe('/')
    expect(safeDestination(undefined)).toBe('/')
  })
})
