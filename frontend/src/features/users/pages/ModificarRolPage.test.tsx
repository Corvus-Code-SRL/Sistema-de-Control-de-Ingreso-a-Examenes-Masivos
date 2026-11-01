import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { ModificarRolPage } from './ModificarRolPage'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import {
  accountsResponse,
  administrator,
  guestTeacher,
  makeAccount,
  roles,
  teacher,
  userMatchers,
} from '@/test/userFixtures'

const withoutRole = makeAccount()

/** Responde el catálogo de roles y el listado que decida cada prueba. */
function stubBackend(listing: (request: RecordedRequest) => unknown) {
  return mockApiWith((request) => {
    if (userMatchers.roles(request.url)) return { body: { data: roles } }
    if (userMatchers.accounts(request.url)) return { body: listing(request) }

    return undefined
  })
}

function listingRequests(calls: RecordedRequest[]) {
  return calls.filter((call) => userMatchers.accounts(call.url)).map((call) => new URL(call.url))
}

async function renderPage(route = '/cuentas/roles') {
  renderWithRouter(<ModificarRolPage />, { route })
  await screen.findByRole('table')
}

function LocationProbe() {
  return <output data-testid="search">{useLocation().search}</output>
}

async function renderPageWithUrl(route: string) {
  render(
    <MemoryRouter initialEntries={[route]}>
      <ModificarRolPage />
      <LocationProbe />
    </MemoryRouter>
  )
  await screen.findByRole('table')
}

/** La vista de escritorio: la tabla repite los datos que la lista móvil también dibuja. */
function table() {
  return within(screen.getByRole('table'))
}

async function chooseRoleFilter(name: string) {
  await userEvent.click(screen.getByRole('combobox', { name: 'Rol activo' }))
  await userEvent.click(await screen.findByRole('option', { name }))
}

describe('ModificarRolPage — tabla', () => {
  it('lista nombre, código SIS o INV, correo, rol activo y una acción por cuenta', async () => {
    stubBackend(() => accountsResponse([administrator, teacher, guestTeacher, withoutRole]))

    await renderPage()

    expect(table().getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Nombre',
      'Código SIS / INV',
      'Correo',
      'Rol activo',
      'Acción',
    ])
    expect(table().getByText('Pablo Careaga Rojas')).toBeInTheDocument()
    expect(table().getByText('199800412')).toBeInTheDocument()
    expect(table().getByText('p.careaga@umss.edu')).toBeInTheDocument()
    expect(table().getByText('r.aguilar@umss.edu')).toBeInTheDocument()
    expect(table().getByText('Docente')).toBeInTheDocument()
    expect(table().getByText('INV4821')).toBeInTheDocument()
    expect(table().getByText('Sin rol')).toBeInTheDocument()
  })

  it('no ofrece «Ver Detalles» ni abre ningún modal de detalles', async () => {
    stubBackend(() => accountsResponse([teacher, guestTeacher, withoutRole]))

    await renderPage()

    expect(screen.queryByText(/Ver Detalles/i)).not.toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(table().getAllByRole('row')).toHaveLength(4)
    // Una sola acción por fila: «Modificar rol», «Asignar rol» o el botón bloqueado del invitado.
    for (const row of table().getAllByRole('row').slice(1)) {
      expect(within(row).getAllByRole(within(row).queryByRole('link') ? 'link' : 'button')).toHaveLength(1)
    }
  })

  it('«Modificar rol» es el botón principal y lleva a la vista de cambio de rol', async () => {
    stubBackend(() => accountsResponse([teacher]))

    await renderPage()

    const modify = table().getByRole('link', { name: 'Modificar rol de Pablo Careaga Rojas' })

    expect(modify).toHaveTextContent('Modificar rol')
    expect(modify).toHaveAttribute('href', `/cuentas/${teacher.id_usuario}`)
    expect(modify).toHaveAttribute('data-variant', 'default')
  })

  it('una cuenta sin rol ofrece «Asignar rol» en amarillo, en lugar de «Modificar rol»', async () => {
    stubBackend(() => accountsResponse([withoutRole]))

    await renderPage()

    const assign = table().getByRole('link', { name: 'Asignar rol de Laura Mendoza Rivas' })

    expect(assign).toHaveAttribute('data-variant', 'warning')
    expect(assign).toHaveClass('bg-warn', 'text-warn-fg')
    expect(assign).toHaveAttribute('href', `/cuentas/${withoutRole.id_usuario}`)
    expect(table().queryByRole('link', { name: /Modificar rol/ })).not.toBeInTheDocument()
  })

  it('el docente invitado figura como «Docente invitado (temporal)» con «Modificar rol» deshabilitado y gris', async () => {
    stubBackend(() => accountsResponse([guestTeacher]))

    await renderPage()

    expect(table().getByText('Docente invitado (temporal)')).toBeInTheDocument()
    expect(table().queryByText('Invitado')).not.toBeInTheDocument()

    const locked = table().getByRole('button', { name: 'Modificar rol de Rosa Aguilar' })

    expect(locked).toBeDisabled()
    expect(locked).toHaveClass('disabled:bg-muted', 'disabled:text-muted-foreground')
    expect(locked).toHaveAccessibleDescription('El rol de docente invitado no se puede cambiar.')
    expect(table().queryByRole('link', { name: /rol/i })).not.toBeInTheDocument()
  })

  it('el aviso del botón bloqueado aparece como tooltip al pasar el cursor', async () => {
    stubBackend(() => accountsResponse([guestTeacher]))

    await renderPage()
    await userEvent.hover(table().getByRole('button', { name: 'Modificar rol de Rosa Aguilar' }).parentElement!)

    expect(await screen.findByRole('tooltip')).toHaveTextContent('El rol de docente invitado no se puede cambiar.')
  })
})

