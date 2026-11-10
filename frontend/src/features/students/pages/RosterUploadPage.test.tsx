import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { RosterUploadPage } from './RosterUploadPage'
import type { Group } from '@/features/groups'
import {
  groupDetailResponse,
  makeForeignGroup,
  makeGroup,
  makeGroupWithoutRoster,
  makeSubject,
  subjectCatalogResponse,
  subjectGroupsResponse,
} from '@/test/fixtures'
import { matchers, mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'

const subject = makeSubject()

function LocationProbe() {
  const { pathname, search } = useLocation()

  return <output data-testid="location">{`${pathname}${search}`}</output>
}

const location = () => screen.getByTestId('location').textContent

interface ApiOptions {
  /** Grupos que dicta el docente (lo que devuelve la consulta de sus grupos). */
  myGroups?: Group[]
  /** Grupo que devuelve el detalle; por defecto, el elegido entre `myGroups`. */
  detail?: Group
  esPeriodoActivo?: boolean
  detailStatus?: number
}

/** Simula los grupos del docente y el detalle del grupo elegido; devuelve las llamadas. */
function stubApi({ myGroups = [makeGroupWithoutRoster()], detail, esPeriodoActivo = true, detailStatus }: ApiOptions = {}) {
  return mockApiWith(({ url }) => {
    if (matchers.catalog(url)) return { body: subjectCatalogResponse([subject]) }

    if (matchers.groupsOfPair(url)) return { body: subjectGroupsResponse(subject, myGroups) }

    const match = /\/grupos\/(\d+)$/.exec(new URL(url, 'http://localhost').pathname)

    if (match) {
      if (detailStatus) return { status: detailStatus, body: { message: 'No encontrado.' } }

      const group = detail ?? myGroups.find((item) => item.id_grupo === Number(match[1])) ?? makeGroup()

      return { body: groupDetailResponse(group, subject, esPeriodoActivo) }
    }

    return undefined
  })
}

function renderPage(route = '/cargar-nomina') {
  return renderWithRouter(
    <>
      <RosterUploadPage />
      <LocationProbe />
    </>,
    { route, path: '/cargar-nomina' }
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('RosterUploadPage (HU-021, independiente)', () => {
  it('desde el menú, el docente elige el grupo y se monta la carga', async () => {
    const user = userEvent.setup()
    stubApi({ myGroups: [makeGroupWithoutRoster(), makeGroup({ id_grupo: 101, num_grupo: '4' })] })

    renderPage()

    const picker = await screen.findByRole('combobox', { name: 'Grupo' })
    expect(screen.queryByText('Carga de nómina')).not.toBeInTheDocument()

    await user.selectOptions(picker, '101')

    expect(await screen.findByText('Carga de nómina')).toBeInTheDocument()
    expect(location()).toBe('/cargar-nomina?grupo=101')
    expect(screen.getByRole('option', { name: `${subject.nombre} · Grupo 4 · ${subject.carrera.nombre}` }))
      .toBeInTheDocument()
  })

  it('si el docente dicta un solo grupo, viene elegido', async () => {
    stubApi({ myGroups: [makeGroupWithoutRoster()] })

    renderPage()

    expect(await screen.findByText('Carga de nómina')).toBeInTheDocument()
    await waitFor(() => expect(location()).toBe('/cargar-nomina?grupo=300'))
    expect(screen.getByRole('combobox', { name: 'Grupo' })).toHaveValue('300')
  })

  it('?grupo= deja el grupo preseleccionado', async () => {
    stubApi({ myGroups: [makeGroupWithoutRoster(), makeGroup({ id_grupo: 101, num_grupo: '4' })] })

    renderPage('/cargar-nomina?grupo=101')

    expect(await screen.findByText('Carga de nómina')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Grupo' })).toHaveValue('101')
  })

  it('al cambiar de grupo la carga empieza de nuevo para el grupo elegido', async () => {
    const user = userEvent.setup()
    const api = stubApi({ myGroups: [makeGroupWithoutRoster(), makeGroup({ id_grupo: 101, num_grupo: '4' })] })

    renderPage('/cargar-nomina?grupo=300')
    await screen.findByText('Carga de nómina')

    await user.selectOptions(screen.getByRole('combobox', { name: 'Grupo' }), '101')

    await waitFor(() => expect(location()).toBe('/cargar-nomina?grupo=101'))
    await waitFor(() => expect(api.calls.some((call) => call.url.endsWith('/grupos/101'))).toBe(true))
  })

  it('no ofrece los grupos inactivos ni los de otro docente', async () => {
    stubApi({
      myGroups: [
        makeGroupWithoutRoster(),
        makeGroup({ id_grupo: 101, num_grupo: '4' }),
        makeGroup({ id_grupo: 102, num_grupo: '5', activo: false }),
        makeForeignGroup(),
      ],
    })

    renderPage()

    await screen.findByRole('combobox', { name: 'Grupo' })

    // «Seleccione un grupo» y los dos grupos activos propios.
    expect(screen.getAllByRole('option')).toHaveLength(3)
  })

  it('sin grupos para cargar lo dice', async () => {
    stubApi({ myGroups: [] })

    renderPage()

    expect(await screen.findByText('No tiene grupos para cargar')).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Grupo' })).not.toBeInTheDocument()
  })

  it('no ofrece la carga en un grupo de un período anterior', async () => {
    stubApi({ esPeriodoActivo: false })

    renderPage('/cargar-nomina?grupo=300')

    expect(await screen.findByText('La nómina de este grupo ya no se puede cargar')).toBeInTheDocument()
    expect(screen.queryByText('Carga de nómina')).not.toBeInTheDocument()
  })

  it('niega la carga en un grupo de otro docente', async () => {
    stubApi({ detail: makeForeignGroup() })

    renderPage('/cargar-nomina?grupo=200')

    expect(await screen.findByText('Este curso no es suyo')).toBeInTheDocument()
    expect(screen.queryByText('Carga de nómina')).not.toBeInTheDocument()
  })

  it('distingue un grupo inexistente', async () => {
    stubApi({ detailStatus: 404 })

    renderPage('/cargar-nomina?grupo=999')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
  })

  it('un id inválido muestra «El grupo no existe» sin pedir el detalle', async () => {
    const api = stubApi()

    renderPage('/cargar-nomina?grupo=abc')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
    expect(api.calls.some((call) => /\/grupos\/[^/]+$/.test(new URL(call.url, 'http://localhost').pathname)))
      .toBe(false)
  })
})
