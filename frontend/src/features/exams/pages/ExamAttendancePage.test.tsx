import { act, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation, useNavigate } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { meBody, TOKEN_STORAGE_KEY, type AccountKey } from '@/test/authFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'
import { openAllSections } from '@/test/sidebar'
import { EXAM_ATTENDANCE_PATH } from '../utils/paths'
import type { ExamAttendanceDetail, ExamAttendanceExam } from '../types/examAttendance.types'
import { EXAM_STATUS } from '../types/exams.types'

const title = 'Estudiantes ingresados'
const emptyTitle = 'No tiene exámenes con ingreso abierto ni en curso'
const exams: ExamAttendanceExam[] = [
  {
    id_examen: 7,
    nombre_examen: 'Primer parcial',
    estado: EXAM_STATUS.EN_INGRESO,
    fecha: '2026-11-13',
    hora_inicio: '08:00',
    hora_fin: '09:30',
    materia: { id_materia: 2, nombre: 'Cálculo II', codigo: 'MAT-102' },
    grupos: [{ id_grupo: 2, num_grupo: '01' }],
    ambiente: { id_ambiente: 3, nro_aula: '691A' },
  },
  {
    id_examen: 8,
    nombre_examen: 'Segundo parcial',
    estado: EXAM_STATUS.EN_CURSO,
    fecha: '2026-11-13',
    hora_inicio: '10:00',
    hora_fin: null,
    materia: null,
    grupos: [],
    ambiente: null,
  },
]

const attendance: ExamAttendanceDetail = {
  id_examen: 7,
  estado: EXAM_STATUS.EN_CURSO,
  id_ambiente_asignado: null,
  ambientes: [{ id_ambiente: 3, nro_aula: '691A' }, { id_ambiente: 4, nro_aula: '692B' }],
  estudiantes: [
    {
      id_estudiante: 10, nombre_completo: 'María Núñez', cod_sis: '202400010', ci: '4567891',
      carrera: { id_carrera: 2, nombre: 'Sistemas' }, id_ambiente: 3, ambiente: '691A',
      estado: 'A_TIEMPO', hora_ingreso: '08:00', expulsado_en: null,
    },
    {
      id_estudiante: 11, nombre_completo: 'José Flores', cod_sis: '202400011', ci: null,
      carrera: { id_carrera: 5, nombre: 'Informática' }, id_ambiente: 4, ambiente: '692B',
      estado: 'SIN_CLASIFICAR', hora_ingreso: null, expulsado_en: null,
    },
  ],
  totales: { total: 2, pendientes: 0, ausentes: 0, a_tiempo: 1, con_retraso: 0,
    expulsados: 0, no_habilitados: 0, sin_clasificar: 1 },
}

const emptyAttendance: ExamAttendanceDetail = {
  ...attendance, id_examen: 8, estudiantes: [],
  totales: { ...attendance.totales, total: 0, a_tiempo: 0, sin_clasificar: 0 },
}

type Session = AccountKey | 'invitado'
type ApiResponse = { status?: number; body: unknown }

function stubCatalog(
  session: Session,
  catalog = vi.fn<() => ApiResponse>(() => ({ body: { data: [] } })),
  detail = vi.fn<(id: number) => ApiResponse>((id) => ({ body: { data: id === 7 ? attendance : emptyAttendance } }))
) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'token-asistencia')
  const { calls } = mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) {
      const me = meBody(session === 'invitado' ? 'docente' : session)
      if (session === 'invitado') me.data.rol.nombre_rol = 'Invitado'
      return { body: me }
    }
    const match = /\/examenes\/(\d+)\/estudiantes-ingresados$/.exec(url)
    if (match) return detail(Number(match[1]))
    if (url.endsWith(EXAM_ATTENDANCE_PATH)) return catalog()
    return { body: { data: [], meta: { total_pendientes: 0 } } }
  })
  return {
    catalogCalls: () => calls.filter(({ url }) => url.endsWith(EXAM_ATTENDANCE_PATH)),
    detailCalls: () => calls.filter(({ url }) => /\/examenes\/\d+\/estudiantes-ingresados$/.test(url)),
  }
}

