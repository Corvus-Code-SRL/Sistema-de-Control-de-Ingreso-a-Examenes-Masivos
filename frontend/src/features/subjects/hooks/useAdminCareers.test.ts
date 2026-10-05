import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useAdminCareers } from './useAdminCareers'
import { mockApiOnce } from '@/test/http'

describe('useAdminCareers', () => {
  it('expone las carreras activas para administracion', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_carrera: 1,
            nombre: 'Ingenieria de Sistemas',
            codigo: 'SIS',
            id_facultad: 1,
          },
          {
            id_carrera: 2,
            nombre: 'Ingenieria Informatica',
            codigo: 'INF',
            id_facultad: 1,
          },
        ],
      },
    })

    const { result } = renderHook(() => useAdminCareers())

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.careers).toEqual([
      {
        id_carrera: 1,
        nombre: 'Ingenieria de Sistemas',
        codigo: 'SIS',
        id_facultad: 1,
      },
      {
        id_carrera: 2,
        nombre: 'Ingenieria Informatica',
        codigo: 'INF',
        id_facultad: 1,
      },
    ])
  })

  it('expone el error cuando las carreras no pueden cargarse', async () => {
    mockApiOnce({
      status: 500,
      body: { message: 'Error interno del servidor.' },
    })

    const { result } = renderHook(() => useAdminCareers())

    await waitFor(() => expect(result.current.status).toBe('error'))

    expect(result.current.careers).toEqual([])
    expect(result.current.error?.status).toBe(500)
  })
})