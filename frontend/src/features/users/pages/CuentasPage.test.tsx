import { screen, waitForElementToBeRemoved, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { CuentasPage } from './CuentasPage'
import { mockApi, mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import {
  accountsResponse,
  administrator,
  assistant,
  makeAccount,
  roles,
  teacher,
  userMatchers,
} from '@/test/userFixtures'

const withoutRole = makeAccount()

async function renderPage() {
  const view = renderWithRouter(<CuentasPage />, { route: '/cuentas' })
  await waitForElementToBeRemoved(() => screen.queryByRole('status'))

  return view
}

/** La vista de escritorio: la tabla repite los datos que la lista móvil también dibuja. */
function table() {
  return within(screen.getByRole('table'))
}

describe('CuentasPage', () => {
  it('lista las cuentas reales con su rol vigente o el aviso de que no tienen', async () => {
    mockApiOnce({ body: accountsResponse([administrator, teacher, withoutRole]) })

    await renderPage()

    expect(table().getByText('Pablo Careaga Rojas')).toBeInTheDocument()
    expect(table().getByText('Docente')).toBeInTheDocument()
    expect(table().getByText('Sin rol')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Todas (3)' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Sin rol (1)' })).toBeInTheDocument()
  })

  it('enlaza cada cuenta con su detalle por su uuid', async () => {
    mockApiOnce({ body: accountsResponse([teacher]) })

    await renderPage()

    expect(table().getByRole('link', { name: 'Ver detalle' })).toHaveAttribute(
      'href',
      `/cuentas/${teacher.id_usuario}`
    )
  })

  it('solo ofrece asignar rol a las cuentas que no tienen uno', async () => {
    mockApiOnce({ body: accountsResponse([teacher, withoutRole]) })

    await renderPage()

    expect(table().getAllByRole('button', { name: 'Asignar rol' })).toHaveLength(1)
  })

  it('marca la cuenta propia y no le ofrece asignarse un rol', async () => {
    const ownWithoutRole = makeAccount({ id_usuario: administrator.id_usuario, rol: null })
    mockApiOnce({ body: accountsResponse([ownWithoutRole]) })

    await renderPage()

    expect(table().getByText('Su cuenta')).toBeInTheDocument()
    expect(table().queryByRole('button', { name: 'Asignar rol' })).not.toBeInTheDocument()
  })

  it('filtra las cuentas sin rol y por texto de búsqueda', async () => {
    mockApiOnce({ body: accountsResponse([teacher, assistant, withoutRole]) })

    await renderPage()

    await userEvent.click(screen.getByRole('tab', { name: 'Sin rol (1)' }))
    expect(table().getAllByRole('row')).toHaveLength(2)
    expect(table().getByText('Laura Mendoza Rivas')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('tab', { name: 'Todas (3)' }))
    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar cuentas' }), '202103377')

    expect(table().getByText('Mateo Quiroga Salinas')).toBeInTheDocument()
    expect(table().queryByText('Pablo Careaga Rojas')).not.toBeInTheDocument()
  })

  it('explica cuando la búsqueda no encuentra ninguna cuenta', async () => {
    mockApiOnce({ body: accountsResponse([teacher]) })

    await renderPage()

    await userEvent.type(screen.getByRole('searchbox', { name: 'Buscar cuentas' }), 'nadie')

    expect(screen.getByText('Ninguna cuenta coincide')).toBeInTheDocument()
  })

  it('distingue la ausencia de cuentas de un fallo del servidor', async () => {
    mockApiOnce({ body: accountsResponse([]) })

    const { unmount } = await renderPage()
    expect(screen.getByText('Aún no hay cuentas registradas')).toBeInTheDocument()
    unmount()

    mockApiOnce({ status: 500, invalidJson: true })

    await renderPage()
    expect(screen.getByText('No se pudo cargar la información')).toBeInTheDocument()
  })

  it('actualiza el listado y confirma después de asignar un rol', async () => {
    let assigned = false
    mockApi([
      { matches: userMatchers.roles, body: { data: roles } },
      { matches: userMatchers.assignRole, body: { data: roles[2] } },
      {
        matches: userMatchers.accounts,
        // La consulta posterior a la asignación ya refleja el rol nuevo.
        get body() {
          return accountsResponse([
            assigned ? { ...withoutRole, rol: { id_rol: 3, nombre_rol: 'Auxiliar' } } : withoutRole,
          ])
        },
      },
    ])

    await renderPage()

    await userEvent.click(table().getByRole('button', { name: 'Asignar rol' }))
    await userEvent.click(await screen.findByRole('radio', { name: /auxiliar/i }))
    assigned = true
    await userEvent.click(screen.getByRole('button', { name: 'Asignar rol' }))

    expect(await screen.findByText('Rol asignado')).toBeInTheDocument()
    expect(await table().findByText('Auxiliar')).toBeInTheDocument()
  })
})
