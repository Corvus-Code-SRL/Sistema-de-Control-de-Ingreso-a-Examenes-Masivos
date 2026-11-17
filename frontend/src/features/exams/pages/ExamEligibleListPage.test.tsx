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

function listFixture(overrides: Partial<{ editable: boolean; estado: 'PROGRAMADO' | 'EN_CURSO' }> = {}) {
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
  preview?: () => unknown
  confirm?: () => unknown
}) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')

  return mockApiWith(({ url, method, body }) => {
    if (url.endsWith('/auth/yo')) return { body: meBody('docente') }
    if (url.endsWith('/examenes/habilitados')) return { body: { data: handler.exams() } }
    if (/\/examenes\/\d+\/habilitados\/preview$/.test(url) && handler.preview) {
      return { body: { data: handler.preview() } }
    }
    if (/\/examenes\/\d+\/habilitados\/confirm$/.test(url) && handler.confirm) {
      return { body: { data: handler.confirm() } }
    }
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

 it('muestra un estado vacío sin botón a otra HU cuando no hay exámenes', async () => {
    stubBackend({ exams: () => [] })

    renderApp('/examenes/habilitados')

    expect(await screen.findByText('Aún no tiene exámenes disponibles')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Cuando registre un examen, podrá habilitar a los estudiantes de sus grupos desde aquí.'
      )
    ).toBeInTheDocument()
    // Independencia entre HUs: la vista no ofrece un atajo a registrar examen.
    expect(
      screen.queryByRole('link', { name: /Ir a registrar examen/i })
    ).not.toBeInTheDocument()
  })

  it('carga exámenes y la lista automáticamente', async () => {
    const api = stubBackend({ exams: examFixture, list: listFixture })

    renderApp('/examenes/habilitados')

    expect(await screen.findAllByLabelText(/Marcar a Álvarez Rojas, Camila/i)).not.toHaveLength(0)
    expect(api.calls.some((c) => c.url.includes('/examenes/12/habilitados'))).toBe(true)
  })

  it('muestra banner de solo lectura cuando editable=false', async () => {
    stubBackend({
      exams: examFixture,
      list: () => listFixture({ editable: false, estado: 'EN_CURSO' }),
    })

    renderApp('/examenes/habilitados')

    expect(await screen.findByText(/El control de ingreso ya empezó/i)).toBeInTheDocument()
  })

  it('muestra el aviso de control abierto cuando el examen sigue PROGRAMADO pero la lista ya no es editable', async () => {
    stubBackend({
      exams: examFixture,
      list: () => listFixture({ editable: false, estado: 'PROGRAMADO' }),
    })

    renderApp('/examenes/habilitados')

    expect(await screen.findByText(/El control de ingreso ya se abrió/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Importar desde archivo/i })).toBeDisabled()
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

    await screen.findAllByLabelText(/Marcar a Álvarez Rojas, Camila/i)

    const [checkbox] = screen.getAllByLabelText(/Marcar a Álvarez Rojas, Camila/i)
    await user.click(checkbox)
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

    const [checkbox] = await screen.findAllByLabelText(/Marcar a Álvarez Rojas, Camila/i)

    await user.click(checkbox)
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
  expect(screen.getByText('Error del servidor')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /Reintentar/i })).toBeInTheDocument()
})

it('restaura el examen seleccionado desde ?examen=12', async () => {
  const api = stubBackend({ exams: examFixture, list: listFixture })

  renderApp('/examenes/habilitados?examen=12')

  expect(await screen.findAllByLabelText(/Marcar a Álvarez Rojas, Camila/i)).not.toHaveLength(0)
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

  await screen.findAllByLabelText(/Marcar a Álvarez Rojas, Camila/i)

  await user.click(screen.getByRole('combobox', { name: /Examen/i }))
  await user.click(screen.getByRole('option', { name: /Segundo parcial/i }))

  await waitFor(() => {
    expect(api.calls.some((c) => c.url.includes('/examenes/13/habilitados'))).toBe(true)
  })
})

it('reemplaza la lista con el archivo en una sola petición, avisa y descarta los cambios sin guardar', async () => {
  let replaced = false
  const api = stubBackend({
    exams: examFixture,
    list: () =>
      replaced
        ? {
            ...listFixture(),
            estudiantes: [
              { ...listFixture().estudiantes[0], estado_habilitacion: 'HABILITADO' },
              {
                ...listFixture().estudiantes[1],
                motivo: 'NO_FIGURA_EN_LISTA',
              },
            ],
          }
        : listFixture(),
    preview: () => ({
      token: 't'.repeat(64),
      total_filas: 1,
      filas_validas: 1,
      filas_inconsistentes: 0,
      faltantes: 1,
      filas: [],
    }),
    confirm: () => {
      replaced = true
      return {
        total_filas: 1,
        filas_inconsistentes: 0,
        no_encontrados: 0,
        habilitados: 1,
        total_no_habilitados: 1,
        motivo: 'NO_FIGURA_EN_LISTA',
        no_habilitados: [
          { id_estudiante: 2, codigo_sis: '202111278', nombre_completo: 'Gutiérrez Vela, Andrea' },
        ],
      }
    },
  })

  const user = userEvent.setup({ pointerEventsCheck: 0 })
  renderApp('/examenes/habilitados')

  // Un ajuste manual sin guardar: el archivo lo reemplazará.
  const [checkbox] = await screen.findAllByLabelText(/Marcar a Álvarez Rojas, Camila/i)
  await user.click(checkbox)
  await user.click(screen.getByRole('button', { name: /No habilitar…/i }))
  await user.click(screen.getByRole('combobox', { name: /Motivo/i }))
  await user.click(screen.getByRole('option', { name: /Asistencia insuficiente/i }))
  await user.click(screen.getByRole('button', { name: /^No habilitar$/i }))
  expect(await screen.findByText(/1 cambio sin guardar/i)).toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: /Importar desde archivo/i }))
  const input = document.querySelector('input[type="file"]') as HTMLInputElement
  await user.upload(input, new File(['codigo_sis\n202210443'], 'lista.csv'))

  expect(
    await screen.findByText('Se reemplazará la lista actual, incluidos los ajustes manuales.')
  ).toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: /Reemplazar la lista/i }))

  expect(await screen.findByText('Quedaron no habilitados')).toBeInTheDocument()
  // Solo una petición de confirmación y ningún PUT manual: el reemplazo es atómico en el servidor.
  expect(api.calls.filter((c) => c.url.endsWith('/habilitados/confirm'))).toHaveLength(1)
  expect(api.calls.some((c) => c.method === 'PUT')).toBe(false)
  // Los cambios locales sin guardar se descartan y la lista se recarga desde el servidor.
  await waitFor(() => expect(screen.queryByText(/cambio sin guardar/i)).not.toBeInTheDocument())
})
