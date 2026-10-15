import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { useLocation } from 'react-router-dom'
import { AdminSubjectAssignmentsPage } from './AdminSubjectAssignmentsPage'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'

const sistemas = { id_carrera: 3, nombre: 'Ingenieria de Sistemas', codigo: 'SIS', id_facultad: 1 }
const civil = { id_carrera: 4, nombre: 'Ingenieria Civil', codigo: 'CIV', id_facultad: 1 }
const economia = { id_carrera: 7, nombre: 'Economia', codigo: 'ECO', id_facultad: 2 }

const calculo = { id_materia: 10, nombre: 'Cálculo I', codigo: '2008057', descripcion: null, estado: 'ACTIVO' }
const algebra = { id_materia: 20, nombre: 'Álgebra Lineal', codigo: '2008060', descripcion: null, estado: 'ACTIVO' }
const topografia = { id_materia: 40, nombre: 'Topografia', codigo: '2008099', descripcion: null, estado: 'ACTIVO' }
const hidraulica = { id_materia: 50, nombre: 'Hidráulica', codigo: '2008100', descripcion: null, estado: 'ACTIVO' }

/** 1/2026: período anterior, solo de consulta. */
const periodo1 = { id_periodo: 5, etiqueta: '1/2026', nombre_periodo: 'Semestre 1', gestion: 2026, vigente: false }
/** 2/2026: el período vigente, el único donde se asigna. */
const periodo2 = { id_periodo: 6, etiqueta: '2/2026', nombre_periodo: 'Semestre 2', gestion: 2026, vigente: true }

function offering(career: typeof sistemas, subject: typeof calculo, period = periodo2, estado = 'ACTIVO') {
  return {
    id_carrera: career.id_carrera,
    id_materia: subject.id_materia,
    id_periodo: period.id_periodo,
    estado,
    carrera: career,
    materia: subject,
    periodo: period,
  }
}

/** Deja a la vista la URL actual para comprobar lo que la pantalla escribe en ella. */
function LocationSpy() {
  const { pathname, search } = useLocation()

  return <output data-testid="url">{`${pathname}${search}`}</output>
}

function renderPage(route: string) {
  return renderWithRouter(
    <>
      <AdminSubjectAssignmentsPage />
      <LocationSpy />
    </>,
    { route }
  )
}

function currentUrl() {
  return screen.getByTestId('url').textContent
}

interface BackendOptions {
  periods?: (typeof periodo1)[]
  periodsStatus?: number
}

/**
 * Backend de mentira con la misma forma que el real: las ofertas, las carreras del selector y las
 * materias asignables dependen del período; `assigned` simula lo creado por el POST.
 */
function stubBackend({ periods = [periodo2, periodo1], periodsStatus = 200 }: BackendOptions = {}) {
  const created: ReturnType<typeof offering>[] = []

  const baseOffers: Record<string, ReturnType<typeof offering>[]> = {
    '5': [offering(sistemas, calculo, periodo1), offering(civil, topografia, periodo1, 'INACTIVO')],
    '6': [offering(sistemas, algebra, periodo2), offering(civil, hidraulica, periodo2)],
  }

  const api = mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')
    const periodId = url.searchParams.get('id_periodo') ?? ''

    if (path === '/periodos') {
      return periodsStatus === 200
        ? { body: { data: periods, meta: { id_periodo_activo: 6 } } }
        : { status: periodsStatus, body: { message: 'Error interno del servidor.' } }
    }

    if (path === '/administracion/carreras') {
      return { body: { data: [civil, economia, sistemas] } }
    }

    if (path === '/administracion/carreras/3/materias-asignables') {
      const offered = [...(baseOffers[periodId] ?? []), ...created.filter((item) => String(item.id_periodo) === periodId)]
        .filter((item) => item.id_carrera === 3)
        .map((item) => item.id_materia)

      return { body: { data: [calculo, algebra].filter((subject) => !offered.includes(subject.id_materia)) } }
    }

    if (path === '/administracion/carreras/3/materias' && request.method === 'POST') {
      const body = request.body as { id_materia: number; id_periodo: number }
      const period = body.id_periodo === 6 ? periodo2 : periodo1
      const row = offering(sistemas, [calculo, algebra].find((item) => item.id_materia === body.id_materia)!, period)

      created.push(row)

      return { status: 201, body: { data: row, mensaje: 'Materia asignada.' } }
    }

    if (path === '/administracion/asignaciones') {
      const careerId = url.searchParams.get('id_carrera')
      // Como el servidor: lo asignado más recientemente va primero, antes de lo que ya estaba.
      const newestFirst = created.filter((item) => String(item.id_periodo) === periodId).reverse()
      const rows = [...newestFirst, ...(baseOffers[periodId] ?? [])]

      return {
        body: {
          data: careerId ? rows.filter((row) => String(row.id_carrera) === careerId) : rows,
          meta: {
            carreras: [civil, economia, sistemas],
            periodo: periodId === '6' ? periodo2 : periodo1,
          },
        },
      }
    }

    return undefined
  })

  return { api, created }
}

