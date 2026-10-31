import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { faultTypes, records, stubRiskCenterApi } from '@/test/riskCenterFixtures'
import { RiskCenterPage } from './RiskCenterPage'

const EDIT_OR_DELETE = /editar|eliminar|borrar|rectificar|modificar/i

function LocationProbe() {
  const { pathname, search } = useLocation()

  return <output data-testid="location">{`${pathname}${search}`}</output>
}

function renderPage(route = '/central-riesgo') {
  return renderWithRouter(
    <>
      <RiskCenterPage />
      <LocationProbe />
    </>,
    { route, path: '/central-riesgo/:id?' }
  )
}

const location = () => screen.getByTestId('location').textContent

async function loadedTable() {
  return within(await screen.findByRole('table'))
}

/** Espera la tabla y, dentro de ella, un texto: la lista móvil repite los mismos textos fuera de la tabla. */
async function findInTable(text: string) {
  return within(await screen.findByRole('table')).findByText(text)
}

function listRequests(calls: { method: string; url: string }[]) {
  return calls.filter((call) => call.method === 'GET' && /\/central-riesgo\?/.test(call.url))
}

/** Cuerpos de la lista de los 25 registros que ocupan tres páginas. */
function manyRecords() {
  return Array.from({ length: 25 }, (_, index) => ({
    ...records[0],
    id_cr_estudiante: 9000 + index,
    estudiante: { ...records[0].estudiante, nombre_completo: `Estudiante ${index + 1}` },
  }))
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('RiskCenterPage — listado (CA-1 y CA-5)', () => {
  it('muestra el título, el subtítulo y las filas del más reciente al más antiguo', async () => {
    stubRiskCenterApi()

    renderPage()

    expect(screen.getByRole('heading', { name: 'Central de Riesgo' })).toBeInTheDocument()
    expect(
      screen.getByText('Antecedentes confirmados por doble comprobación, de toda la institución. Solo lectura.')
    ).toBeInTheDocument()

    const table = await loadedTable()
    const rows = table.getAllByRole('row').slice(1)

    expect(table.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Estudiante',
      'Código SIS',
      'Tipo de falta',
      'Examen',
      'Registro',
      'Detalle',
    ])
    expect(rows).toHaveLength(3)
    expect(within(rows[0]).getByText('José Luis Pérez Núñez')).toBeInTheDocument()
    expect(within(rows[0]).getByText('202312345')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Suplantación de identidad')).toBeInTheDocument()
    expect(within(rows[0]).getByText('1er Parcial · Cálculo I')).toBeInTheDocument()
    expect(within(rows[0]).getByText('20/10/2026')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Lucía Salazar Terán')).toBeInTheDocument()
    expect(within(rows[1]).getByText('14/10/2026')).toBeInTheDocument()
    expect(within(rows[2]).getByText('12/06/2026')).toBeInTheDocument()
    expect(screen.getByText('1–3 de 3 registros')).toBeInTheDocument()
  })

  it('el tipo de falta se muestra con icono y texto, no solo con color', async () => {
    stubRiskCenterApi()

    renderPage()

    const badge = (await loadedTable()).getAllByText('Suplantación de identidad')[0].parentElement

    expect(badge?.querySelector('svg')).not.toBeNull()
  })

  it('la lista es de solo lectura: no ofrece editar ni eliminar', async () => {
    stubRiskCenterApi()

    renderPage()
    await loadedTable()

    expect(screen.queryByRole('button', { name: EDIT_OR_DELETE })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: EDIT_OR_DELETE })).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: /^Ver registro de/ })).toHaveLength(3)
  })

  it('en móvil la misma información sale como tarjetas', async () => {
    stubRiskCenterApi()

    renderPage()
    await loadedTable()

    const cards = within(screen.getByRole('list', { name: 'Registros' })).getAllByRole('listitem')

    expect(cards).toHaveLength(3)
    expect(within(cards[0]).getByText('José Luis Pérez Núñez')).toBeInTheDocument()
    expect(within(cards[0]).getByText(/202312345 · 20\/10\/2026/)).toBeInTheDocument()
    expect(within(cards[0]).getByText('Suplantación de identidad')).toBeInTheDocument()
    expect(within(cards[0]).getByText('1er Parcial · Cálculo I')).toBeInTheDocument()
    expect(within(cards[0]).getByRole('link')).toHaveAttribute('href', '/central-riesgo/9945')
  })

  it('muestra el error del servidor con la opción de reintentar', async () => {
    stubRiskCenterApi({ listFail: true })

    renderPage()

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar la Central de Riesgo.')
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })
})

