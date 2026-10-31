import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { AlienAttemptsStudent, Faculty } from '../types/alienAttempts.types'
import { AlienAttemptsReportPage } from './AlienAttemptsReportPage'

const FACULTIES: Faculty[] = [
  { id_facultad: 7, nombre: 'FCyT', codigo: 'FCYT' },
  { id_facultad: 9, nombre: 'FCE', codigo: 'FCE' },
]

function attempt(overrides: Partial<AlienAttemptsStudent> = {}): AlienAttemptsStudent {
  return {
    id_estudiante: 42,
    nombre_completo: 'Sergio Vargas Ibáñez',
    cod_sis: '202178004',
    intentos: 3,
    examenes: ['1er Parcial Cálculo I', '2do Parcial Álgebra I', 'Parcial Física'],
    id_cr_estudiante: 9945,
    ...overrides,
  }
}

const RECORDS: AlienAttemptsStudent[] = [
  attempt(),
  attempt({
    id_estudiante: 43,
    nombre_completo: 'Lucía Salazar Terán',
    cod_sis: '202216630',
    intentos: 2,
    examenes: ['2do Parcial Física', 'Recuperatorio IP'],
    id_cr_estudiante: null,
  }),
  attempt({
    id_estudiante: 44,
    nombre_completo: 'Rodrigo Choque Mamani',
    cod_sis: '202305512',
    intentos: 1,
    examenes: ['1er Parcial Cálculo I'],
    id_cr_estudiante: null,
  }),
]

function statsFor(records: AlienAttemptsStudent[]) {
  return {
    total_intentos: records.reduce((acc, r) => acc + r.intentos, 0),
    total_estudiantes: records.length,
    total_en_central_riesgo: records.filter((r) => r.id_cr_estudiante !== null).length,
  }
}

interface StubOptions {
  records?: AlienAttemptsStudent[]
  listFail?: boolean
  facultiesFail?: boolean
  pdf?: Blob
}

function stubApi(options: StubOptions = {}) {
  const records = options.records ?? RECORDS

  return mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/facultades') {
      return options.facultiesFail
        ? { status: 500, body: { message: 'No se pudo cargar el catálogo.' } }
        : { body: { data: FACULTIES } }
    }

    if (path === '/reportes/intentos-ajenos/pdf') {
      return { body: options.pdf ?? new Blob(['%PDF-1.4'], { type: 'application/pdf' }) }
    }

    if (path === '/reportes/intentos-ajenos') {
      if (options.listFail) {
        return { status: 500, body: { message: 'No se pudo cargar el reporte.' } }
      }

      const facultad = url.searchParams.get('facultad')
      const rows = facultad === null ? records : records.filter((r) => r.id_cr_estudiante !== null)

      return {
        body: {
          data: rows,
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: rows.length,
            from: rows.length === 0 ? null : 1,
            to: rows.length === 0 ? null : rows.length,
            ...statsFor(rows),
          },
        },
      }
    }

    return undefined
  })
}

function LocationProbe() {
  const { pathname, search } = useLocation()

  return <output data-testid="location">{`${pathname}${search}`}</output>
}

function renderPage(route = '/reportes/intentos') {
  return renderWithRouter(
    <>
      <AlienAttemptsReportPage />
      <LocationProbe />
    </>,
    { route, path: '/reportes/intentos' }
  )
}

const location = () => screen.getByTestId('location').textContent

/** Espera la tabla y devuelve sus queries (`within`), aplicable una sola vez. */
async function loadedTable() {
  return within(await screen.findByRole('table'))
}