describe('ModificarRolPage — filtro de rol', () => {
  it('ofrece, en orden, Todos los roles, Administrador, Docente, Auxiliar, Docente invitado y Sin rol', async () => {
    stubBackend(() => accountsResponse([teacher]))

    await renderPage()
    await userEvent.click(screen.getByRole('combobox', { name: 'Rol activo' }))

    expect((await screen.findAllByRole('option')).map((option) => option.textContent)).toEqual([
      'Todos los roles',
      'Administrador',
      'Docente',
      'Auxiliar',
      'Docente invitado',
      'Sin rol',
    ])
  })

  it.each([
    ['Administrador', '1'],
    ['Docente', '2'],
    ['Auxiliar', '3'],
    ['Docente invitado', '4'],
    ['Sin rol', 'sin-rol'],
  ])('«%s» filtra en el servidor con rol=%s y queda en la URL', async (option, expected) => {
    const { calls } = stubBackend(() => accountsResponse([teacher]))

    await renderPageWithUrl('/cuentas/roles')
    await chooseRoleFilter(option)

    await waitFor(() => expect(listingRequests(calls).at(-1)?.searchParams.get('rol')).toBe(expected))
    expect(screen.getByTestId('search')).toHaveTextContent(`rol=${expected}`)
    expect(screen.getByRole('combobox', { name: 'Rol activo' })).toHaveTextContent(option)
  })

  it('«Todos los roles» quita el filtro del servidor y de la URL', async () => {
    const { calls } = stubBackend(() => accountsResponse([teacher]))

    await renderPageWithUrl('/cuentas/roles?rol=sin-rol')
    await chooseRoleFilter('Todos los roles')

    await waitFor(() => expect(listingRequests(calls).at(-1)?.searchParams.has('rol')).toBe(false))
    expect(screen.getByTestId('search')).not.toHaveTextContent('rol=')
  })

  it('se combina con la búsqueda y vuelve a la primera página', async () => {
    const { calls } = stubBackend(() => accountsResponse([teacher], { page: 2, total: 12, lastPage: 2 }))

    await renderPageWithUrl('/cuentas/roles?q=pablo&page=2')
    await chooseRoleFilter('Docente invitado')

    await waitFor(() => expect(listingRequests(calls).at(-1)?.searchParams.get('rol')).toBe('4'))

    const last = listingRequests(calls).at(-1)

    expect(last?.searchParams.get('q')).toBe('pablo')
    expect(last?.searchParams.get('page')).toBe('1')
    expect(last?.searchParams.get('per_page')).toBe('10')
    expect(screen.getByTestId('search').textContent).toBe('?q=pablo&rol=4')
  })

  it('«Sin rol» se conserva al paginar', async () => {
    const { calls } = stubBackend((request) => {
      const page = Number(new URL(request.url).searchParams.get('page'))

      return accountsResponse([withoutRole], { page, total: 12, lastPage: 2 })
    })

    await renderPageWithUrl('/cuentas/roles?rol=sin-rol')
    await userEvent.click(screen.getByRole('button', { name: /Siguiente/ }))

    await waitFor(() => expect(listingRequests(calls).at(-1)?.searchParams.get('page')).toBe('2'))
    expect(listingRequests(calls).at(-1)?.searchParams.get('rol')).toBe('sin-rol')
    expect(screen.getByTestId('search').textContent).toBe('?rol=sin-rol&page=2')
  })

  it('lee el rol de la URL al abrir la vista y lo muestra elegido', async () => {
    const { calls } = stubBackend(() => accountsResponse([guestTeacher]))

    await renderPage('/cuentas/roles?rol=4')

    expect(listingRequests(calls)[0].searchParams.get('rol')).toBe('4')
    expect(await screen.findByRole('combobox', { name: 'Rol activo' })).toHaveTextContent('Docente invitado')
  })
})

