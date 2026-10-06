import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeAll, describe, expect, it } from 'vitest'
import { useLocation } from 'react-router-dom'
import { AdminSubjectsPage } from './AdminSubjectsPage'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'

beforeAll(() => {
  Object.defineProperty(HTMLElement.prototype, 'hasPointerCapture', { configurable: true, value: () => false })
  Object.defineProperty(HTMLElement.prototype, 'setPointerCapture', { configurable: true, value: () => undefined })
  Object.defineProperty(HTMLElement.prototype, 'releasePointerCapture', { configurable: true, value: () => undefined })
  Object.defineProperty(Element.prototype, 'scrollIntoView', { configurable: true, value: () => undefined })
})

const sistemas = { id_carrera: 3, nombre: 'Ingenieria de Sistemas', codigo: 'SIS', id_facultad: 1 }
const civil = { id_carrera: 4, nombre: 'Ingenieria Civil', codigo: 'CIV', id_facultad: 1 }

const calculo = { id_materia: 10, nombre: 'Cálculo II', codigo: '2008057', descripcion: null, estado: 'ACTIVO' }
const bases = { id_materia: 20, nombre: 'Bases de Datos I', codigo: '2008058', descripcion: null, estado: 'INACTIVO' }
const ia = { id_materia: 30, nombre: 'Inteligencia Artificial', codigo: '2008001', descripcion: null, estado: 'ACTIVO' }

const pairCalculo = { id_carrera: 3, id_materia: 10, estado: 'ACTIVO', carrera: sistemas, materia: calculo }
const pairTopografia = {
  id_carrera: 4,
  id_materia: 40,
  estado: 'INACTIVO',
  carrera: civil,
  materia: { id_materia: 40, nombre: 'Topografia', codigo: '2008099', descripcion: null, estado: 'ACTIVO' },
}

/** Deja a la vista la URL actual para comprobar lo que la pantalla escribe en ella. */
function LocationSpy() {
  const { pathname, search } = useLocation()

  return <output data-testid="url">{`${pathname}${search}`}</output>
}

function renderPage(route: string) {
  return renderWithRouter(
    <>
      <AdminSubjectsPage />
      <LocationSpy />
    </>,
    { route }
  )
}

function currentUrl() {
  return screen.getByTestId('url').textContent
}

/** El servidor de mentira ignora tildes y mayúsculas, como el real. */
function plain(text: string) {
  return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
}

/** Respuestas del backend para las dos pestañas; `assigned` simula el estado tras asignar. */
function stubBackend() {
  const state = { assigned: false }

  const api = mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/materias/administracion') {
      const term = plain(url.searchParams.get('q') ?? '')
      const data = [calculo, bases, ia].filter(
        (subject) => plain(subject.nombre).includes(term) || subject.codigo.includes(term)
      )

      return { body: { data } }
    }

    if (path === '/administracion/carreras') {
      return { body: { data: [sistemas, civil] } }
    }

    if (path === '/administracion/carreras/3/materias-asignables') {
      return { body: { data: state.assigned ? [] : [ia] } }
    }

    if (path === '/administracion/carreras/3/materias' && request.method === 'POST') {
      state.assigned = true

      return {
        status: 201,
        body: {
          data: { id_carrera: 3, id_materia: 30, estado: 'ACTIVO', carrera: sistemas, materia: ia },
          mensaje: 'Materia asignada a la carrera correctamente.',
        },
      }
    }

    if (path === '/administracion/asignaciones') {
      const careerId = url.searchParams.get('id_carrera')
      const pairs = [
        pairCalculo,
        ...(state.assigned
          ? [{ id_carrera: 3, id_materia: 30, estado: 'ACTIVO', carrera: sistemas, materia: ia }]
          : []),
        pairTopografia,
      ]

      // Como el servidor real: las opciones son las carreras con pares, por nombre y sin importar el filtro.
      const carreras = [civil, sistemas].filter((career) => pairs.some((pair) => pair.id_carrera === career.id_carrera))

      return {
        body: {
          data: careerId ? pairs.filter((pair) => String(pair.id_carrera) === careerId) : pairs,
          meta: { carreras },
        },
      }
    }

    return undefined
  })

  return { api, state }
}

