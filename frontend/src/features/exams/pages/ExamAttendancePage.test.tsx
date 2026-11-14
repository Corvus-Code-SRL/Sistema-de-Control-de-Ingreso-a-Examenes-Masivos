import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { meBody, TOKEN_STORAGE_KEY, type AccountKey } from '@/test/authFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'
import { openAllSections } from '@/test/sidebar'
import { EXAM_ATTENDANCE_PATH } from '../utils/paths'
import type { ExamAttendanceExam } from '../types/examAttendance.types'
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

type Session = AccountKey | 'invitado'
type CatalogResponse = { status?: number; body: unknown }

function stubCatalog(session: Session, catalog = vi.fn<() => CatalogResponse>(() => ({ body: { data: [] } }))) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'token-asistencia')
  const { calls } = mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) {
      const me = meBody(session === 'invitado' ? 'docente' : session)
      if (session === 'invitado') me.data.rol.nombre_rol = 'Invitado'
      return { body: me }
    }
    if (url.endsWith(EXAM_ATTENDANCE_PATH)) return catalog()
    return { body: { data: [], meta: { total_pendientes: 0 } } }
  })
  return () => calls.filter(({ url }) => url.endsWith(EXAM_ATTENDANCE_PATH))
}

afterEach(() => {
  window.localStorage.clear()
  vi.unstubAllGlobals()
})

describe('HU-066: acceso al catálogo de asistencia', () => {
  it.each(['docente', 'invitado', 'auxiliar'] as const)('%s puede abrir la ruta y tiene una sola entrada', async (role) => {
    const catalogCalls = stubCatalog(role)
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
    const catalogCalls = stubCatalog('administrador')
    renderApp(EXAM_ATTENDANCE_PATH)
    const user = userEvent.setup()

    expect(await screen.findByRole('alert')).toHaveTextContent('Sin permiso')
    const sidebar = screen.getByRole('navigation', { name: 'Navegación principal' })
    await openAllSections(user, sidebar)
    expect(within(sidebar).queryByRole('link', { name: title })).not.toBeInTheDocument()
    expect(catalogCalls()).toHaveLength(0)
  })

  it('consulta el catálogo autenticado y permite cambiar de examen sin otra petición', async () => {
    const catalogCalls = stubCatalog('auxiliar', vi.fn(() => ({ body: { data: exams } })))
    renderApp(EXAM_ATTENDANCE_PATH)
    const user = userEvent.setup()
    const selector = await screen.findByRole('combobox', { name: 'Examen' })

    expect(selector).toHaveTextContent('Seleccione un examen')
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
  })

  it('distingue la carga de una falla temporal y permite reintentar', async () => {
    const catalog = vi.fn<() => CatalogResponse>()
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