function LocationControls() {
  const { search } = useLocation()
  const navigate = useNavigate()

  return <>
    <output aria-label="Parámetros actuales">{search}</output>
    <button onClick={() => navigate(-1)}>Volver en el historial</button>
  </>
}

function currentFilters() {
  return new URLSearchParams(screen.getByLabelText('Parámetros actuales').textContent ?? '')
}

afterEach(() => {
  window.localStorage.clear()
  vi.unstubAllGlobals()
})

describe('HU-066: acceso al catálogo de asistencia', () => {
  it.each(['docente', 'invitado', 'auxiliar'] as const)('%s puede abrir la ruta y tiene una sola entrada', async (role) => {
    const { catalogCalls } = stubCatalog(role)
    const app = renderApp(EXAM_ATTENDANCE_PATH)
    const user = userEvent.setup()

    expect(await screen.findByText(emptyTitle)).toBeVisible()
    expect(screen.queryByRole('combobox', { name: 'Examen' })).not.toBeInTheDocument()
    const sidebar = screen.getByRole('navigation', { name: 'Navegación principal' })
    await openAllSections(user, sidebar)
    const links = within(sidebar).getAllByRole('link', { name: title })
    expect(links).toHaveLength(1)
    expect(links[0]).toHaveAttribute('aria-current', 'page')
    expect(app.pathname).toBe(EXAM_ATTENDANCE_PATH)
    expect(catalogCalls()).toHaveLength(1)
  })

  it('el administrador no tiene la entrada y la URL directa no consulta el catálogo', async () => {
    const { catalogCalls } = stubCatalog('administrador')
    renderApp(EXAM_ATTENDANCE_PATH)
    const user = userEvent.setup()

    expect(await screen.findByRole('alert')).toHaveTextContent('Sin permiso')
    const sidebar = screen.getByRole('navigation', { name: 'Navegación principal' })
    await openAllSections(user, sidebar)
    expect(within(sidebar).queryByRole('link', { name: title })).not.toBeInTheDocument()
    expect(catalogCalls()).toHaveLength(0)
  })

  it('consulta el catálogo una vez y pide el detalle autenticado de cada selección', async () => {
    const auxiliaryDetail = { ...emptyAttendance, id_examen: 7, estado: EXAM_STATUS.EN_INGRESO,
      id_ambiente_asignado: 3, ambientes: [attendance.ambientes[0]] }
    const { catalogCalls, detailCalls } = stubCatalog('auxiliar', vi.fn(() => ({ body: { data: exams } })),
      vi.fn((id) => ({ body: { data: id === 7 ? auxiliaryDetail : emptyAttendance } })))
    renderApp(EXAM_ATTENDANCE_PATH)
    const user = userEvent.setup()
    const selector = await screen.findByRole('combobox', { name: 'Examen' })

    expect(selector).toHaveTextContent('Seleccione un examen')
    expect(detailCalls()).toHaveLength(0)
    await user.click(selector)
    await user.click(screen.getByRole('option', { name: /Primer parcial/ }))
    expect(screen.getByRole('heading', { name: 'Primer parcial' })).toBeVisible()
    expect(screen.getByText('En ingreso')).toBeVisible()
    expect(screen.getByText('Ambiente: 691A')).toBeVisible()

    await user.click(selector)
    await user.click(screen.getByRole('option', { name: /Segundo parcial/ }))
    expect(screen.getByRole('heading', { name: 'Segundo parcial' })).toBeVisible()
    expect(screen.queryByRole('heading', { name: 'Primer parcial' })).not.toBeInTheDocument()
    expect(screen.queryByText('Ambiente: 691A')).not.toBeInTheDocument()
    expect(catalogCalls()).toMatchObject([{ method: 'GET', headers: { Authorization: 'Bearer token-asistencia' } }])
    expect(detailCalls()).toMatchObject([7, 8].map((id) => ({
      url: expect.stringMatching(`/examenes/${id}/estudiantes-ingresados$`),
      method: 'GET', headers: { Authorization: 'Bearer token-asistencia' },
    })))
  })

  it('distingue la carga de una falla temporal y permite reintentar', async () => {
    const catalog = vi.fn<() => ApiResponse>()
      .mockReturnValueOnce({ status: 503, body: { message: 'No se pudo comprobar la apertura del control de ingreso.' } })
      .mockReturnValueOnce({ body: { data: exams } })
    stubCatalog('docente', catalog)
    const respond = globalThis.fetch
    let release!: () => void
    const pending = new Promise<void>((resolve) => { release = resolve })
    vi.stubGlobal('fetch', async (input: RequestInfo | URL, init?: RequestInit) => {
      if (String(input).endsWith(EXAM_ATTENDANCE_PATH)) await pending
      return respond(input, init)
    })
    renderApp(EXAM_ATTENDANCE_PATH)

    expect(await screen.findByText('Cargando exámenes disponibles')).toBeInTheDocument()
    expect(catalog).not.toHaveBeenCalled()
    release()
    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo cargar la información')
    await waitFor(() => expect(catalog).toHaveBeenCalledTimes(1))
    expect(screen.queryByText(emptyTitle)).not.toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('combobox', { name: 'Examen' })).toBeVisible()
    expect(catalog).toHaveBeenCalledTimes(2)
  })

  it('muestra el 403 del catálogo sin cerrar la sesión ni ofrecer reintentar', async () => {
    stubCatalog('docente', vi.fn(() => ({ status: 403, body: { message: 'Acceso denegado.' } })))
    const app = renderApp(EXAM_ATTENDANCE_PATH)

    expect(await screen.findByRole('alert')).toHaveTextContent('Sin permiso')
    expect(screen.getByText('Acceso denegado.')).toBeVisible()
    expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
    expect(app.pathname).toBe(EXAM_ATTENDANCE_PATH)
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('token-asistencia')
  })
})