describe('RiskCenterPage — búsqueda (CA-2)', () => {
  it('«jose» encuentra a José sin acentos y la búsqueda queda en la URL', async () => {
    const user = userEvent.setup()
    const api = stubRiskCenterApi()
    renderPage()
    await loadedTable()

    await user.type(screen.getByRole('searchbox', { name: 'Buscar por nombre o código SIS' }), 'jose')

    await waitFor(() => expect(location()).toBe('/central-riesgo?q=jose'))
    await waitFor(async () => expect((await loadedTable()).getAllByRole('row')).toHaveLength(3))

    const names = (await loadedTable()).getAllByRole('row').slice(1).map((row) => row.textContent)

    expect(names.every((text) => text?.includes('José Luis Pérez Núñez'))).toBe(true)
    expect(screen.queryByText('Lucía Salazar Terán')).not.toBeInTheDocument()
    expect(screen.getByText('1–2 de 2 registros')).toBeInTheDocument()
    // Espera a que termine de escribir: no pide una consulta por cada letra.
    const typedQueries = listRequests(api.calls).map((call) => new URL(call.url).searchParams.get('q'))

    expect(typedQueries).not.toContain('j')
    expect(typedQueries).not.toContain('jo')
    expect(typedQueries).not.toContain('jos')
    expect(typedQueries).toContain('jose')
  })

  it('también busca por código SIS', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage()
    await loadedTable()

    await user.type(screen.getByRole('searchbox'), '202216630')

    await waitFor(() => expect(screen.queryByText('José Luis Pérez Núñez')).not.toBeInTheDocument())
    expect(within(screen.getByRole('table')).getByText('Lucía Salazar Terán')).toBeInTheDocument()
    expect(screen.getByText('1–1 de 1 registros')).toBeInTheDocument()
  })

  it('una búsqueda en la URL se aplica al cargar y llena el campo', async () => {
    stubRiskCenterApi()

    renderPage('/central-riesgo?q=lucia')

    expect(await findInTable('Lucía Salazar Terán')).toBeInTheDocument()
    expect(screen.queryByText('José Luis Pérez Núñez')).not.toBeInTheDocument()
    expect(screen.getByRole('searchbox')).toHaveValue('lucia')
  })

  it('una respuesta atrasada no pisa a la búsqueda más nueva', async () => {
    const user = userEvent.setup()
    const pending: Array<{ q: string | null; respond: () => void }> = []
    const respondWith = (body: unknown) =>
      ({ ok: true, status: 200, headers: new Headers(), json: async () => body }) as Response

    vi.stubGlobal(
      'fetch',
      vi.fn((input: RequestInfo | URL) => {
        const url = new URL(String(input))

        if (url.pathname.endsWith('/tipos-falta')) {
          return Promise.resolve(respondWith({ data: faultTypes }))
        }

        const q = url.searchParams.get('q')
        const rows = q === 'jose' ? [records[0], records[2]] : q === 'jose perez' ? [records[2]] : records

        return new Promise<Response>((resolve) => {
          const body = {
            data: rows,
            meta: { current_page: 1, last_page: 1, per_page: 10, total: rows.length },
          }
          const entry = { q, respond: () => resolve(respondWith(body)) }

          pending.push(entry)

          if (q === null) entry.respond()
        })
      })
    )

    renderPage()
    await loadedTable()

    const search = screen.getByRole('searchbox')

    await user.type(search, 'jose')
    await waitFor(() => expect(pending.some((entry) => entry.q === 'jose')).toBe(true))
    await user.type(search, ' perez')
    await waitFor(() => expect(pending.some((entry) => entry.q === 'jose perez')).toBe(true))

    // La más nueva responde primero y la vieja llega tarde.
    pending.find((entry) => entry.q === 'jose perez')?.respond()
    await waitFor(() => expect(screen.getByText('1–1 de 1 registros')).toBeInTheDocument())

    pending.find((entry) => entry.q === 'jose')?.respond()
    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(screen.getByText('1–1 de 1 registros')).toBeInTheDocument()
    expect(screen.queryByText('1–2 de 2 registros')).not.toBeInTheDocument()
  })
})

