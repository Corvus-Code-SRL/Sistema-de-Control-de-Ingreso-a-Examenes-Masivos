import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { entries, manyEntries, stubAuditLogApi } from '@/test/auditLogFixtures'
import { renderWithRouter } from '@/test/render'
import { AuditLogPage } from './AuditLogPage'

const EDIT_OR_DELETE = /editar|eliminar|borrar|rectificar|modificar registro/i

function LocationProbe() {
  const { pathname, search } = useLocation()

  return <output data-testid="location">{`${pathname}${search}`}</output>
}

function renderPage(route = '/bitacora') {
  return renderWithRouter(
    <>
      <AuditLogPage />
      <LocationProbe />
    </>,
    { route, path: '/bitacora' }
  )
}

const location = () => screen.getByTestId('location').textContent

async function loadedTable() {
  return within(await screen.findByRole('table'))
}

function listRequests(calls: { method: string; url: string }[]) {
  return calls.filter((call) => call.method === 'GET' && /\/bitacora\?/.test(call.url))
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('AuditLogPage — listado (CA-1, CA-2 y CA-9)', () => {
  it('muestra fecha, usuario con nombre y SIS, acción, módulo y registro, de lo más reciente a lo más antiguo', async () => {
    stubAuditLogApi()

    renderPage()

    expect(screen.getByRole('heading', { name: 'Bitácora' })).toBeInTheDocument()

    const table = await loadedTable()
    const rows = table.getAllByRole('row').slice(1)

    expect(table.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Fecha y hora',
      'Usuario',
      'Acción',
      'Módulo',
      'Registro afectado',
      'Detalle',
    ])
    expect(rows).toHaveLength(5)
    expect(within(rows[0]).getByText('20/10/2026 10:21')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Marcelo Quiroga Andrade')).toBeInTheDocument()
    expect(within(rows[0]).getByText('10452')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Modificar')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Grupos')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Grupo #7')).toBeInTheDocument()
    expect(screen.getByText('1–5 de 5 operaciones')).toBeInTheDocument()
  })

  it('muestra la hora de La Paz aunque el instante venga en UTC (23:30 del 19/10)', async () => {
    stubAuditLogApi()

    renderPage()

    const rows = (await loadedTable()).getAllByRole('row').slice(1)

    expect(within(rows[1]).getByText('19/10/2026 23:30')).toBeInTheDocument()
  })

  it('con 120 operaciones muestra 50 por página y avanza con «Siguiente»', async () => {
    const user = userEvent.setup()
    const api = stubAuditLogApi({ entries: manyEntries(120) })

    renderPage()

    expect((await loadedTable()).getAllByRole('row').slice(1)).toHaveLength(50)
    expect(screen.getByText('1–50 de 120 operaciones')).toBeInTheDocument()
    expect(screen.getByText('Página 1 de 3')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Siguiente' }))

    await waitFor(() => expect(location()).toBe('/bitacora?page=2'))
    await waitFor(() => expect(screen.getByText('51–100 de 120 operaciones')).toBeInTheDocument())
    expect(listRequests(api.calls).some((call) => call.url.includes('page=2'))).toBe(true)
  })

  it('«Automático» y «Sistema» se muestran con icono y texto', async () => {
    stubAuditLogApi()

    renderPage()

    const rows = (await loadedTable()).getAllByRole('row').slice(1)
    const automatic = within(rows[2]).getByText('Automático')
    const system = within(rows[3]).getByText('Sistema')

    expect(automatic.querySelector('svg')).not.toBeNull()
    expect(system.querySelector('svg')).not.toBeNull()
  })

  it('en móvil la misma información sale como tarjetas', async () => {
    stubAuditLogApi()

    renderPage()
    await loadedTable()

    const cards = within(screen.getByRole('list', { name: 'Operaciones' })).getAllByRole('listitem')

    expect(cards).toHaveLength(5)
    expect(within(cards[0]).getByText('Marcelo Quiroga Andrade')).toBeInTheDocument()
    expect(within(cards[0]).getByText('Modificar · Grupos')).toBeInTheDocument()
    expect(within(cards[0]).getByRole('link')).toHaveAttribute('href', '/bitacora?detalle=130')
  })

  it('muestra el error del servidor con la opción de reintentar', async () => {
    stubAuditLogApi({ listFail: true })

    renderPage()

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar la bitácora.')
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })

  it('es de solo lectura: no ofrece editar, eliminar ni seleccionar', async () => {
    stubAuditLogApi()

    renderPage()
    await loadedTable()

    expect(screen.queryByRole('button', { name: EDIT_OR_DELETE })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: EDIT_OR_DELETE })).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: /^Ver detalle de la operación/ })).toHaveLength(10)
  })
})

