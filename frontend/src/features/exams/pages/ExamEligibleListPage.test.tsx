import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { TOKEN_STORAGE_KEY, meBody } from '@/test/authFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'

// Sin vi.mock de @/features/auth. Sin vi.mock de eligibleListService.
// Se intercepta fetch, como en AppRouter.test.tsx.

function examFixture() {
  return [
    {
      id_examen: 12,
      nombre: 'Primer parcial',
      materia: { id: 3, nombre: 'Cálculo I' },
      carrera: { id: 1, nombre: 'Sistemas' },
      fecha: '2026-10-20',
      hora_inicio: '10:00',
      estado: 'PROGRAMADO' as const,
    },
  ]
}

function listFixture(overrides: Partial<{ editable: boolean; estado: 'PROGRAMADO' | 'EN_INGRESO' }> = {}) {
  return {
    editable: true,
    estado: 'PROGRAMADO',
    estudiantes: [
      {
        id_estudiante: 1,
        codigo_sis: '202210443',
        nombre_completo: 'Álvarez Rojas, Camila',
        id_grupo: 2,
        nombre_grupo: 'Grupo 2',
        estado_habilitacion: 'HABILITADO',
        caso_especial: false,
        motivo: null,
        detalle: null,
      },
      {
        id_estudiante: 2,
        codigo_sis: '202111278',
        nombre_completo: 'Gutiérrez Vela, Andrea',
        id_grupo: 2,
        nombre_grupo: 'Grupo 2',
        estado_habilitacion: 'NO_HABILITADO',
        caso_especial: false,
        motivo: 'ASISTENCIA_INSUFICIENTE',
        detalle: null,
      },
    ],
    ...overrides,
  }
}

function stubBackend(handler: {
  exams: () => unknown
  list?: () => unknown
  put?: (body: unknown) => unknown
}) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')

  return mockApiWith(({ url, method, body }) => {
    if (url.endsWith('/auth/yo')) return { body: meBody('docente') }
    if (url.endsWith('/examenes/habilitados')) return { body: { data: handler.exams() } }
    if (/\/examenes\/\d+\/habilitados$/.test(url)) {
      if (method === 'PUT' && handler.put) return { body: { data: handler.put(body) } }
      if (handler.list) return { body: { data: handler.list() } }
    }
    return { body: { data: [] } }
  })
}