async function selectOption(selectName: string, optionName: string) {
  await userEvent.click(screen.getByRole('combobox', { name: selectName }))
  await userEvent.click(await screen.findByRole('option', { name: optionName }))
}

function requestsTo(calls: RecordedRequest[], fragment: string, method = 'GET') {
  return calls.filter((call) => call.method === method && call.url.includes(fragment))
}

function tableRows() {
  return within(screen.getByRole('table')).getAllByRole('row').slice(1)
}

describe('AdminSubjectAssignmentsPage — período', () => {
  it('abre con el período vigente preseleccionado y marcado «(vigente)», y lista solo lo de ese período', async () => {
    const { api } = stubBackend()

    renderPage('/materias/asignaciones')

    expect(await screen.findByText('Hidráulica')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Período académico' })).toHaveTextContent('2/2026 (vigente)')
    expect(screen.getByText('Álgebra Lineal', { selector: 'td' })).toBeInTheDocument()
    expect(screen.queryByText('Cálculo I', { selector: 'td' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Materias asignadas en 2/2026' })).toBeInTheDocument()

    const list = requestsTo(api.calls, '/administracion/asignaciones')

    expect(list).toHaveLength(1)
    expect(new URL(list[0].url).searchParams.get('id_periodo')).toBe('6')
  })

  it('lista los períodos con su etiqueta UMSS y marca solo el vigente', async () => {
    stubBackend()

    renderPage('/materias/asignaciones')
    await screen.findByText('Hidráulica')

    await userEvent.click(screen.getByRole('combobox', { name: 'Período académico' }))

    const options = (await screen.findAllByRole('option')).map((option) => option.textContent)

    expect(options).toEqual(['2/2026 (vigente)', '1/2026'])
  })

  it('al elegir otro período lista solo lo ofrecido en él y lo escribe en la URL', async () => {
    const { api } = stubBackend()

    renderPage('/materias/asignaciones')
    await screen.findByText('Hidráulica')

    await selectOption('Período académico', '1/2026')

    expect(await screen.findByText('Cálculo I', { selector: 'td' })).toBeInTheDocument()
    expect(screen.getByText('Topografia')).toBeInTheDocument()
    expect(screen.queryByText('Hidráulica')).not.toBeInTheDocument()
    expect(screen.queryByText('Álgebra Lineal', { selector: 'td' })).not.toBeInTheDocument()
    expect(currentUrl()).toBe('/materias/asignaciones?periodo=5')

    const lists = requestsTo(api.calls, '/administracion/asignaciones')

    expect(new URL(lists[lists.length - 1].url).searchParams.get('id_periodo')).toBe('5')
  })

  it('el período elegido persiste al recargar: lo lee de la URL', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=5')

    expect(await screen.findByText('Cálculo I', { selector: 'td' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Período académico' })).toHaveTextContent('1/2026')
    expect(screen.getByRole('combobox', { name: 'Período académico' })).not.toHaveTextContent('vigente')
    expect(screen.queryByText('Hidráulica')).not.toBeInTheDocument()
    expect(currentUrl()).toBe('/materias/asignaciones?periodo=5')
  })

  it.each([
    ['no numérico', 'abc'],
    ['cero', '0'],
    ['negativo', '-3'],
    ['decimal', '1.5'],
    ['fuera del rango de int4', '99999999999'],
    ['inexistente', '999'],
  ])('un período inválido en la URL (%s) cae en el vigente y se limpia', async (_label, value) => {
    stubBackend()

    renderPage(`/materias/asignaciones?periodo=${value}`)

    expect(await screen.findByText('Hidráulica')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Período académico' })).toHaveTextContent('2/2026 (vigente)')
    await waitFor(() => expect(currentUrl()).toBe('/materias/asignaciones'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('sin período vigente muestra el más reciente con un aviso discreto', async () => {
    stubBackend({ periods: [{ ...periodo2, vigente: false }, periodo1] })

    renderPage('/materias/asignaciones')

    expect(await screen.findByText('Álgebra Lineal', { selector: 'td' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Período académico' })).toHaveTextContent('2/2026')
    expect(screen.getByText('No hay un período vigente; se muestra el más reciente.')).toBeInTheDocument()
  })

  it('sin período vigente el más reciente es de solo consulta: formulario deshabilitado y aviso', async () => {
    const { api } = stubBackend({ periods: [{ ...periodo2, vigente: false }, periodo1] })

    renderPage('/materias/asignaciones')
    await screen.findByText('Álgebra Lineal', { selector: 'td' })

    expect(
      screen.getByText(
        'Este período es de solo consulta. No hay un período vigente, así que no es posible asignar materias hasta que se marque uno como vigente.'
      )
    ).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Materia' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Asignar materia' })).toBeDisabled()
    expect(requestsTo(api.calls, 'materias-asignables')).toHaveLength(0)
  })

  it('con un período vigente no muestra el aviso', async () => {
    stubBackend()

    renderPage('/materias/asignaciones')
    await screen.findByText('Hidráulica')

    expect(screen.queryByText(/No hay un período vigente/)).not.toBeInTheDocument()
  })

  it('muestra el error de los períodos con opción de reintentar', async () => {
    stubBackend({ periodsStatus: 500 })

    renderPage('/materias/asignaciones')

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })

  it('sin ningún período explica que no se puede asignar', async () => {
    stubBackend({ periods: [] })

    renderPage('/materias/asignaciones')

    expect(await screen.findByText('No hay períodos académicos')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Asignar materia' })).not.toBeInTheDocument()
  })
})

describe('AdminSubjectAssignmentsPage — lista y filtro por carrera', () => {
  it('lista carrera, código, materia y estado de cada oferta del período', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=5')
    await screen.findByText('Topografia')

    const table = within(screen.getByRole('table'))

    expect(table.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Carrera',
      'Código',
      'Materia',
      'Estado',
    ])

    const rows = tableRows()

    expect(within(rows[0]).getByText('Ingenieria de Sistemas')).toBeInTheDocument()
    expect(within(rows[0]).getByText('2008057')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Cálculo I')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Activo')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Ingenieria Civil')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Inactivo')).toBeInTheDocument()
  })

  it('muestra las filas en el orden del servidor, sin reordenarlas por carrera', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=6')
    await screen.findByText('Hidráulica')

    const rows = tableRows()

    expect(within(rows[0]).getByText('Ingenieria de Sistemas')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Ingenieria Civil')).toBeInTheDocument()
  })

  it('no ofrece editar, habilitar, deshabilitar ni eliminar asignaciones', async () => {
    stubBackend()

    renderPage('/materias/asignaciones')
    await screen.findByText('Hidráulica')

    expect(
      screen.queryByRole('button', { name: /editar|habilitar|deshabilitar|desactivar|activar|eliminar|borrar/i })
    ).not.toBeInTheDocument()
  })

  it('filtra por carrera, lo escribe en la URL junto al período y lo conserva al recargar', async () => {
    const { api } = stubBackend()

    renderPage('/materias/asignaciones?periodo=6')
    await screen.findByText('Hidráulica')

    await selectOption('Filtrar por carrera', 'SIS · Ingenieria de Sistemas')

    await waitFor(() => expect(screen.queryByText('Hidráulica')).not.toBeInTheDocument())
    expect(screen.getByText('Álgebra Lineal', { selector: 'td' })).toBeInTheDocument()
    expect(currentUrl()).toBe('/materias/asignaciones?periodo=6&carrera=3')

    const filtered = requestsTo(api.calls, 'id_carrera=3')

    expect(new URL(filtered[0].url).searchParams.get('id_periodo')).toBe('6')

    await selectOption('Filtrar por carrera', 'Todas las carreras')
    await waitFor(() => expect(currentUrl()).toBe('/materias/asignaciones?periodo=6'))
  })

  it('lee el filtro de la URL: un enlace directo o una recarga lo conservan', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=6&carrera=4')

    expect(await screen.findByText('Hidráulica')).toBeInTheDocument()
    expect(screen.queryByText('Álgebra Lineal', { selector: 'td' })).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('CIV · Ingenieria Civil')
    expect(currentUrl()).toBe('/materias/asignaciones?periodo=6&carrera=4')
  })

  it.each([
    ['no numérico', 'abc'],
    ['cero', '0'],
    ['decimal', '1.5'],
    ['fuera del rango de int4', '99999999999'],
    ['carrera desconocida', '999'],
  ])('una carrera inválida en la URL (%s) cae en «Todas las carreras» y se limpia', async (_label, value) => {
    stubBackend()

    renderPage(`/materias/asignaciones?carrera=${value}`)

    expect(await screen.findByText('Hidráulica')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('Todas las carreras')
    await waitFor(() => expect(currentUrl()).toBe('/materias/asignaciones'))
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('el filtro de carrera persiste al cambiar de período', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?carrera=3')
    await screen.findByText('Álgebra Lineal', { selector: 'td' })

    await selectOption('Período académico', '1/2026')

    expect(await screen.findByText('Cálculo I', { selector: 'td' })).toBeInTheDocument()
    expect(currentUrl()).toBe('/materias/asignaciones?carrera=3&periodo=5')
    expect(screen.getByRole('combobox', { name: 'Filtrar por carrera' })).toHaveTextContent('SIS · Ingenieria de Sistemas')
  })

  it('una carrera activa sin ofertas en el período muestra «Esta carrera aún no tiene materias asignadas en 2/2026»', async () => {
    stubBackend()

    renderPage('/materias/asignaciones')
    await screen.findByText('Hidráulica')

    await selectOption('Filtrar por carrera', 'ECO · Economia')

    expect(
      await screen.findByText('Esta carrera aún no tiene materias asignadas en 2/2026')
    ).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(currentUrl()).toBe('/materias/asignaciones?carrera=7')
  })

  it('el estado vacío nombra el período elegido', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=5&carrera=7')

    expect(
      await screen.findByText('Esta carrera aún no tiene materias asignadas en 1/2026')
    ).toBeInTheDocument()
  })
})

describe('AdminSubjectAssignmentsPage — asignar', () => {
  async function chooseCareerAndSubject(subjectOption: string) {
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())
    await selectOption('Carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
    await userEvent.click(screen.getByRole('combobox', { name: 'Materia' }))
    await userEvent.click(await screen.findByRole('option', { name: subjectOption }))
  }

  function subjectOptionNames() {
    return screen.queryAllByRole('option').map((option) => option.textContent)
  }

  it('en el vigente solo ofrece las materias que la carrera aún no tiene ofertadas', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=6')
    await screen.findByText('Hidráulica')

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())
    await selectOption('Carrera', 'SIS · Ingenieria de Sistemas')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
    await userEvent.click(screen.getByRole('combobox', { name: 'Materia' }))

    // Álgebra Lineal ya se ofrece a Sistemas en 2/2026.
    expect(subjectOptionNames()).toEqual(['2008057 · Cálculo I'])
  })

  it('en un período anterior el formulario está deshabilitado y explica que es de solo consulta', async () => {
    const { api } = stubBackend()

    renderPage('/materias/asignaciones?periodo=5')
    await screen.findByText('Topografia')

    expect(
      screen.getByText(
        'Este período es de solo consulta. Solo puedes asignar materias en el período vigente (2/2026).'
      )
    ).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Materia' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Asignar materia' })).toBeDisabled()
    expect(requestsTo(api.calls, 'materias-asignables')).toHaveLength(0)
    expect(api.calls.filter((call) => call.method !== 'GET')).toHaveLength(0)
  })

  it('en el vigente el formulario está habilitado y sin aviso de solo consulta', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=6')
    await screen.findByText('Hidráulica')

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())
    expect(screen.queryByText(/solo de consulta|solo consulta/)).not.toBeInTheDocument()
  })

  it('confirma, avisa con el período y la pareja aparece en la lista del período como «Activo»', async () => {
    const { api } = stubBackend()

    renderPage('/materias/asignaciones?periodo=6')
    await screen.findByText('Hidráulica')

    await chooseCareerAndSubject('2008057 · Cálculo I')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).getByText('2/2026')).toBeInTheDocument()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Confirmar asignación' }))

    expect(
      await screen.findByText('Materia asignada: Cálculo I → Ingenieria de Sistemas (2/2026)')
    ).toBeInTheDocument()

    const calculoRow = await waitFor(() => {
      const row = tableRows().find((item) => within(item).queryByText('Cálculo I') !== null)

      expect(row).toBeDefined()

      return row!
    })

    expect(within(calculoRow).getByText('Activo')).toBeInTheDocument()
    expect(requestsTo(api.calls, '/administracion/carreras/3/materias', 'POST')[0].body).toEqual({
      id_materia: 10,
      id_periodo: 6,
    })
    expect(requestsTo(api.calls, '/administracion/asignaciones')).toHaveLength(2)
  })

  it('tras confirmar, la nueva asignación es la primera fila de la lista', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=6')
    await screen.findByText('Hidráulica')

    expect(within(tableRows()[0]).getByText('Álgebra Lineal')).toBeInTheDocument()

    await chooseCareerAndSubject('2008057 · Cálculo I')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    await screen.findByText('Materia asignada: Cálculo I → Ingenieria de Sistemas (2/2026)')

    await waitFor(() => expect(tableRows()).toHaveLength(3))

    const first = within(tableRows()[0])

    expect(first.getByText('Ingenieria de Sistemas')).toBeInTheDocument()
    expect(first.getByText('2008057')).toBeInTheDocument()
    expect(first.getByText('Cálculo I')).toBeInTheDocument()
    expect(first.getByText('Activo')).toBeInTheDocument()
    expect(within(tableRows()[1]).getByText('Álgebra Lineal')).toBeInTheDocument()
    expect(within(tableRows()[2]).getByText('Hidráulica')).toBeInTheDocument()
  })

  it('con el filtro igual a la carrera asignada, la nueva fila también queda primera', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=6&carrera=3')
    await screen.findByText('Álgebra Lineal', { selector: 'td' })

    await chooseCareerAndSubject('2008057 · Cálculo I')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    await screen.findByText('Materia asignada: Cálculo I → Ingenieria de Sistemas (2/2026)')

    await waitFor(() => expect(tableRows()).toHaveLength(2))

    expect(within(tableRows()[0]).getByText('Cálculo I')).toBeInTheDocument()
    expect(within(tableRows()[1]).getByText('Álgebra Lineal')).toBeInTheDocument()
    expect(currentUrl()).toBe('/materias/asignaciones?periodo=6&carrera=3')
  })

  it('el período y el filtro de la URL sobreviven a una asignación', async () => {
    stubBackend()

    renderPage('/materias/asignaciones?periodo=6&carrera=3')
    await screen.findByText('Álgebra Lineal', { selector: 'td' })

    await chooseCareerAndSubject('2008057 · Cálculo I')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    await screen.findByText('Materia asignada: Cálculo I → Ingenieria de Sistemas (2/2026)')
    expect(currentUrl()).toBe('/materias/asignaciones?periodo=6&carrera=3')
  })

  it('Cancelar cierra el diálogo y la lista no cambia', async () => {
    const { api, created } = stubBackend()

    renderPage('/materias/asignaciones?periodo=6')
    await screen.findByText('Álgebra Lineal', { selector: 'td' })

    await chooseCareerAndSubject('2008057 · Cálculo I')
    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(created).toHaveLength(0)
    expect(api.calls.filter((call) => call.method !== 'GET')).toHaveLength(0)
    expect(tableRows()).toHaveLength(2)
    expect(requestsTo(api.calls, '/administracion/asignaciones')).toHaveLength(1)
  })
})
