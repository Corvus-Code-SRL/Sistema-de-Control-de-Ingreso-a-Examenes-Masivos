import { screen, waitFor, within } from '@testing-library/react'
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
      grupos: ['2', '3'],
      ambientes: [
        { id: 1, nro_aula: '691A' },
        { id: 2, nro_aula: '692B' },
      ],
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
  preview?: () => unknown
  confirm?: () => unknown
}) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')

  return mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) return { body: meBody('docente') }
    if (url.endsWith('/examenes/habilitados')) return { body: { data: handler.exams() } }
    if (/\/examenes\/\d+\/habilitados\/preview$/.test(url) && handler.preview) {
      return { body: { data: handler.preview() } }
    }
    if (/\/examenes\/\d+\/habilitados\/confirm$/.test(url) && handler.confirm) {
      return { body: { data: handler.confirm() } }
    }
    if (/\/examenes\/\d+\/habilitados$/.test(url)) {
      if (handler.list) return { body: { data: handler.list() } }
    }
    return { body: { data: [] } }
  })
}

// A nivel de módulo: varios tests viven fuera del describe y el hook guarda el examen elegido en sessionStorage.
afterEach(() => {
  window.localStorage.clear()
  window.sessionStorage.clear()
})

describe('ExamEligibleListPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

 it('muestra un estado vacío sin botón a otra HU cuando no hay exámenes', async () => {
    stubBackend({ exams: () => [] })

    renderApp('/examenes/habilitados')

    expect(await screen.findByText('Aún no tiene exámenes disponibles')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Cuando registre un examen, podrá cargar la lista de habilitados desde aquí.'
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

    expect(await screen.findAllByText('Álvarez Rojas, Camila')).not.toHaveLength(0)
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
    expect(screen.getByRole('button', { name: 'Cargar lista de habilitados' })).toBeDisabled()
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

  expect(await screen.findAllByText('Álvarez Rojas, Camila')).not.toHaveLength(0)
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
      grupos: ['1'],
      ambientes: [],
    },
  ]
  const api = stubBackend({
    exams: () => twoExams,
    list: listFixture,
  })

  const user = userEvent.setup()
  renderApp('/examenes/habilitados')

  await screen.findAllByText('Álvarez Rojas, Camila')

  await user.click(screen.getByRole('combobox', { name: /Examen/i }))
  await user.click(screen.getByRole('option', { name: /Segundo parcial/i }))

  await waitFor(() => {
    expect(api.calls.some((c) => c.url.includes('/examenes/13/habilitados'))).toBe(true)
  })
})

it('reemplaza la lista con el archivo en una sola petición y recarga la lista', async () => {
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

  await screen.findAllByText('Álvarez Rojas, Camila')

  await user.click(screen.getByRole('button', { name: 'Cargar lista de habilitados' }))
  const input = document.querySelector('input[type="file"]') as HTMLInputElement
  await user.upload(input, new File(['codigo_sis\n202210443'], 'lista.csv'))

  expect(
    await screen.findByText('Se reemplazará la lista actual.')
  ).toBeInTheDocument()
  await user.click(screen.getByRole('button', { name: /Reemplazar la lista/i }))

  expect(await screen.findByText('Quedaron no habilitados')).toBeInTheDocument()
  // Solo una petición de confirmación y ningún PUT manual: el reemplazo es atómico en el servidor.
  expect(api.calls.filter((c) => c.url.endsWith('/habilitados/confirm'))).toHaveLength(1)
  expect(api.calls.some((c) => c.method === 'PUT')).toBe(false)
  // Tras el reemplazo la lista se vuelve a pedir al servidor.
  await waitFor(() =>
    expect(
      api.calls.filter((c) => c.method === 'GET' && /\/examenes\/12\/habilitados$/.test(c.url)).length
    ).toBeGreaterThan(1)
  )
})

it('no ofrece edición manual: ni casillas, ni «No habilitar», ni guardar', async () => {
  stubBackend({ exams: examFixture, list: listFixture })

  renderApp('/examenes/habilitados')

  await screen.findAllByText('Álvarez Rojas, Camila')
  expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  expect(screen.queryByRole('button', { name: /No habilitar/i })).not.toBeInTheDocument()
  expect(screen.queryByRole('button', { name: /Guardar/i })).not.toBeInTheDocument()
})