describe('RiskCenterPage — filtro por tipo (CA-3)', () => {
  it('elegir un tipo filtra el listado y lo deja en la URL', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage()
    await loadedTable()

    await user.click(screen.getByRole('combobox', { name: 'Tipo de falta' }))
    await user.click(await screen.findByRole('option', { name: 'Suplantación de identidad' }))

    await waitFor(() => expect(location()).toBe('/central-riesgo?tipo=2'))
    await waitFor(() => expect(screen.getByText('1–1 de 1 registros')).toBeInTheDocument())
    expect(screen.queryByText('Lucía Salazar Terán')).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tipo de falta' })).toHaveTextContent('Suplantación de identidad')
  })

  it('el filtro sobrevive a la recarga: se lee de la URL', async () => {
    stubRiskCenterApi()

    renderPage('/central-riesgo?tipo=3')

    expect(await findInTable('Lucía Salazar Terán')).toBeInTheDocument()
    expect(screen.queryByText('José Luis Pérez Núñez')).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tipo de falta' })).toHaveTextContent('Material no permitido')
  })

  it('«Todos los tipos» quita el filtro de la URL', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage('/central-riesgo?tipo=3')
    await findInTable('Lucía Salazar Terán')

    await user.click(screen.getByRole('combobox', { name: 'Tipo de falta' }))
    await user.click(await screen.findByRole('option', { name: 'Todos los tipos' }))

    await waitFor(() => expect(location()).toBe('/central-riesgo'))
    await waitFor(() => expect(screen.getByText('1–3 de 3 registros')).toBeInTheDocument())
  })

  it('las opciones son los tipos reales del catálogo', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage()
    await loadedTable()

    await user.click(screen.getByRole('combobox', { name: 'Tipo de falta' }))

    expect((await screen.findAllByRole('option')).map((option) => option.textContent)).toEqual([
      'Todos los tipos',
      'Copia en examen',
      'Suplantación de identidad',
      'Material no permitido',
    ])
  })

  it('si el catálogo de tipos falla, el filtro se deshabilita y el listado sigue funcionando', async () => {
    stubRiskCenterApi({ typesFail: true })

    renderPage()

    expect(await findInTable('Lucía Salazar Terán')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tipo de falta' })).toBeDisabled()
  })
})

describe('RiskCenterPage — valores inválidos en la URL', () => {
  it('cae a los valores por defecto sin avisar y reemplaza la URL', async () => {
    stubRiskCenterApi()

    renderPage(`/central-riesgo?tipo=abc&page=0&q=${'x'.repeat(60)}`)

    expect(await findInTable('Lucía Salazar Terán')).toBeInTheDocument()
    await waitFor(() => expect(location()).toBe('/central-riesgo'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('un tipo que no está en el catálogo se quita', async () => {
    stubRiskCenterApi()

    renderPage('/central-riesgo?tipo=99&q=jose')

    await waitFor(() => expect(location()).toBe('/central-riesgo?q=jose'))
    expect(await screen.findByText('1–2 de 2 registros')).toBeInTheDocument()
  })

  it('un page=1 redundante se limpia', async () => {
    stubRiskCenterApi()

    renderPage('/central-riesgo?page=1')

    await waitFor(() => expect(location()).toBe('/central-riesgo'))
  })
})

describe('RiskCenterPage — paginación', () => {
  it('pagina de a diez y cambia de página con la URL', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi({ records: manyRecords() })
    renderPage()
    await loadedTable()

    expect(screen.getByText('1–10 de 25 registros')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /siguiente/i }))

    await waitFor(() => expect(location()).toBe('/central-riesgo?page=2'))
    await waitFor(() => expect(screen.getByText('11–20 de 25 registros')).toBeInTheDocument())

    await user.click(screen.getByRole('button', { name: /anterior/i }))

    await waitFor(() => expect(location()).toBe('/central-riesgo'))
  })

  it('una página fuera de rango vuelve a la última que existe', async () => {
    stubRiskCenterApi({ records: manyRecords() })

    renderPage('/central-riesgo?page=9')

    await waitFor(() => expect(location()).toBe('/central-riesgo?page=3'))
    expect(await screen.findByText('21–25 de 25 registros')).toBeInTheDocument()
  })

  it('cambiar el filtro vuelve a la primera página', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi({ records: manyRecords() })
    renderPage('/central-riesgo?page=2')
    await screen.findByText('11–20 de 25 registros')

    await user.type(screen.getByRole('searchbox'), 'Estudiante')

    await waitFor(() => expect(location()).toBe('/central-riesgo?q=Estudiante'))
  })
})