async function selectOption(selectName: string, optionName: string) {
  await userEvent.click(screen.getByRole('combobox', { name: selectName }))
  await userEvent.click(await screen.findByRole('option', { name: optionName }))
}

function requestsTo(calls: RecordedRequest[], fragment: string, method = 'GET') {
  return calls.filter((call) => call.method === method && call.url.includes(fragment))
}

describe('AdminSubjectsPage — pestañas', () => {
  it('abre en Catálogo con las dos pestañas', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })

    expect(screen.getByRole('heading', { name: 'Materias' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Catálogo' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Asignaciones' })).toHaveAttribute('aria-selected', 'false')
    expect(await screen.findByText('Cálculo II')).toBeInTheDocument()
  })

  it('?tab=asignaciones abre directamente la pestaña Asignaciones', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })

    expect(screen.getByRole('tab', { name: 'Asignaciones' })).toHaveAttribute('aria-selected', 'true')
    expect(await screen.findByText('Topografia')).toBeInTheDocument()
  })

  it('un valor desconocido de ?tab= cae en Catálogo', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=otra' })

    expect(screen.getByRole('tab', { name: 'Catálogo' })).toHaveAttribute('aria-selected', 'true')
    expect(await screen.findByText('Cálculo II')).toBeInTheDocument()
  })

  it('cambia de pestaña al hacer clic y solo consulta lo de la pestaña visible', async () => {
    const { api } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })
    await screen.findByText('Cálculo II')

    expect(requestsTo(api.calls, '/administracion/asignaciones')).toHaveLength(0)

    await userEvent.click(screen.getByRole('tab', { name: 'Asignaciones' }))

    expect(await screen.findByText('Topografia')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Asignaciones' })).toHaveAttribute('aria-selected', 'true')

    await userEvent.click(screen.getByRole('tab', { name: 'Catálogo' }))

    expect(await screen.findByText('Bases de Datos I')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Catálogo' })).toHaveAttribute('aria-selected', 'true')
  })
})

describe('AdminSubjectsPage — pestaña Catálogo', () => {
  it('muestra código, nombre y estado de cada materia', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })

    const table = within(await screen.findByRole('table'))
    const rows = table.getAllByRole('row').slice(1)

    expect(rows).toHaveLength(3)
    expect(within(rows[0]).getByText('2008057')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Cálculo II')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Activo')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Bases de Datos I')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Inactivo')).toBeInTheDocument()
    expect(table.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Código',
      'Materia',
      'Estado',
    ])
  })

  it('es de solo lectura: ni crear, ni editar, ni eliminar', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })
    await screen.findByText('Cálculo II')

    expect(screen.queryByRole('link', { name: /editar/i })).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: /editar|registrar|crear|nueva|eliminar|borrar/i })
    ).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /registrar|crear|nueva/i })).not.toBeInTheDocument()
  })

  it('busca por nombre sin distinguir tildes y pide el término al servidor', async () => {
    const { api } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })
    await screen.findByText('Bases de Datos I')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar materias' }), 'calculo')

    await waitFor(() => expect(screen.queryByText('Bases de Datos I')).not.toBeInTheDocument())
    expect(screen.getByText('Cálculo II')).toBeInTheDocument()
    expect(requestsTo(api.calls, 'q=calculo')).toHaveLength(1)
  })

  it('busca por código', async () => {
    const { api } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })
    await screen.findByText('Bases de Datos I')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar materias' }), '2008058')

    await waitFor(() => expect(screen.queryByText('Cálculo II')).not.toBeInTheDocument())
    expect(screen.getByText('Bases de Datos I')).toBeInTheDocument()
    expect(requestsTo(api.calls, 'q=2008058')).toHaveLength(1)
  })

  it('no lanza una petición por cada tecla: espera a que termine de escribir', async () => {
    const { api } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })
    await screen.findByText('Bases de Datos I')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar materias' }), 'calc')

    await waitFor(() => expect(requestsTo(api.calls, 'q=calc')).toHaveLength(1))
    expect(requestsTo(api.calls, 'q=c&')).toHaveLength(0)
    expect(requestsTo(api.calls, 'q=ca')).toHaveLength(1)
  })

  it('indica cuando ninguna materia coincide con la búsqueda', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })
    await screen.findByText('Bases de Datos I')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar materias' }), 'zzzz')

    expect(await screen.findByText('Ninguna materia coincide')).toBeInTheDocument()
  })

  it('muestra el estado vacío cuando el catálogo no tiene materias', async () => {
    mockApiWith(() => ({ body: { data: [] } }))

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })

    expect(await screen.findByText('Aún no hay materias registradas')).toBeInTheDocument()
  })

  it('muestra el error del servidor con opción de reintentar', async () => {
    mockApiWith(() => ({ status: 500, body: { message: 'Error interno del servidor.' } }))

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias' })

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })
})