describe('ExamEligibleListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    window.localStorage.clear()
  })

  it('muestra un estado vacío con acción para registrar examen cuando no hay exámenes', async () => {
    stubBackend({ exams: () => [] })

    renderApp('/examenes/habilitados')

    expect(await screen.findByText('Aún no tiene exámenes disponibles')).toBeInTheDocument()
    expect(
      screen.getByText('Registre un examen para poder habilitar a los estudiantes de sus grupos.')
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Ir a registrar examen/i })).toHaveAttribute(
      'href',
      '/examenes/nuevo'
    )
  })

  it('carga exámenes y la lista automáticamente', async () => {
    const api = stubBackend({ exams: examFixture, list: listFixture })

    renderApp('/examenes/habilitados')

    expect(await screen.findByText('Álvarez Rojas, Camila')).toBeInTheDocument()
    expect(api.calls.some((c) => c.url.includes('/examenes/12/habilitados'))).toBe(true)
  })

  it('muestra banner de solo lectura cuando editable=false', async () => {
    stubBackend({
      exams: examFixture,
      list: () => listFixture({ editable: false, estado: 'EN_INGRESO' }),
    })

    renderApp('/examenes/habilitados')

    expect(await screen.findByText(/El control de ingreso ya empezó/i)).toBeInTheDocument()
  })

  it('aplica "No habilitar" con motivo y guarda', async () => {
    const api = stubBackend({
      exams: examFixture,
      list: listFixture,
      put: () => ({
        ...listFixture(),
        estudiantes: [
          { ...listFixture().estudiantes[0], estado_habilitacion: 'NO_HABILITADO', motivo: 'ASISTENCIA_INSUFICIENTE' },
          listFixture().estudiantes[1],
        ],
      }),
    })

    const user = userEvent.setup()
    renderApp('/examenes/habilitados')

    await screen.findByText('Álvarez Rojas, Camila')

    await user.click(screen.getByLabelText(/Marcar a Álvarez Rojas, Camila/i))
    await user.click(screen.getByRole('button', { name: /No habilitar…/i }))
    await user.click(screen.getByRole('combobox', { name: /Motivo/i }))
    await user.click(screen.getByRole('option', { name: /Asistencia insuficiente/i }))
    await user.click(screen.getByRole('button', { name: /^No habilitar$/i }))

    expect(await screen.findByText(/1 cambio sin guardar/i)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Guardar cambios/i }))

    await waitFor(() => {
      const put = api.calls.find((c) => c.method === 'PUT' && c.url.includes('/habilitados'))
      expect(put).toBeDefined()
      expect(put!.body).toEqual({
        cambios: [
          {
            id_estudiante: 1,
            estado_habilitacion: 'NO_HABILITADO',
            motivo: 'ASISTENCIA_INSUFICIENTE',
          },
        ],
      })
    })

    expect(await screen.findByText(/Lista de habilitados guardada/i)).toBeInTheDocument()
  })

  it('descarta los cambios pendientes', async () => {
    stubBackend({ exams: examFixture, list: listFixture })

    const user = userEvent.setup()
    renderApp('/examenes/habilitados')

    await screen.findByText('Álvarez Rojas, Camila')

    await user.click(screen.getByLabelText(/Marcar a Álvarez Rojas, Camila/i))
    await user.click(screen.getByRole('button', { name: /No habilitar…/i }))
    await user.click(screen.getByRole('combobox', { name: /Motivo/i }))
    await user.click(screen.getByRole('option', { name: /Asistencia insuficiente/i }))
    await user.click(screen.getByRole('button', { name: /^No habilitar$/i }))

    await user.click(screen.getByRole('button', { name: /Descartar/i }))

    expect(screen.queryByText(/cambio sin guardar/i)).not.toBeInTheDocument()
  })
})

it('muestra el error cuando falla la carga de exámenes', async () => {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')
  mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) return { body: meBody('docente') }
    if (url.endsWith('/examenes/habilitados')) {
      return { status: 500, body: { message: 'Error del servidor' } }
    }
    return { body: { data: [] } }
  })

  renderApp('/examenes/habilitados')

  expect(await screen.findByText('No se pudo cargar la información')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /Reintentar/i })).toBeInTheDocument()
})

it('restaura el examen seleccionado desde ?examen=12', async () => {
  const api = stubBackend({ exams: examFixture, list: listFixture })

  renderApp('/examenes/habilitados?examen=12')

  expect(await screen.findByText('Álvarez Rojas, Camila')).toBeInTheDocument()
  expect(api.calls.some((c) => c.url.includes('/examenes/12/habilitados'))).toBe(true)
})

it('al cambiar de examen escribe ?examen=<id> en la URL', async () => {
  const twoExams = [
    ...examFixture(),
    {
      id_examen: 13,
      nombre: 'Segundo parcial',
      materia: { id: 3, nombre: 'Cálculo I' },
      carrera: { id: 1, nombre: 'Sistemas' },
      fecha: '2026-11-20',
      hora_inicio: '10:00',
      estado: 'PROGRAMADO' as const,
    },
  ]
  const api = stubBackend({
    exams: () => twoExams,
    list: listFixture,
  })

  const user = userEvent.setup()
  renderApp('/examenes/habilitados')

  await screen.findByText('Álvarez Rojas, Camila')

  await user.click(screen.getByRole('combobox', { name: /Examen/i }))
  await user.click(screen.getByRole('option', { name: /Segundo parcial/i }))

  await waitFor(() => {
    expect(api.calls.some((c) => c.url.includes('/examenes/13/habilitados'))).toBe(true)
  })
})