describe('HU-066: nómina del examen', () => {
  const catalog = () => vi.fn(() => ({ body: { data: exams } }))

  it('recupera selección y filtros de la URL y conserva los totales del servidor', async () => {
    const { detailCalls } = stubCatalog('docente', catalog())
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7&carrera=2&estado=A_TIEMPO&ambiente=3&q=nunez`, <LocationControls />)
    const table = await screen.findByRole('table', { name: 'Estudiantes del examen' })
    const user = userEvent.setup()

    expect(within(table).getByRole('columnheader', { name: 'CI' })).toBeVisible()
    expect(within(table).getByRole('cell', { name: '4567891' })).toBeVisible()
    expect(within(table).getByRole('cell', { name: '08:00' })).toBeVisible()
    expect(within(table).queryByText('José Flores')).not.toBeInTheDocument()
    expect(screen.getByText('1 de 2 estudiantes')).toBeVisible()
    const totals = screen.getByLabelText('Totales de asistencia')
    expect(within(totals).getByText('Total de estudiantes').nextElementSibling).toHaveTextContent('2')
    expect(within(totals).getByText('Sin clasificar').nextElementSibling).toHaveTextContent('1')

    await user.click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    expect(within(table).getByText('José Flores')).toBeVisible()
    expect([...currentFilters()]).toEqual([['examen', '7']])
    await user.click(screen.getByRole('button', { name: 'Volver en el historial' }))
    expect(within(table).queryByText('José Flores')).not.toBeInTheDocument()
    expect(currentFilters().get('estado')).toBe('A_TIEMPO')
    expect(detailCalls()).toHaveLength(1)
  })

  it('busca por nombre, SIS y CI y escribe los filtros elegidos en la URL', async () => {
    const { detailCalls } = stubCatalog('docente', catalog())
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7`, <LocationControls />)
    const search = await screen.findByRole('searchbox', { name: 'Nombre, SIS o CI' })
    const user = userEvent.setup()

    for (const term of ['nunez maria', '202400010', '4567891']) {
      await user.clear(search)
      await user.type(search, term)
      expect(screen.getByRole('cell', { name: 'María Núñez' })).toBeVisible()
      expect(screen.queryByRole('cell', { name: 'José Flores' })).not.toBeInTheDocument()
      expect(currentFilters().get('q')).toBe(term)
    }
    await user.clear(search)
    for (const [field, option, key, value] of [
      ['Carrera', 'Informática', 'carrera', '5'],
      ['Estado', 'Sin clasificar', 'estado', 'SIN_CLASIFICAR'],
      ['Ambiente', '692B', 'ambiente', '4'],
    ]) {
      await user.click(screen.getByRole('combobox', { name: field }))
      await user.click(screen.getByRole('option', { name: option }))
      expect(currentFilters().get(key)).toBe(value)
      expect(screen.getByRole('cell', { name: 'José Flores' })).toBeVisible()
    }
    await user.type(search, 'sin coincidencias')
    expect(screen.getByText('No hay estudiantes que coincidan con los filtros')).toBeVisible()
    expect(detailCalls()).toHaveLength(1)
  })

  it('muestra y busca el CI autorizado del auxiliar sin aplicar un ambiente ajeno de la URL', async () => {
    const student = attendance.estudiantes[0]
    const detail: ExamAttendanceDetail = { ...attendance, id_ambiente_asignado: 3, ambientes: [attendance.ambientes[0]],
      estudiantes: [student], totales: { ...attendance.totales, total: 1, sin_clasificar: 0 } }
    const catalogWithStaleRoom = vi.fn(() => ({ body: { data: [{ ...exams[0], ambiente: attendance.ambientes[1] }] } }))
    const { detailCalls } = stubCatalog('auxiliar', catalogWithStaleRoom, vi.fn(() => ({ body: { data: detail } })))
    const respond = globalThis.fetch
    let release!: () => void
    const pending = new Promise<void>((resolve) => { release = resolve })
    vi.stubGlobal('fetch', async (input: RequestInfo | URL, init?: RequestInit) => {
      if (String(input).endsWith('/examenes/7/estudiantes-ingresados')) await pending
      return respond(input, init)
    })
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7&ambiente=4`)

    expect(await screen.findByText('Cargando estudiantes del examen')).toBeVisible()
    expect(screen.queryByText('Ambiente: 692B')).not.toBeInTheDocument()
    release()
    const table = await screen.findByRole('table')
    expect(screen.getByText('Ambiente: 691A')).toBeVisible()
    expect(screen.queryByText('Ambiente: 692B')).not.toBeInTheDocument()
    expect(within(table).getByText('María Núñez')).toBeVisible()
    expect(within(table).getByRole('columnheader', { name: 'CI' })).toBeVisible()
    expect(within(table).getByRole('cell', { name: student.ci! })).toBeVisible()
    expect(screen.queryByRole('combobox', { name: 'Ambiente' })).not.toBeInTheDocument()
    const user = userEvent.setup()
    const search = screen.getByRole('searchbox', { name: 'Nombre, SIS o CI' })
    for (const term of [student.ci!, 'nunez maria', student.cod_sis]) {
      await user.clear(search)
      await user.type(search, term)
      expect(within(table).getByText('María Núñez')).toBeVisible()
    }
    await user.clear(search)
    await user.type(search, attendance.estudiantes[1].cod_sis)
    expect(screen.getByText('No hay estudiantes que coincidan con los filtros')).toBeVisible()
    expect(detailCalls()).toHaveLength(1)
    expect(detailCalls()[0].url).toMatch(/\/examenes\/7\/estudiantes-ingresados$/)
  })

  it('conserva el ambiente del catálogo para el docente cuando el detalle no tiene ambiente asignado', async () => {
    stubCatalog('docente', catalog())
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7`)

    expect(await screen.findByRole('table')).toBeVisible()
    expect(screen.getByText('Ambiente: 691A')).toBeVisible()
  })

  it('cancela la consulta anterior, descarta su respuesta y limpia los filtros al cambiar de examen', async () => {
    stubCatalog('docente', catalog())
    const respond = globalThis.fetch
    let signal: AbortSignal | null | undefined
    let release!: () => void
    const pending = new Promise<void>((resolve) => { release = resolve })
    vi.stubGlobal('fetch', async (input: RequestInfo | URL, init?: RequestInit) => {
      if (String(input).endsWith('/examenes/7/estudiantes-ingresados')) {
        signal = init?.signal
        await pending
      }
      return respond(input, init)
    })
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7&q=nunez&carrera=2`, <LocationControls />)
    const user = userEvent.setup()
    expect(await screen.findByText('Cargando estudiantes del examen')).toBeVisible()
    await user.click(screen.getByRole('combobox', { name: 'Examen' }))
    await user.click(screen.getByRole('option', { name: /Segundo parcial/ }))
    expect(signal?.aborted).toBe(true)
    expect(await screen.findByText('No hay estudiantes disponibles para este examen')).toBeVisible()
    expect([...currentFilters()]).toEqual([['examen', '8']])
    await act(async () => { release(); await pending })
    expect(screen.queryByText('María Núñez')).not.toBeInTheDocument()
  })

  it('permite reintentar un fallo temporal del detalle', async () => {
    const detail = vi.fn<(id: number) => ApiResponse>()
      .mockReturnValueOnce({ status: 503, body: { message: 'No se pudo comprobar la apertura.' } })
      .mockReturnValueOnce({ body: { data: attendance } })
    stubCatalog('docente', catalog(), detail)
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7`)

    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo comprobar la apertura.')
    await userEvent.setup().click(screen.getByRole('button', { name: 'Reintentar' }))
    expect(await screen.findByRole('cell', { name: 'María Núñez' })).toBeVisible()
    expect(detail).toHaveBeenCalledTimes(2)
  })

  it('mantiene el selector cuando se deniega el detalle y no muestra una nómina', async () => {
    stubCatalog('docente', catalog(), vi.fn(() => ({ status: 403, body: { message: 'Acceso denegado.' } })))
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7`)

    expect(await screen.findByRole('alert')).toHaveTextContent('Sin permiso')
    expect(screen.getByRole('combobox', { name: 'Examen' })).toBeVisible()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
    expect(window.localStorage.getItem(TOKEN_STORAGE_KEY)).toBe('token-asistencia')
  })

  it('deja actualizar el catálogo si el examen terminó antes de cargar el detalle', async () => {
    const catalogResponse = catalog().mockReturnValueOnce({ body: { data: exams } })
      .mockReturnValueOnce({ body: { data: [exams[1]] } })
    stubCatalog('docente', catalogResponse,
      vi.fn(() => ({ status: 409, body: { message: 'El examen no tiene el ingreso abierto ni está en curso.' } })))
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7&estado=A_TIEMPO`, <LocationControls />)

    expect(await screen.findByRole('heading', { name: 'La consulta del examen ha terminado' })).toBeVisible()
    expect(screen.queryByText('En ingreso')).not.toBeInTheDocument()
    await userEvent.setup().click(screen.getByRole('button', { name: 'Actualizar exámenes' }))
    expect(currentFilters().size).toBe(0)
    expect(catalogResponse).toHaveBeenCalledTimes(2)
    expect(screen.getByRole('combobox', { name: 'Examen' })).toHaveTextContent('Seleccione un examen')
  })

  it('no consulta un examen de la URL cuando ya no figura en el catálogo', async () => {
    const { detailCalls } = stubCatalog('docente', vi.fn(() => ({ body: { data: [exams[1]] } })))
    renderApp(`${EXAM_ATTENDANCE_PATH}?examen=7`)

    expect(await screen.findByRole('heading', { name: 'El examen seleccionado no está disponible' })).toBeVisible()
    expect(screen.getByRole('combobox', { name: 'Examen' })).toHaveTextContent('Seleccione un examen')
    expect(detailCalls()).toHaveLength(0)
  })
})
