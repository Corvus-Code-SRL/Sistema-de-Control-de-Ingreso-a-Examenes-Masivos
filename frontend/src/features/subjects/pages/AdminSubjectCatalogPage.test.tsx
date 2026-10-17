import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { AdminSubjectCatalogPage } from './AdminSubjectCatalogPage'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'

const calculo = { id_materia: 10, nombre: 'Cálculo II', codigo: '2008057', descripcion: null, estado: 'ACTIVO' }
const bases = { id_materia: 20, nombre: 'Bases de Datos I', codigo: '2008058', descripcion: null, estado: 'INACTIVO' }
const ia = { id_materia: 30, nombre: 'Inteligencia Artificial', codigo: '2008001', descripcion: null, estado: 'ACTIVO' }

/** El servidor de mentira ignora tildes y mayúsculas, como el real. */
function plain(text: string) {
  return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
}

function stubBackend() {
  return mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/materias/administracion') {
      const term = plain(url.searchParams.get('q') ?? '')
      const data = [calculo, bases, ia].filter(
        (subject) => plain(subject.nombre).includes(term) || subject.codigo.includes(term)
      )

      return { body: { data } }
    }

    return undefined
  })
}

function requestsTo(calls: RecordedRequest[], fragment: string) {
  return calls.filter((call) => call.method === 'GET' && call.url.includes(fragment))
}

describe('AdminSubjectCatalogPage', () => {
  it('es una vista propia: sin pestañas y sin consultar asignaciones', async () => {
    const api = stubBackend()

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })

    expect(screen.getByRole('heading', { name: 'Catálogo de materias' })).toBeInTheDocument()
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(await screen.findByText('Cálculo II')).toBeInTheDocument()
    expect(requestsTo(api.calls, '/administracion/')).toHaveLength(0)
    expect(requestsTo(api.calls, '/periodos')).toHaveLength(0)
  })

  it('muestra código, nombre y estado de cada materia', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })

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

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })
    await screen.findByText('Cálculo II')

    expect(
      screen.queryByRole('button', { name: /editar|registrar|crear|nueva|eliminar|borrar/i })
    ).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /editar|registrar|crear|nueva/i })).not.toBeInTheDocument()
  })

  it('busca por nombre sin distinguir tildes y pide el término al servidor', async () => {
    const api = stubBackend()

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })
    await screen.findByText('Bases de Datos I')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar materias' }), 'calculo')

    await waitFor(() => expect(screen.queryByText('Bases de Datos I')).not.toBeInTheDocument())
    expect(screen.getByText('Cálculo II')).toBeInTheDocument()
    expect(requestsTo(api.calls, 'q=calculo')).toHaveLength(1)
  })

  it('busca por código', async () => {
    const api = stubBackend()

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })
    await screen.findByText('Bases de Datos I')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar materias' }), '2008058')

    await waitFor(() => expect(screen.queryByText('Cálculo II')).not.toBeInTheDocument())
    expect(screen.getByText('Bases de Datos I')).toBeInTheDocument()
    expect(requestsTo(api.calls, 'q=2008058')).toHaveLength(1)
  })

  it('indica cuando ninguna materia coincide con la búsqueda', async () => {
    stubBackend()

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })
    await screen.findByText('Bases de Datos I')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar materias' }), 'zzzz')

    expect(await screen.findByText('Ninguna materia coincide')).toBeInTheDocument()
  })

  it('muestra el estado vacío cuando el catálogo no tiene materias', async () => {
    mockApiWith(() => ({ body: { data: [] } }))

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })

    expect(await screen.findByText('Aún no hay materias registradas')).toBeInTheDocument()
  })

  it('muestra el error del servidor con opción de reintentar', async () => {
    mockApiWith(() => ({ status: 500, body: { message: 'Error interno del servidor.' } }))

    renderWithRouter(<AdminSubjectCatalogPage />, { route: '/materias/catalogo' })

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })
})
