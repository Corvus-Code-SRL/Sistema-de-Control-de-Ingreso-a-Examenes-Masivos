import { renderHook, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { capabilitiesOf, useCurrentUser } from '@/features/auth'
import { assistantsService } from '@/features/assistants'

import { examsService } from '../services/examsService'
import { useInProgressExams } from './useInProgressExams'

vi.mock('@/features/auth', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/auth')>()),
  useCurrentUser: vi.fn(),
}))
vi.mock('../services/examsService', () => ({ examsService: { listExams: vi.fn() } }))
vi.mock('@/features/assistants/services/assistantsService', () => ({
  assistantsService: { listMyExams: vi.fn() },
}))

function currentUser(role: 'Docente' | 'Auxiliar'): ReturnType<typeof useCurrentUser> {
  const area = role === 'Docente' ? 'docente' : 'auxiliar'
  return { user: { nombre: 'Persona Prueba', iniciales: 'PP', area }, area, capabilities: capabilitiesOf(role) }
}

beforeEach(() => vi.clearAllMocks())

describe('useInProgressExams', () => {
  it('el docente recibe solo sus exámenes EN_CURSO con todos sus ambientes', async () => {
    vi.mocked(useCurrentUser).mockReturnValue(currentUser('Docente'))
    vi.mocked(examsService.listExams).mockResolvedValue([
      {
        id_examen: 8, nombre_examen: 'Tercer Parcial', estado: 'EN_CURSO', fecha: '2026-10-29',
        hora_inicio: '10:00', hora_fin: '12:00', duracion: 120, normas: null, id_tipo_examen: 1,
        id_carrera: 1, id_materia: 1, id_usuario_docente: 'docente',
        materia: { id_materia: 1, nombre: 'Álgebra Lineal', codigo: 'MAT101' },
        ambientes: [
          { id_ambiente: 1, nro_aula: '691A', capacidad: 40 },
          { id_ambiente: 2, nro_aula: '691B', capacidad: 40 },
        ],
      },
      {
        id_examen: 9, nombre_examen: 'Examen futuro', estado: 'PROGRAMADO', fecha: '2026-11-01',
        hora_inicio: '10:00', hora_fin: '11:00', duracion: 60, normas: null, id_tipo_examen: 1,
        id_carrera: 1, id_materia: 1, id_usuario_docente: 'docente',
      },
    ])

    const { result } = renderHook(() => useInProgressExams())

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.data).toEqual([{
      id: 8, title: 'Tercer Parcial', subject: 'Álgebra Lineal', date: '2026-10-29',
      startsAt: '10:00', endsAt: '12:00', rooms: '691A, 691B',
    }])
    expect(assistantsService.listMyExams).not.toHaveBeenCalled()
  })

  it('el auxiliar recibe los EN_CURSO donde tiene ambiente', async () => {
    vi.mocked(useCurrentUser).mockReturnValue(currentUser('Auxiliar'))
    vi.mocked(assistantsService.listMyExams).mockResolvedValue([
      {
        id_examen: 8, nombre_examen: 'Tercer Parcial', fecha: '2026-10-29', hora_inicio: '10:00',
        hora_fin: '12:00', estado: 'EN_CURSO', materia: 'Álgebra Lineal',
        ambiente: { id_ambiente: 2, nro_aula: '691B', capacidad: 40 }, ambiente_por_defecto: false,
      },
      {
        id_examen: 11, nombre_examen: 'Sin ambiente', fecha: '2026-10-29', hora_inicio: '10:00',
        hora_fin: '12:00', estado: 'EN_CURSO', materia: 'Química', ambiente: null, ambiente_por_defecto: false,
      },
      {
        id_examen: 12, nombre_examen: 'En ingreso', fecha: '2026-10-29', hora_inicio: '10:00',
        hora_fin: '12:00', estado: 'EN_INGRESO', materia: 'Física',
        ambiente: { id_ambiente: 2, nro_aula: '691B', capacidad: 40 }, ambiente_por_defecto: false,
      },
    ])

    const { result } = renderHook(() => useInProgressExams())

    await waitFor(() => expect(result.current.status).toBe('success'))
    expect(result.current.data).toEqual([{
      id: 8, title: 'Tercer Parcial', subject: 'Álgebra Lineal', date: '2026-10-29',
      startsAt: '10:00', endsAt: '12:00', rooms: '691B',
    }])
    expect(examsService.listExams).not.toHaveBeenCalled()
  })

  it('expone el error para reintentar', async () => {
    vi.mocked(useCurrentUser).mockReturnValue(currentUser('Docente'))
    vi.mocked(examsService.listExams).mockRejectedValue(new Error('Sin conexión'))

    const { result } = renderHook(() => useInProgressExams())

    await waitFor(() => expect(result.current.status).toBe('error'))
    expect(result.current.error?.message).toBe('Sin conexión')
  })
})
