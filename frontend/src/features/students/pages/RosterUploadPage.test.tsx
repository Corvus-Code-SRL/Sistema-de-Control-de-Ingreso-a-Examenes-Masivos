import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { RosterUploadPage } from './RosterUploadPage'
import { makeForeignGroup, makeGroup, makeGroupWithoutRoster } from '@/test/fixtures'
import { stubGroupApi } from '@/test/groupStudentFixtures'
import { renderWithRouter } from '@/test/render'

function renderPage(route = '/grupos/300/nomina') {
  return renderWithRouter(<RosterUploadPage />, { route, path: '/grupos/:idGrupo/nomina' })
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('RosterUploadPage (vista interina de HU-021)', () => {
  it('monta el asistente de carga para un grupo propio, activo y del período vigente', async () => {
    stubGroupApi({ group: makeGroupWithoutRoster() })

    renderPage()

    expect(await screen.findByText('Carga de nómina')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Cargar nómina' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al grupo' })).toHaveAttribute('href', '/grupos/300')
  })

  it('no ofrece la carga en un grupo de un período anterior', async () => {
    stubGroupApi({ group: makeGroupWithoutRoster(), esPeriodoActivo: false })

    renderPage()

    expect(await screen.findByText('La nómina de este grupo ya no se puede cargar')).toBeInTheDocument()
    expect(screen.queryByText('Carga de nómina')).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'Volver al grupo' })[0]).toHaveAttribute(
      'href',
      '/grupos/300'
    )
  })

  it('no ofrece la carga en un grupo inactivo', async () => {
    stubGroupApi({ group: makeGroup({ activo: false }) })

    renderPage('/grupos/100/nomina')

    expect(await screen.findByText('La nómina de este grupo ya no se puede cargar')).toBeInTheDocument()
  })

  it('niega la carga en un grupo de otro docente', async () => {
    stubGroupApi({ group: makeForeignGroup() })

    renderPage('/grupos/200/nomina')

    expect(await screen.findByText('Este curso no es suyo')).toBeInTheDocument()
    expect(screen.queryByText('Carga de nómina')).not.toBeInTheDocument()
  })

  it('distingue un grupo inexistente', async () => {
    stubGroupApi({ detailStatus: 404 })

    renderPage('/grupos/999/nomina')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
  })

  it('un id inválido muestra «El grupo no existe» sin consultar', async () => {
    const api = stubGroupApi()

    renderPage('/grupos/abc/nomina')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
    expect(api.calls).toHaveLength(0)
  })
})
