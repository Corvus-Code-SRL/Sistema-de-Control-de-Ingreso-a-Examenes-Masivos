import { describe, expect, it } from 'vitest'
import { ApiError } from './api-client'
import { throttleNotice, throttleWaitSeconds } from './throttle'

function tooManyRequests(retryAfter?: number): ApiError {
  return new ApiError(429, 'Too Many Attempts.', {}, undefined, { retryAfter })
}

describe('throttleWaitSeconds', () => {
  it('respeta Retry-After dentro del rango', () => {
    expect(throttleWaitSeconds(tooManyRequests(12))).toBe(12)
  })

  it('no baja de 2 s ni sube de 30 s', () => {
    expect(throttleWaitSeconds(tooManyRequests(0))).toBe(2)
    expect(throttleWaitSeconds(tooManyRequests(1))).toBe(2)
    expect(throttleWaitSeconds(tooManyRequests(60))).toBe(30)
  })

  it('sin Retry-After espera 5 s', () => {
    expect(throttleWaitSeconds(tooManyRequests())).toBe(5)
  })
})

describe('throttleNotice', () => {
  it('avisa con los segundos de espera', () => {
    expect(throttleNotice(7)).toBe('Muchas consultas seguidas, reintentando en 7 s.')
  })
})
