import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useAdminSubjects } from './useAdminSubjects'
import { mockApiOnce } from '@/test/http'

describe('useAdminSubjects', () => {
  it('expone las materias institucionales para administracion', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_materia: 10,
            nombre: 'Bases de Datos I',
            codigo: '2008057',
          },
          {
            id_materia: 20,
            nombre: 'Calculo II',
            codigo: '2008058',
          },
        ],
      },
    })

    const { result } = renderHook(() => useAdminSubjects())

    await waitFor(() => expect(result.current.status).toBe('success'))

    expect(result.current.subjects).toEqual([
      {
        id_materia: 10,
        nombre: 'Bases de Datos I',
        codigo: '2008057',
      },
      {
        id_materia: 20,
        nombre: 'Calculo II',
        codigo: '2008058',
      },
    ])
  })

  it('expone el error cuando el catalogo no puede cargarse', async () => {
    mockApiOnce({
      status: 500,
      body: { message: 'Error interno del servidor.' },
    })

    const { result } = renderHook(() => useAdminSubjects())

    await waitFor(() => expect(result.current.status).toBe('error'))

    expect(result.current.subjects).toEqual([])
    expect(result.current.error?.status).toBe(500)
  })
})