describe('AdminSubjectsPage — pestaña Asignaciones', () => {
  it('lista carrera, código, nombre de la materia y estado', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })

    const table = within(await screen.findByRole('table'))

    expect(table.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Carrera',
      'Código',
      'Materia',
      'Estado',
    ])

    const rows = table.getAllByRole('row').slice(1)

    expect(within(rows[0]).getByText('Ingenieria de Sistemas')).toBeInTheDocument()
    expect(within(rows[0]).getByText('2008057')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Cálculo II')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Activo')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Ingenieria Civil')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Inactivo')).toBeInTheDocument()
  })

  it('no ofrece editar, habilitar, deshabilitar ni eliminar asignaciones', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })
    await screen.findByText('Topografia')

    expect(screen.queryByRole('link', { name: /editar/i })).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: /editar|habilitar|deshabilitar|desactivar|activar|eliminar|borrar/i })
    ).not.toBeInTheDocument()
  })

  it('filtra la lista por carrera', async () => {
    const { api } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })
    await screen.findByText('Topografia')
    expect(screen.getByText('Cálculo II')).toBeInTheDocument()

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toBeEnabled())
    await selectOption('Filtrar por carrera', 'CIV · Ingenieria Civil')

    await waitFor(() => expect(screen.queryByText('Cálculo II')).not.toBeInTheDocument())
    expect(screen.getByText('Topografia')).toBeInTheDocument()
    expect(requestsTo(api.calls, 'id_carrera=4')).toHaveLength(1)

    await selectOption('Filtrar por carrera', 'Todas las carreras')

    expect(await screen.findByText('Cálculo II')).toBeInTheDocument()
  })

  it('una asignación nueva aparece en la lista sin recargar la página', async () => {
    const { api } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })
    await screen.findByText('Topografia')
    expect(screen.queryByText('Inteligencia Artificial', { selector: 'td' })).not.toBeInTheDocument()

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())
    await selectOption('Carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
    await selectOption('Materia', '2008001 · Inteligencia Artificial')

    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    expect(
      await screen.findByText('Inteligencia Artificial fue asignada a Ingenieria de Sistemas.')
    ).toBeInTheDocument()

    expect(await screen.findByText('Inteligencia Artificial', { selector: 'td' })).toBeInTheDocument()
    expect(requestsTo(api.calls, '/administracion/asignaciones')).toHaveLength(2)
    expect(requestsTo(api.calls, '/administracion/carreras/3/materias', 'POST')).toHaveLength(1)
  })

  it('la lista se refresca conservando el filtro de carrera', async () => {
    const { api } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })
    await screen.findByText('Topografia')

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toBeEnabled())
    await selectOption('Filtrar por carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.queryByText('Topografia')).not.toBeInTheDocument())

    await selectOption('Carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
    await selectOption('Materia', '2008001 · Inteligencia Artificial')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    expect(await screen.findByText('Inteligencia Artificial', { selector: 'td' })).toBeInTheDocument()
    expect(requestsTo(api.calls, 'id_carrera=3')).toHaveLength(2)
    expect(screen.queryByText('Topografia')).not.toBeInTheDocument()
  })

  it('cancelar la confirmación no escribe nada', async () => {
    const { api, state } = stubBackend()

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })
    await screen.findByText('Topografia')

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())
    await selectOption('Carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
    await selectOption('Materia', '2008001 · Inteligencia Artificial')

    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(api.calls.filter((call) => call.method !== 'GET')).toHaveLength(0)
    expect(state.assigned).toBe(false)
    expect(requestsTo(api.calls, '/administracion/asignaciones')).toHaveLength(1)
  })

  it('muestra el estado vacío cuando no hay asignaciones', async () => {
    mockApiWith((request) =>
      request.url.includes('/administracion/carreras')
        ? { body: { data: [sistemas] } }
        : { body: { data: [], meta: { carreras: [] } } }
    )

    renderWithRouter(<AdminSubjectsPage />, { route: '/materias?tab=asignaciones' })

    expect(await screen.findByText('Sin materias asignadas')).toBeInTheDocument()
  })
})