describe('AuditLogPage — filtro por usuario (CA-3)', () => {
  it('«marcelo» filtra por nombre y queda en la URL', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage()
    await loadedTable()

    await user.type(screen.getByRole('searchbox', { name: /Buscar usuario/ }), 'marcelo')

    await waitFor(() => expect(location()).toBe('/bitacora?q=marcelo'))
    await waitFor(async () => expect((await loadedTable()).getAllByRole('row')).toHaveLength(2))

    expect(screen.getByText('1–1 de 1 operaciones')).toBeInTheDocument()
    expect(screen.queryByText('Daniela Ferrufino Soliz')).not.toBeInTheDocument()
  })

  it('«10452» filtra por código SIS', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage()
    await loadedTable()

    await user.type(screen.getByRole('searchbox', { name: /Buscar usuario/ }), '10452')

    await waitFor(() => expect(location()).toBe('/bitacora?q=10452'))
    await waitFor(async () => expect((await loadedTable()).getAllByRole('row')).toHaveLength(2))

    expect((await loadedTable()).getByText('Marcelo Quiroga Andrade')).toBeInTheDocument()
  })

  it('no manda una petición por cada tecla: espera una pausa', async () => {
    const user = userEvent.setup()
    const api = stubAuditLogApi()
    renderPage()
    await loadedTable()

    await user.type(screen.getByRole('searchbox', { name: /Buscar usuario/ }), 'marcelo')
    await waitFor(() => expect(location()).toBe('/bitacora?q=marcelo'))

    const withTerm = listRequests(api.calls).filter((call) => call.url.includes('q='))

    expect(withTerm).toHaveLength(1)
  })
})

describe('AuditLogPage — acción y módulo (CA-4 y CA-5)', () => {
  it('«Modificar» muestra solo operaciones de modificación', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage()
    await loadedTable()

    await user.click(screen.getByRole('combobox', { name: 'Acción' }))
    await user.click(await screen.findByRole('option', { name: 'Modificar' }))

    await waitFor(() => expect(location()).toBe('/bitacora?accion=MODIFICAR'))
    await waitFor(() => expect(screen.getByText('1–1 de 1 operaciones')).toBeInTheDocument())
    expect(screen.getByRole('combobox', { name: 'Acción' })).toHaveTextContent('Modificar')
  })

  it('«Exámenes» muestra solo operaciones de ese módulo', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage()
    await loadedTable()

    await user.click(screen.getByRole('combobox', { name: 'Módulo' }))
    await user.click(await screen.findByRole('option', { name: 'Exámenes' }))

    await waitFor(() => expect(location()).toBe('/bitacora?modulo=examenes'))
    await waitFor(() => expect(screen.getByText('1–2 de 2 operaciones')).toBeInTheDocument())
  })

  it('«Todas las acciones» quita el filtro de la URL', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage('/bitacora?accion=MODIFICAR')
    await loadedTable()

    await user.click(screen.getByRole('combobox', { name: 'Acción' }))
    await user.click(await screen.findByRole('option', { name: 'Todas las acciones' }))

    await waitFor(() => expect(location()).toBe('/bitacora'))
  })

  it('una acción de la URL que no existe en el catálogo se quita sin avisar', async () => {
    stubAuditLogApi()
    renderPage('/bitacora?accion=INEXISTENTE')

    await waitFor(() => expect(location()).toBe('/bitacora'))
    expect(await loadedTable()).toBeTruthy()
  })

  it('si las opciones no cargan, el listado sigue funcionando y los selectores se deshabilitan', async () => {
    stubAuditLogApi({ optionsFail: true })
    renderPage()

    expect((await loadedTable()).getAllByRole('row').slice(1)).toHaveLength(5)
    expect(screen.getByRole('combobox', { name: 'Acción' })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Módulo' })).toBeDisabled()
  })
})

describe('AuditLogPage — rango de fechas (CA-6)', () => {
  it('el día se interpreta en La Paz: 23:30 del 19/10 entra en el 19, no en el 20', async () => {
    const api = stubAuditLogApi()

    renderPage('/bitacora?desde=2026-10-19&hasta=2026-10-19')

    const rows = (await loadedTable()).getAllByRole('row').slice(1)

    expect(rows).toHaveLength(2)
    expect(within(rows[0]).getByText('19/10/2026 23:30')).toBeInTheDocument()
    expect(within(rows[1]).getByText('19/10/2026 08:00')).toBeInTheDocument()

    const request = listRequests(api.calls)[0].url

    expect(request).toContain('desde=2026-10-19')
    expect(request).toContain('hasta=2026-10-19')
  })

  it('elegir las fechas las guarda en la URL', async () => {
    stubAuditLogApi()
    renderPage()
    await loadedTable()

    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-10-19' } })
    await waitFor(() => expect(location()).toBe('/bitacora?desde=2026-10-19'))

    fireEvent.change(screen.getByLabelText('Hasta'), { target: { value: '2026-10-20' } })
    await waitFor(() => expect(location()).toBe('/bitacora?desde=2026-10-19&hasta=2026-10-20'))
  })

  it('«Desde» posterior a «Hasta» muestra el mensaje y no consulta al servidor', async () => {
    const api = stubAuditLogApi()

    renderPage('/bitacora?desde=2026-10-20&hasta=2026-10-19')

    expect(await screen.findByRole('alert')).toHaveTextContent('La fecha de inicio debe ser anterior a la fecha de fin.')
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(listRequests(api.calls)).toHaveLength(0)
  })

  it('corregir el rango vuelve a mostrar el listado', async () => {
    stubAuditLogApi()
    renderPage('/bitacora?desde=2026-10-20&hasta=2026-10-19')
    await screen.findByRole('alert')

    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-10-19' } })

    expect(await loadedTable()).toBeTruthy()
    expect(screen.queryByText('La fecha de inicio debe ser anterior a la fecha de fin.')).not.toBeInTheDocument()
  })

  it('una fecha que no existe en el calendario se descarta', async () => {
    stubAuditLogApi()
    renderPage('/bitacora?desde=2026-02-31')

    await waitFor(() => expect(location()).toBe('/bitacora'))
  })
})

