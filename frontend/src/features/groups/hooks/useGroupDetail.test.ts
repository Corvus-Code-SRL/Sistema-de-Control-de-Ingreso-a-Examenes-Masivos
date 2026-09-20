import { renderHook, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { useGroupDetail } from './useGroupDetail'
import {
  groupDetailResponse,
  makeForeignGroup,
  makeGroup,
  makeGroupWithoutRoster,
  makeSubject,
} from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'

describe('useGroupDetail', () => {
  const materia = makeSubject()

  it('abre el detalle de un grupo propio', async () => {
    mockApiOnce({ body: groupDetailResponse(makeGroup(), materia) })

    const { result } = renderHook(() => useGroupDetail(100))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isForbidden).toBe(false)
    expect(result.current.detail?.group.es_mio).toBe(true)
    expect(result.current.detail?.subject.nombre).toBe('Bases de Datos I')
    expect(result.current.error).toBeNull()
  })

  it('niega el acceso a un grupo de otro docente aunque el servidor responda 200', async () => {
    // El backend marca la propiedad en el recurso en lugar de rechazar la petición.
    mockApiOnce({ body: groupDetailResponse(makeForeignGroup(), materia) })

    const { result } = renderHook(() => useGroupDetail(200))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isForbidden).toBe(true)
    expect(result.current.error).toBeNull()
  })

  it('niega el acceso igual cuando el servidor responde 403', async () => {
    mockApiOnce({ status: 403, body: { message: 'No autorizado.' } })

    const { result } = renderHook(() => useGroupDetail(200))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isForbidden).toBe(true)
    // Un 403 no se ofrece para reintentar: la vista explica el motivo.
    expect(result.current.error).toBeNull()
  })

  it('distingue un grupo inexistente de uno sin permiso', async () => {
    mockApiOnce({ status: 404, body: { message: 'No existe el grupo indicado.' } })

    const { result } = renderHook(() => useGroupDetail(999999))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isNotFound).toBe(true)
    expect(result.current.isForbidden).toBe(false)
    expect(result.current.error?.message).toBe('No existe el grupo indicado.')
  })

  it('abre un grupo propio sin nómina como caso normal', async () => {
    mockApiOnce({ body: groupDetailResponse(makeGroupWithoutRoster(), materia) })

    const { result } = renderHook(() => useGroupDetail(300))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.isForbidden).toBe(false)
    expect(result.current.detail?.group.cantidad_estudiantes).toBe(0)
    expect(result.current.error).toBeNull()
  })

  it('indica cuando el grupo no es del periodo activo', async () => {
    mockApiOnce({ body: groupDetailResponse(makeGroup(), materia, false) })

    const { result } = renderHook(() => useGroupDetail(100))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.detail?.meta.es_periodo_activo).toBe(false)
  })

  it('expone el error del servidor para poder reintentar', async () => {
    mockApiOnce({ status: 500, body: { message: 'Error interno del servidor.' } })

    const { result } = renderHook(() => useGroupDetail(100))

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.error?.message).toBe('Error interno del servidor.')
    expect(result.current.isForbidden).toBe(false)
    expect(result.current.isNotFound).toBe(false)
  })
})