describe('AdminSubjectsPage — filtro por carrera de las asignaciones', () => {
  async function openFilter() {
    await userEvent.click(screen.getByRole('combobox', { name: 'Filtrar por carrera' }))

    return screen.findAllByRole('option')
  }

  it('ofrece «Todas las carreras» y las carreras con asignaciones, por nombre', async () => {
    stubBackend()

    renderPage('/materias?tab=asignaciones')
    await screen.findByText('Topografia')

    const options = await openFilter()

    expect(options.map((option) => option.textContent)).toEqual([
      'Todas las carreras',
      'CIV · Ingenieria Civil',
      'SIS · Ingenieria de Sistemas',
    ])
  })

  it('arranca en «Todas las carreras» y la lista trae todos los pares', async () => {
    const { api } = stubBackend()

    renderPage('/materias?tab=asignaciones')
    await screen.findByText('Topografia')

    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('Todas las carreras')
    expect(screen.getByText('Cálculo II')).toBeInTheDocument()
    expect(requestsTo(api.calls, '/administracion/asignaciones')[0].url).not.toContain('id_carrera')
  })

  it('al elegir una carrera pide el filtro al servidor y muestra solo sus filas', async () => {
    const { api } = stubBackend()

    renderPage('/materias?tab=asignaciones')
    await screen.findByText('Topografia')

    await selectOption('Filtrar por carrera', 'CIV · Ingenieria Civil')

    await waitFor(() => expect(screen.queryByText('Cálculo II')).not.toBeInTheDocument())
    expect(screen.getByText('Topografia')).toBeInTheDocument()

    const filtered = requestsTo(api.calls, 'id_carrera=4')

    expect(filtered).toHaveLength(1)
    expect(new URL(filtered[0].url).searchParams.get('id_carrera')).toBe('4')
  })

  it('las opciones no se achican al filtrar', async () => {
    stubBackend()

    renderPage('/materias?tab=asignaciones')
    await screen.findByText('Topografia')
    await selectOption('Filtrar por carrera', 'CIV · Ingenieria Civil')
    await waitFor(() => expect(screen.queryByText('Cálculo II')).not.toBeInTheDocument())

    expect((await openFilter()).map((option) => option.textContent)).toEqual([
      'Todas las carreras',
      'CIV · Ingenieria Civil',
      'SIS · Ingenieria de Sistemas',
    ])
  })

  it('escribe el filtro en la URL junto a ?tab= y lo quita al volver a «Todas las carreras»', async () => {
    stubBackend()

    renderPage('/materias?tab=asignaciones')
    await screen.findByText('Topografia')

    await selectOption('Filtrar por carrera', 'CIV · Ingenieria Civil')
    await waitFor(() => expect(currentUrl()).toBe('/materias?tab=asignaciones&carrera=4'))

    await selectOption('Filtrar por carrera', 'Todas las carreras')
    await waitFor(() => expect(currentUrl()).toBe('/materias?tab=asignaciones'))
    expect(await screen.findByText('Cálculo II')).toBeInTheDocument()
  })

  it('lee el filtro de la URL: un enlace directo o una recarga lo conservan', async () => {
    const { api } = stubBackend()

    renderPage('/materias?tab=asignaciones&carrera=4')

    expect(await screen.findByText('Topografia')).toBeInTheDocument()
    expect(screen.queryByText('Cálculo II')).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('CIV · Ingenieria Civil')
    expect(requestsTo(api.calls, 'id_carrera=4')).toHaveLength(1)
    expect(currentUrl()).toBe('/materias?tab=asignaciones&carrera=4')
  })

  it.each([
    ['no numérico', 'abc'],
    ['cero', '0'],
    ['negativo', '-3'],
    ['decimal', '1.5'],
    ['fuera del rango de int4', '99999999999'],
  ])('un valor inválido en la URL (%s) cae en «Todas las carreras» y se limpia', async (_label, value) => {
    stubBackend()

    renderPage(`/materias?tab=asignaciones&carrera=${value}`)

    expect(await screen.findByText('Topografia')).toBeInTheDocument()
    expect(screen.getByText('Cálculo II')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('Todas las carreras')
    await waitFor(() => expect(currentUrl()).toBe('/materias?tab=asignaciones'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('una carrera desconocida o sin asignaciones cae en «Todas las carreras» sin errores', async () => {
    stubBackend()

    renderPage('/materias?tab=asignaciones&carrera=999')

    expect(await screen.findByText('Topografia')).toBeInTheDocument()
    expect(screen.getByText('Cálculo II')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('Todas las carreras')
    await waitFor(() => expect(currentUrl()).toBe('/materias?tab=asignaciones'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.queryByText('Esta carrera aún no tiene materias asignadas')).not.toBeInTheDocument()
  })

  it('el filtro sobrevive a una asignación: la lista se refresca con la misma carrera', async () => {
    const { api } = stubBackend()

    renderPage('/materias?tab=asignaciones&carrera=3')
    await screen.findByText('Cálculo II')
    expect(screen.queryByText('Topografia')).not.toBeInTheDocument()

    await selectOption('Carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
    await selectOption('Materia', '2008001 · Inteligencia Artificial')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    expect(await screen.findByText('Inteligencia Artificial', { selector: 'td' })).toBeInTheDocument()
    expect(screen.queryByText('Topografia')).not.toBeInTheDocument()
    expect(requestsTo(api.calls, 'id_carrera=3')).toHaveLength(2)
    expect(currentUrl()).toBe('/materias?tab=asignaciones&carrera=3')
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('SIS · Ingenieria de Sistemas')
  })

  it('si el par nuevo es de otra carrera, la confirmación se ve y el filtro no cambia', async () => {
    stubBackend()

    renderPage('/materias?tab=asignaciones&carrera=4')
    await screen.findByText('Topografia')

    await selectOption('Carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
    await selectOption('Materia', '2008001 · Inteligencia Artificial')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    expect(
      await screen.findByText('Inteligencia Artificial fue asignada a Ingenieria de Sistemas.')
    ).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())

    expect(currentUrl()).toBe('/materias?tab=asignaciones&carrera=4')
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('CIV · Ingenieria Civil')
    expect(screen.getByText('Topografia')).toBeInTheDocument()
    expect(screen.queryByText('Inteligencia Artificial', { selector: 'td' })).not.toBeInTheDocument()
  })

  it('muestra «Esta carrera aún no tiene materias asignadas» cuando la carrera filtrada no tiene filas', async () => {
    mockApiWith((request) => {
      if (request.url.includes('/administracion/asignaciones')) {
        return { body: { data: [], meta: { carreras: [sistemas] } } }
      }

      return { body: { data: [sistemas] } }
    })

    renderPage('/materias?tab=asignaciones&carrera=3')

    expect(await screen.findByText('Esta carrera aún no tiene materias asignadas')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('al cambiar de pestaña el filtro se limpia de la URL', async () => {
    stubBackend()

    renderPage('/materias?tab=asignaciones&carrera=4')
    await screen.findByText('Topografia')

    await userEvent.click(screen.getByRole('tab', { name: 'Catálogo' }))

    await waitFor(() => expect(currentUrl()).toBe('/materias'))

    await userEvent.click(screen.getByRole('tab', { name: 'Asignaciones' }))

    expect(await screen.findByText('Cálculo II')).toBeInTheDocument()
    expect(currentUrl()).toBe('/materias?tab=asignaciones')
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('Todas las carreras')
  })
})
