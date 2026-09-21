import { screen, waitForElementToBeRemoved } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { CuentaDetallePage } from './CuentaDetallePage'
import { mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import {
  administrator,
  detailResponse,
  makeAccount,
  makeDetail,
  teacher,
} from '@/test/userFixtures'

async function renderPage(userId: string) {
  renderWithRouter(<CuentaDetallePage />, {
    route: `/cuentas/${userId}`,
    path: '/cuentas/:idUsuario',
  })

  await waitForElementToBeRemoved(() => screen.queryByRole('status'))
}

const teacherHistory = [
  { id_rol: 2, nombre_rol: 'Docente', fecha_inicio: '2026-03-01T12:00:00Z', fecha_fin: null },
  {
    id_rol: 3,
    nombre_rol: 'Auxiliar',
    fecha_inicio: '2026-01-10T12:00:00Z',
    fecha_fin: '2026-03-01T12:00:00Z',
  },
]

describe('CuentaDetallePage', () => {
  it('muestra el rol vigente y el historial real de roles', async () => {
    mockApiOnce({ body: detailResponse(makeDetail(teacher, teacherHistory)) })

    await renderPage(teacher.id_usuario)

    expect(screen.getByRole('heading', { name: 'Pablo Careaga Rojas' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /cambiar rol/i })).toBeEnabled()

    const headers = screen.getAllByRole('columnheader').map((header) => header.textContent)
    expect(headers).toEqual(['Rol', 'Desde', 'Hasta'])

    expect(screen.getAllByRole('row')).toHaveLength(3)
    expect(screen.getByText('Vigente')).toBeInTheDocument()
    expect(screen.getByText('Auxiliar')).toBeInTheDocument()
  })

  it('no incluye acciones ni datos que pertenecen a otras historias', async () => {
    mockApiOnce({ body: detailResponse(makeDetail(teacher, teacherHistory)) })

    await renderPage(teacher.id_usuario)

    expect(screen.queryByRole('button', { name: /editar datos/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /deshabilitar/i })).not.toBeInTheDocument()
    expect(screen.queryByText(/bitácora/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/facultad/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/asignó/i)).not.toBeInTheDocument()
  })

  it('ofrece asignar rol a una cuenta que aún no tiene ninguno', async () => {
    mockApiOnce({ body: detailResponse(makeDetail(makeAccount())) })

    await renderPage(makeAccount().id_usuario)

    expect(screen.getByRole('button', { name: /asignar rol/i })).toBeEnabled()
    expect(screen.getByText(/no tiene ningún rol asignado/i)).toBeInTheDocument()
    expect(screen.getByText('Aún no hay registros en el historial de roles.')).toBeInTheDocument()
  })

  it('impide que el Administrador cambie su propio rol', async () => {
    const history = [
      { id_rol: 1, nombre_rol: 'Administrador', fecha_inicio: '2026-01-02T12:00:00Z', fecha_fin: null },
    ]
    mockApiOnce({ body: detailResponse(makeDetail(administrator, history)) })

    await renderPage(administrator.id_usuario)

    expect(screen.getByText('Su cuenta')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /cambiar rol/i })).toBeDisabled()
    expect(
      screen.getByText('No puede cambiar el rol de su propia cuenta. Pídalo a otro administrador.')
    ).toBeInTheDocument()
  })

  it('explica que la cuenta no existe', async () => {
    mockApiOnce({ status: 404, body: { message: 'No query results for model [App\\Models\\User].' } })

    await renderPage('00000000-0000-4000-8000-000000000099')

    expect(screen.getByText('La cuenta no existe')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ir a Cuentas' })).toHaveAttribute('href', '/cuentas')
  })
})