describe('RiskCenterPage — sin resultados y sin registros (CA-6)', () => {
  it('una búsqueda sin coincidencias explica y ofrece limpiar los filtros', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage()
    await loadedTable()

    await user.type(screen.getByRole('searchbox'), 'zzz')

    expect(await screen.findByText('No hay registros que coincidan')).toBeInTheDocument()
    expect(screen.getByText('Pruebe con otro nombre o código, o quite el filtro de tipo.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(location()).toBe('/central-riesgo'))
    expect(screen.getByRole('searchbox')).toHaveValue('')
    expect(await findInTable('Lucía Salazar Terán')).toBeInTheDocument()
    expect(screen.queryByText('No hay registros que coincidan')).not.toBeInTheDocument()
  })

  it('Limpiar filtros también quita el tipo elegido', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage('/central-riesgo?tipo=1&q=lucia')

    await user.click(await screen.findByRole('button', { name: 'Limpiar filtros' }))

    await waitFor(() => expect(location()).toBe('/central-riesgo'))
    expect(await screen.findByText('1–3 de 3 registros')).toBeInTheDocument()
  })

  it('sin ningún registro en la central muestra su propio mensaje y no ofrece limpiar', async () => {
    stubRiskCenterApi({ records: [] })

    renderPage()

    expect(await screen.findByText('Aún no hay registros en la Central de Riesgo')).toBeInTheDocument()
    expect(screen.queryByText('No hay registros que coincidan')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Limpiar filtros' })).not.toBeInTheDocument()
  })
})

describe('RiskCenterPage — detalle (CA-4 y CA-5)', () => {
  it('«Ver» abre el panel con el detalle y conserva los filtros en la URL', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage('/central-riesgo?q=jose')
    const table = await loadedTable()

    await user.click(table.getAllByRole('link', { name: 'Ver registro de José Luis Pérez Núñez' })[0])

    const dialog = await screen.findByRole('dialog')

    expect(location()).toBe('/central-riesgo/9945?q=jose')
    expect(await within(dialog).findByText('Confirmado con el carnet original en la puerta.')).toBeInTheDocument()
  })

  it('un enlace directo al detalle muestra el listado con el panel abierto', async () => {
    stubRiskCenterApi()

    renderPage('/central-riesgo/9945?tipo=2')

    const dialog = await screen.findByRole('dialog', { name: 'Registro de la Central de Riesgo' })

    expect(await within(dialog).findByText('Daniela Ferrufino Soliz · 20/10 10:05')).toBeInTheDocument()
    expect(within(dialog).getByText('Marcelo Quiroga Andrade · 20/10 10:21')).toBeInTheDocument()
    // El listado está detrás, con el filtro de la URL.
    expect(await screen.findAllByText('José Luis Pérez Núñez', {}, { timeout: 2000 })).not.toHaveLength(0)
    expect(location()).toBe('/central-riesgo/9945?tipo=2')
  })

  it('cerrar el panel vuelve a /central-riesgo conservando los filtros', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage('/central-riesgo/9945?q=jose&tipo=2&page=1')
    const dialog = await screen.findByRole('dialog')
    await within(dialog).findByText('Confirmado con el carnet original en la puerta.')

    await user.click(within(dialog).getByRole('button', { name: 'Cerrar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(location()).toBe('/central-riesgo?q=jose&tipo=2')
    expect(screen.getByRole('searchbox')).toHaveValue('jose')
  })

  it('Escape también cierra el panel', async () => {
    const user = userEvent.setup()
    stubRiskCenterApi()
    renderPage('/central-riesgo/9945')
    await screen.findByRole('dialog')

    await user.keyboard('{Escape}')

    await waitFor(() => expect(location()).toBe('/central-riesgo'))
  })

  it('el detalle es de solo lectura: sin editar ni eliminar y con el aviso del pie', async () => {
    stubRiskCenterApi()
    renderPage('/central-riesgo/9945')

    const dialog = await screen.findByRole('dialog')
    await within(dialog).findByText('Confirmado con el carnet original en la puerta.')

    expect(within(dialog).queryByRole('button', { name: EDIT_OR_DELETE })).not.toBeInTheDocument()
    expect(within(dialog).queryByRole('link', { name: EDIT_OR_DELETE })).not.toBeInTheDocument()
    expect(within(dialog).getByText('Los registros no se editan ni se eliminan.')).toBeInTheDocument()
    expect(within(dialog).getByText('Solo lectura')).toBeInTheDocument()
  })

  it('un registro inexistente muestra «Registro no encontrado» sin romper el listado', async () => {
    stubRiskCenterApi()
    renderPage('/central-riesgo/123')

    const dialog = await screen.findByRole('dialog')

    expect(await within(dialog).findByText('Registro no encontrado')).toBeInTheDocument()
    expect(location()).toBe('/central-riesgo/123')
  })

  it('un id que no es un entero positivo vuelve al listado con los mismos filtros', async () => {
    stubRiskCenterApi()

    renderPage('/central-riesgo/abc?q=jose')

    await waitFor(() => expect(location()).toBe('/central-riesgo?q=jose'))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})

describe('RiskCenterPage — migas de pan', () => {
  it('el Docente la ve bajo «Central de riesgo › Registros»', async () => {
    stubRiskCenterApi()

    renderPage()
    await loadedTable()

    const breadcrumb = within(screen.getByRole('navigation', { name: 'breadcrumb' }))

    expect(breadcrumb.getByText('Central de riesgo')).toBeInTheDocument()
    expect(breadcrumb.getByText('Registros')).toBeInTheDocument()
  })
})