describe('AuditLogPage — filtros en la URL (CA-8)', () => {
  it('los filtros sobreviven a la recarga: se leen de la URL', async () => {
    stubAuditLogApi()

    renderPage('/bitacora?q=marcelo&accion=MODIFICAR&modulo=grupos&desde=2026-10-01&hasta=2026-10-31')

    const table = await loadedTable()

    expect(table.getAllByRole('row').slice(1)).toHaveLength(1)
    expect(screen.getByRole('searchbox', { name: /Buscar usuario/ })).toHaveValue('marcelo')
    expect(screen.getByRole('combobox', { name: 'Acción' })).toHaveTextContent('Modificar')
    expect(screen.getByRole('combobox', { name: 'Módulo' })).toHaveTextContent('Grupos')
    expect(screen.getByLabelText('Desde')).toHaveValue('2026-10-01')
    expect(screen.getByLabelText('Hasta')).toHaveValue('2026-10-31')
  })

  it('una página inválida en la URL vuelve a la primera', async () => {
    stubAuditLogApi()
    renderPage('/bitacora?page=abc')

    await waitFor(() => expect(location()).toBe('/bitacora'))
  })

  it('una página fuera de rango vuelve a la última que existe', async () => {
    stubAuditLogApi({ entries: manyEntries(120) })
    renderPage('/bitacora?page=9')

    await waitFor(() => expect(location()).toBe('/bitacora?page=3'))
    await waitFor(() => expect(screen.getByText('101–120 de 120 operaciones')).toBeInTheDocument())
  })
})

describe('AuditLogPage — sin resultados (CA-10)', () => {
  it('sin coincidencias avisa y «Limpiar filtros» restablece la URL', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage('/bitacora?q=zzzz&accion=MODIFICAR')

    expect(await screen.findByText('No hay operaciones que coincidan')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(location()).toBe('/bitacora'))
    expect((await loadedTable()).getAllByRole('row').slice(1)).toHaveLength(5)
    expect(screen.getByRole('searchbox', { name: /Buscar usuario/ })).toHaveValue('')
  })

  it('«Limpiar filtros» conserva el detalle abierto', async () => {
    stubAuditLogApi()
    renderPage('/bitacora?q=zzzz&detalle=130')

    // El panel es modal y deja el resto inerte para el puntero; el clic se dispara directo.
    fireEvent.click(await screen.findByRole('button', { name: 'Limpiar filtros', hidden: true }))

    await waitFor(() => expect(location()).toBe('/bitacora?detalle=130'))
  })

  it('una bitácora vacía sin filtros no ofrece limpiar nada', async () => {
    stubAuditLogApi({ entries: [] })
    renderPage()

    expect(await screen.findByText('Aún no hay operaciones registradas')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Limpiar filtros' })).not.toBeInTheDocument()
  })
})

describe('AuditLogPage — detalle (CA-7)', () => {
  it('«Ver» abre el panel con `?detalle=` y conserva los filtros', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage('/bitacora?modulo=grupos')
    await loadedTable()

    await user.click(screen.getAllByRole('link', { name: 'Ver detalle de la operación 130' })[0])

    await waitFor(() => expect(location()).toBe('/bitacora?modulo=grupos&detalle=130'))

    const panel = await screen.findByRole('dialog')

    expect(await within(panel).findByText('Número de grupo')).toBeInTheDocument()
    expect(within(panel).getByText('3')).toBeInTheDocument()
    expect(within(panel).getByText('5')).toBeInTheDocument()
  })

  it('cerrar el panel quita `detalle` y conserva los filtros', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()
    renderPage('/bitacora?modulo=grupos&detalle=130')

    const panel = await screen.findByRole('dialog')
    await within(panel).findByText('Número de grupo')

    await user.click(within(panel).getByRole('button', { name: 'Cerrar detalle' }))

    await waitFor(() => expect(location()).toBe('/bitacora?modulo=grupos'))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('mover un filtro con el panel abierto no pierde el detalle', async () => {
    stubAuditLogApi()
    renderPage('/bitacora?detalle=130')
    await screen.findByRole('dialog')

    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-10-01' } })

    await waitFor(() => expect(location()).toBe('/bitacora?detalle=130&desde=2026-10-01'))
  })
})