describe('ModificarRolPage — búsqueda, paginación y errores', () => {
  it('la búsqueda viaja al servidor y queda en la URL junto con la paginación', async () => {
    const { calls } = stubBackend(() => accountsResponse([teacher]))

    await renderPage()
    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar por nombre o código' }), 'jose')

    await waitFor(() => expect(listingRequests(calls).at(-1)?.searchParams.get('q')).toBe('jose'))

    const last = listingRequests(calls).at(-1)

    expect(last?.searchParams.get('per_page')).toBe('10')
    expect(last?.searchParams.get('page')).toBe('1')
  })

  it('lee los filtros de la URL al abrir la vista', async () => {
    const { calls } = stubBackend(() => accountsResponse([teacher], { page: 2, total: 12, lastPage: 2 }))

    await renderPage('/cuentas/roles?q=pablo&rol=2&page=2')

    const first = listingRequests(calls)[0]

    expect(first.searchParams.get('q')).toBe('pablo')
    expect(first.searchParams.get('rol')).toBe('2')
    expect(first.searchParams.get('page')).toBe('2')
  })

  it('pagina en el servidor: muestra el rango y pide la página siguiente', async () => {
    const { calls } = stubBackend((request) => {
      const page = Number(new URL(request.url).searchParams.get('page'))

      return accountsResponse([page === 1 ? teacher : withoutRole], { page, total: 12, lastPage: 2 })
    })

    await renderPage()

    expect(screen.getByText('1–10 de 12 cuentas')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Siguiente/ }))

    await waitFor(() => expect(listingRequests(calls).at(-1)?.searchParams.get('page')).toBe('2'))
    await waitFor(() => expect(table().getByText('Laura Mendoza Rivas')).toBeInTheDocument())
  })

  it('sin coincidencias ofrece limpiar los filtros', async () => {
    stubBackend(() => accountsResponse([], { total: 0 }))

    renderWithRouter(<ModificarRolPage />, { route: '/cuentas/roles?q=zzz' })

    expect(await screen.findByText('Ninguna cuenta coincide')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Limpiar filtros' })).toBeInTheDocument()
  })

  it('un error del listado se informa con la opción de reintentar', async () => {
    mockApiWith((request) =>
      userMatchers.roles(request.url)
        ? { body: { data: roles } }
        : { status: 500, body: { message: 'Error del servidor.' } }
    )

    renderWithRouter(<ModificarRolPage />, { route: '/cuentas/roles' })

    expect(await screen.findByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })
})