beforeEach(() => {
  // Fija "hoy" en una fecha lejana a los rangos usados en los tests, para que
  // `isoDaysAgo(30)` no coincida con ninguna fecha del test (evita que
  // `withFilters` borre `desde` por coincidir con el default).
  vi.setSystemTime(new Date('2026-11-15T12:00:00Z'))
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

/**
 * Setea el value de un <input type="date"> con el setter nativo y dispara `input` y
 * `change` para que React actualice el estado. Es el patrón canónico para inputs
 * controlados de tipo date en jsdom: `fireEvent.change(input, { target: { value } })`
 * no pasa por el setter de React y el `onChange` no llega al componente.
 */
function setDateInput(input: HTMLElement, value: string) {
  const nativeSetter = Object.getOwnPropertyDescriptor(
    window.HTMLInputElement.prototype,
    'value'
  )!.set!
  nativeSetter.call(input, value)
  fireEvent.input(input)
  fireEvent.change(input)
}

describe('AlienAttemptsReportPage — render y stats', () => {
  it('muestra título, subtítulo, filtros, stats y filas ordenadas por intentos', async () => {
    stubApi()

    renderPage()

    expect(screen.getByRole('heading', { name: 'Intentos de ingreso a exámenes ajenos' })).toBeInTheDocument()
    expect(
      screen.getByText('Estudiantes que intentaron ingresar a un examen que no les corresponde.')
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Desde')).toBeInTheDocument()
    expect(screen.getByLabelText('Hasta')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Facultad' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Generar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /descargar pdf/i })).toBeInTheDocument()

    const table = await loadedTable()
    const rows = table.getAllByRole('row').slice(1)

    expect(rows).toHaveLength(3)
    expect(within(rows[0]).getByText('Sergio Vargas Ibáñez')).toBeInTheDocument()
    expect(within(rows[0]).getByText('3')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Lucía Salazar Terán')).toBeInTheDocument()
    expect(within(rows[2]).getByText('Rodrigo Choque Mamani')).toBeInTheDocument()

    // Los stats se verifican por su testid para no chocar con los "3" de la tabla.
    expect(within(screen.getByTestId('stat-intentos')).getByText('6')).toBeInTheDocument()
    expect(within(screen.getByTestId('stat-estudiantes')).getByText('3')).toBeInTheDocument()
    expect(within(screen.getByTestId('stat-cr')).getByText('1')).toBeInTheDocument()
  })

  it('el badge «En CR» es un enlace al detalle; el que no está en CR muestra «—» sin enlace', async () => {
    stubApi()

    renderPage()
    const table = await loadedTable()

    const link = table.getByRole('link', { name: /central de riesgo de sergio/i })
    expect(link.getAttribute('href')).toBe('/central-riesgo/9945')

    const luciaRow = table
      .getAllByRole('row')
      .find((row) => row.textContent?.includes('Lucía Salazar Terán'))!
    expect(within(luciaRow).queryByRole('link')).not.toBeInTheDocument()
    expect(within(luciaRow).getByText('—')).toBeInTheDocument()
  })

  it('en móvil la misma información sale como tarjetas', async () => {
    stubApi()

    renderPage()
    await loadedTable()

    const cards = within(
      screen.getByRole('list', { name: 'Estudiantes con intentos a exámenes ajenos' })
    ).getAllByRole('listitem')
    expect(cards).toHaveLength(3)
    expect(within(cards[0]).getByText('Sergio Vargas Ibáñez')).toBeInTheDocument()
    expect(within(cards[0]).getByText(/202178004 · 3 intentos/)).toBeInTheDocument()
    expect(within(cards[0]).getByRole('link').getAttribute('href')).toBe('/central-riesgo/9945')
    expect(within(cards[1]).queryByRole('link')).not.toBeInTheDocument()
  })
})

describe('AlienAttemptsReportPage — filtros', () => {
  it('«Generar» aplica el rango y lo deja en la URL', async () => {
    const user = userEvent.setup()
    const api = stubApi()
    renderPage()
    await loadedTable()

    setDateInput(screen.getByLabelText('Desde'), '2026-10-01')
    setDateInput(screen.getByLabelText('Hasta'), '2026-10-15')

    console.log('[test] input values:', {
      desde: (screen.getByLabelText('Desde') as HTMLInputElement).value,
      hasta: (screen.getByLabelText('Hasta') as HTMLInputElement).value,
    })

    await user.click(screen.getByRole('button', { name: 'Generar' }))

    await waitFor(() => expect(location()).toBe('/reportes/intentos?desde=2026-10-01&hasta=2026-10-15'))

    const lastCall = api.calls.filter((c) => /\/reportes\/intentos-ajenos$/.test(new URL(c.url).pathname)).at(-1)!
    const url = new URL(lastCall.url)
    expect(url.searchParams.get('desde')).toBe('2026-10-01')
    expect(url.searchParams.get('hasta')).toBe('2026-10-15')
  })

  it('con «desde» posterior a «hasta» muestra el error y no llama al servidor', async () => {
    const api = stubApi()
    renderPage()
    await loadedTable()

    const listCallsBefore = api.calls.filter((c) => /\/reportes\/intentos-ajenos$/.test(new URL(c.url).pathname)).length

    setDateInput(screen.getByLabelText('Desde'), '2026-10-31')
    setDateInput(screen.getByLabelText('Hasta'), '2026-10-01')

    expect(
        await screen.findByText('La fecha de inicio debe ser anterior a la fecha de fin.')
    ).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Generar' })).toBeDisabled()

    const listCallsAfter = api.calls.filter((c) => /\/reportes\/intentos-ajenos$/.test(new URL(c.url).pathname)).length
    expect(listCallsAfter).toBe(listCallsBefore)
    })

  it('el filtro por facultad se aplica y queda en la URL', async () => {
    const user = userEvent.setup()
    stubApi()
    renderPage()
    await loadedTable()

    await user.click(screen.getByRole('combobox', { name: 'Facultad' }))
    await user.click(await screen.findByRole('option', { name: 'FCyT' }))

    await waitFor(() => expect(location()).toBe('/reportes/intentos?facultad=7'))
  })

  it('«Todas» quita el filtro de la URL', async () => {
    const user = userEvent.setup()
    stubApi()
    renderPage('/reportes/intentos?facultad=9')
    await loadedTable()

    await user.click(screen.getByRole('combobox', { name: 'Facultad' }))
    await user.click(await screen.findByRole('option', { name: 'Todas' }))

    await waitFor(() => expect(location()).toBe('/reportes/intentos'))
  })

  it('si el catálogo de facultades falla, el filtro se deshabilita y el reporte sigue', async () => {
    stubApi({ facultiesFail: true })

    renderPage()

    expect(await screen.findByRole('table')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Facultad' })).toBeDisabled()
  })
})

describe('AlienAttemptsReportPage — PDF', () => {
  it('«Descargar PDF» pide el archivo con los filtros actuales', async () => {
    // jsdom no implementa URL.createObjectURL/revokeObjectURL.
    Object.defineProperty(URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn(() => 'blob:fake'),
    })
    Object.defineProperty(URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn(),
    })

    const user = userEvent.setup()
    const api = stubApi()
    renderPage('/reportes/intentos?desde=2026-10-01&hasta=2026-10-15&facultad=7')
    await loadedTable()

    await user.click(screen.getByRole('button', { name: /descargar pdf/i }))

    await waitFor(() =>
      expect(api.calls.some((c) => /\/reportes\/intentos-ajenos\/pdf$/.test(new URL(c.url).pathname))).toBe(true)
    )

    const pdfCall = api.calls.find((c) => /\/reportes\/intentos-ajenos\/pdf$/.test(new URL(c.url).pathname))!
    const url = new URL(pdfCall.url)
    expect(url.searchParams.get('desde')).toBe('2026-10-01')
    expect(url.searchParams.get('hasta')).toBe('2026-10-15')
    expect(url.searchParams.get('facultad')).toBe('7')
  })
})

describe('AlienAttemptsReportPage — estados', () => {
  it('sin datos en el rango muestra el mensaje de vacío', async () => {
    stubApi({ records: [] })

    renderPage()

    expect(
      await screen.findByText('No hubo intentos de ingreso a exámenes ajenos en este rango.')
    ).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('un error del servidor muestra el ErrorState con reintentar', async () => {
    stubApi({ listFail: true })

    renderPage()

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })

  it('valores inválidos en la URL caen a los defaults y se reemplazan', async () => {
    stubApi()

    renderPage('/reportes/intentos?facultad=abc&page=0&desde=x&hasta=y')

    await loadedTable()
    await waitFor(() => expect(location()).toBe('/reportes/intentos'))
  })
})