it('no tiene sección de plantilla en la página; la plantilla vive solo en el diálogo', async () => {
  stubBackend({ exams: examFixture, list: listFixture })

  const user = userEvent.setup({ pointerEventsCheck: 0 })
  renderApp('/examenes/habilitados')

  await screen.findAllByText('Álvarez Rojas, Camila')
  expect(screen.queryByRole('link', { name: 'Descargar plantilla' })).not.toBeInTheDocument()
  expect(screen.queryByText('Formato del archivo')).not.toBeInTheDocument()
  expect(screen.queryByText(/Formatos: CSV o XLSX/)).not.toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: 'Cargar lista de habilitados' }))
  expect(screen.getByRole('link', { name: 'Descargar plantilla' })).toHaveAttribute(
    'href',
    '/plantillas/nomina-plantilla.csv'
  )
  expect(
    screen.getByText('Se compara por código SIS; los demás datos son referenciales.')
  ).toBeInTheDocument()
})

it('muestra los totales y el estado, pero no la columna «Motivo»', async () => {
  stubBackend({ exams: examFixture, list: listFixture })

  renderApp('/examenes/habilitados')

  await screen.findAllByText('Álvarez Rojas, Camila')
  expect(screen.getByText('En la nómina del examen')).toBeInTheDocument()
  expect(screen.getByRole('button', { name: /No habilitados · 1/ })).toBeInTheDocument()
  expect(screen.getAllByText('No habilitado').length).toBeGreaterThan(0)
  expect(screen.queryByRole('columnheader', { name: 'Motivo' })).not.toBeInTheDocument()
  expect(screen.queryByText('Asistencia insuficiente')).not.toBeInTheDocument()
})

it('al elegir un examen muestra la tarjeta de contexto con sus datos', async () => {
  stubBackend({ exams: examFixture, list: listFixture })

  renderApp('/examenes/habilitados')

  const context = await screen.findByLabelText('Contexto del examen')
  expect(within(context).getByText('Cálculo I')).toBeInTheDocument()
  expect(within(context).getByText('2 y 3')).toBeInTheDocument()
  expect(within(context).getByText('20/10/2026')).toBeInTheDocument()
  expect(within(context).getByText('10:00')).toBeInTheDocument()
  expect(within(context).getByText('691A, 692B')).toBeInTheDocument()
  expect(within(context).queryByText(/Programado|PROGRAMADO|Hora de fin/)).not.toBeInTheDocument()
})

it('indica «Sin ambientes» cuando el examen no tiene ambientes', async () => {
  stubBackend({
    exams: () => [{ ...examFixture()[0], ambientes: [] }],
    list: listFixture,
  })

  renderApp('/examenes/habilitados')

  const context = await screen.findByLabelText('Contexto del examen')
  expect(within(context).getByText('Sin ambientes')).toBeInTheDocument()
})

it('muestra la hora de inicio como HH:MM, tal como llega del servidor', async () => {
  stubBackend({
    exams: () => [{ ...examFixture()[0], hora_inicio: '07:30:00' }],
    list: listFixture,
  })

  renderApp('/examenes/habilitados')

  const context = await screen.findByLabelText('Contexto del examen')
  expect(within(context).getByText('07:30')).toBeInTheDocument()
})

it('una lista de 2000 estudiantes va en un contenedor con scroll, sin paginación', async () => {
  const big = {
    editable: true,
    estado: 'PROGRAMADO',
    estudiantes: Array.from({ length: 2000 }, (_, i) => ({
      id_estudiante: i + 1,
      codigo_sis: String(300000000 + i),
      nombre_completo: `Apellido${i} Nombre${i}`,
      id_grupo: 2,
      nombre_grupo: 'Grupo 2',
      estado_habilitacion: 'HABILITADO',
      caso_especial: false,
      motivo: null,
      detalle: null,
    })),
  }
  stubBackend({ exams: examFixture, list: () => big })

  renderApp('/examenes/habilitados')

  const scroll = await screen.findByTestId('eligible-scroll', {}, { timeout: 10000 })
  expect(scroll.className).toMatch(/max-h-\[60vh\]/)
  expect(scroll.className).toMatch(/sm:max-h-96/)
  expect(scroll.className).toMatch(/overflow-y-auto/)
  expect(scroll.querySelectorAll('tbody tr')).toHaveLength(2000)
  expect(scroll.querySelector('th')?.className).toMatch(/sticky/)
  expect(screen.queryByRole('navigation', { name: /Paginación/i })).not.toBeInTheDocument()
  expect(screen.queryByRole('button', { name: /Siguiente/i })).not.toBeInTheDocument()
  expect(screen.getByText('2000 estudiantes en la lista.')).toBeInTheDocument()
}, 30000)
