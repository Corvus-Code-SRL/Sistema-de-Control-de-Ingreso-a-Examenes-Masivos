import { act, fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { mockApi, mockApiWith, mockNetworkFailure } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { EntryControlPage } from './EntryControlPage'
import { attemptTime } from '../components/entryAttemptPresentation'
import type { EntryContext, Verification } from '../types/entry-control.types'

const context: EntryContext = {
  id_examen: 7, nombre_examen: 'Primer parcial', fecha: '2026-09-29',
  hora_inicio: '08:00', hora_fin: '10:00', estado: 'EN_INGRESO',
  materia: 'Bases de Datos I', carrera: 'Ing. de Sistemas', grupos: ['1'],
  rol_controlador: 'AUXILIAR', id_ambiente_asignado: 5,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A' }, { id_ambiente: 6, nro_aula: '692B' }],
}
const status = { ingresados: 0, pendientes: 1, total: 1, ultimos_ingresos: [], version: '1', conectados: [] }
const student = { id_estudiante: 19, cod_sis: '202309201', ci: '9456781' as string | null, ci_pendiente: false,
  nombre_completo: 'Carlos Daniel Rocha Mendoza', carrera: 'Ing. de Sistemas', foto_url: null }
const allowed = { veredicto: 'AUTORIZADO', motivo: 'Inscrito en un grupo del examen.', autorizado: true,
  estudiante: student, grupo: { id_grupo: 1, num_grupo: '1' },
  ambiente_asignado: { id_ambiente: 5, nro_aula: '691A' },
  antecedentes: { total: 0, latest: null }, ingreso_previo: null }

function routes(verdict: Verification = allowed, examContext = context): Parameters<typeof mockApi>[0] {
  return [
    { matches: (url: string) => url.endsWith('/contexto'), body: { data: examContext } },
    { matches: (url: string) => url.includes('/estado'), body: { data: examContext.estado === 'PROGRAMADO' ? { estado: 'PROGRAMADO', motivo_apertura: null } : status } },
    { matches: (url: string) => url.endsWith('/verificar'), body: { data: verdict } },
    { matches: (url: string) => url.endsWith('/confirmar-ingreso'), status: 201, body: { data: { creado: true } } },
    { matches: (url: string) => url.endsWith('/tipos-falta'), body: { data: [{ id_falta: 4, nombre: 'Suplantación', descripcion: null }] } },
    { matches: (url: string) => url.includes('/intentos?'), body: { data: [] } },
  ]
}

describe('EntryControlPage', () => {
  it('muestra desde cuándo están desactualizados los datos si se corta la red', async () => {
    mockApi(routes())
    const firstFetch = globalThis.fetch
    let statusCalls = 0
    vi.stubGlobal('fetch', vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      if (String(input).includes('/estado')) {
        statusCalls += 1
        if (statusCalls > 1) return Promise.reject(new TypeError('Failed to fetch'))
      }
      return firstFetch(input, init)
    }))
    renderWithRouter(<EntryControlPage />, {
      route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso',
    })

    await screen.findByText('En vivo')
    fireEvent.click(screen.getByRole('button', { name: 'Actualizar' }))
    expect(await screen.findByText(/Datos sin actualizar desde \d{2}:\d{2}/)).toBeInTheDocument()
  })

  it('muestra el aviso de datos desactualizados al entrar sin red', async () => {
    mockNetworkFailure()
    renderWithRouter(<EntryControlPage />, {
      route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso',
    })

    expect(await screen.findByText(/Datos sin actualizar desde \d{2}:\d{2}/)).toBeInTheDocument()
  })
  it('retira el aviso de datos sin actualizar cuando vuelve la conexión', async () => {
    mockApi(routes())
    const firstFetch = globalThis.fetch
    let offline = false
    vi.stubGlobal('fetch', vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
      if (offline && String(input).includes('/estado')) return Promise.reject(new TypeError('Failed to fetch'))
      return firstFetch(input, init)
    }))
    const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.useFakeTimers()
    try {
      await act(async () => {
        renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
      })
      expect(screen.getByText('En vivo')).toBeInTheDocument()
      expect(screen.queryByText(/Datos sin actualizar/)).not.toBeInTheDocument()

      offline = true
      await act(async () => { await vi.advanceTimersByTimeAsync(2000) })
      expect(screen.getByText(/Datos sin actualizar desde \d{2}:\d{2}/)).toBeInTheDocument()
      expect(screen.queryByText('En vivo')).not.toBeInTheDocument()

      offline = false
      await act(async () => { await vi.advanceTimersByTimeAsync(3000) })
      expect(screen.queryByText(/Datos sin actualizar/)).not.toBeInTheDocument()
      expect(screen.getByText('En vivo')).toBeInTheDocument()
    } finally {
      vi.useRealTimers()
      if (hidden) Object.defineProperty(document, 'hidden', hidden)
    }
  })

  it('muestra al estudiante de otro examen con sus antecedentes, sin aceptar y con reporte', async () => {
    mockApi(routes({
      veredicto: 'NO_PERTENECE', id_intento: 77, autorizado: false, motivo: 'No pertenece a los grupos de este examen',
      estudiante: {
        id_estudiante: 77, cod_sis: '199912543', ci: '0012345', ci_pendiente: false,
        nombre_completo: 'María Fuera Nómina', carrera: null, foto_url: null,
      },
      grupo: null, ambiente_asignado: null,
      antecedentes: { total: 1, latest: { tipo_falta: 'Suplantación', fecha_registro: '2026-10-13' } },
      ingreso_previo: null,
    }))
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const sisInput = await screen.findByLabelText('Código SIS')
    await waitFor(() => expect(sisInput).toBeEnabled())
    await user.type(sisInput, '199912543')
    expect(sisInput).toHaveValue('199912543')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    expect(await screen.findByText('No pertenece a los grupos de este examen')).toBeInTheDocument()
    expect(screen.getByText('María Fuera Nómina')).toBeInTheDocument()
    expect(screen.getByText('199912543')).toBeInTheDocument()
    expect(screen.getByText('Sin fotografía')).toBeInTheDocument()
    expect(screen.getByText('Este estudiante tiene un registro en la Central de Riesgo')).toBeInTheDocument()
    expect(screen.getByText('Suplantación · 2026-10-13')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Reportar incidencia' }))
    const dialog = await screen.findByRole('dialog', { name: 'Reportar incidencia' })
    expect(within(dialog).getByText('María Fuera Nómina')).toBeInTheDocument()
    expect(within(dialog).getByText('691A')).toBeInTheDocument()
    expect(within(dialog).getByRole('textbox', { name: 'Descripción' })).toBeInTheDocument()
  })

  it('muestra estudiante, ambiente, hora y controlador en los últimos ingresos', async () => {
    const apiRoutes = routes()
    apiRoutes[1].body = { data: {
      ...status, ingresados: 1, pendientes: 0,
      ultimos_ingresos: [{
        id_estudiante: 19, cod_sis: '202309201', nombre: 'Carlos',
        apellido_paterno: 'Rocha', apellido_materno: 'Mendoza',
        hora_ingreso: '09:51:00', registrado_en: '2026-09-29T13:51:00Z',
        nro_aula: '691A', controlador_nombre: 'Ana', controlador_apellido: 'López',
      }],
    } }
    mockApi(apiRoutes)
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const room = await screen.findByText('Ambiente 691A')
    expect(screen.getByText('En vivo')).toBeInTheDocument()
    const row = room.closest('li') as HTMLLIElement
    expect(within(row).getByText('Carlos Rocha Mendoza')).toBeInTheDocument()
    expect(within(row).getByText(/por Ana López/)).toBeInTheDocument()
    expect(within(row).getByText('09:51')).toBeInTheDocument()
  })

  it('verifica con el aula fija del auxiliar y confirma el ingreso', async () => {
    mockApi(routes())
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByText(/Usted controla en/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Ambiente de control')).not.toBeInTheDocument()
    await user.type(screen.getByLabelText('Código SIS'), '202309201')
    await user.type(screen.getByLabelText('Carnet de identidad (CI)'), '9456781')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    expect(await screen.findByText('AUTORIZADO', {}, { timeout: 3000 })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Aceptar ingreso' }))

    expect(await screen.findByText(/Ingreso registrado: Carlos Daniel Rocha Mendoza/)).toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ cod_sis: '202309201', ci: '9456781', id_ambiente: 5 })
    await waitFor(() => expect(calls.some(([url]) => String(url).endsWith('/confirmar-ingreso'))).toBe(true))
  })

  it('muestra la ficha de un auxiliar sin permitir confirmar ingreso', async () => {
    mockApi(routes({
      ...allowed, veredicto: 'AUXILIAR_EXAMEN', autorizado: false, estudiante: null,
      auxiliar: { cod_sis: '200100002', nombre_completo: 'Ana López', ambiente_asignado: { id_ambiente: 6, nro_aula: '692B' } },
      grupo: null, ambiente_asignado: null,
      motivo: 'El SIS corresponde a un auxiliar de este examen. No se registra ingreso de estudiante.',
    }))
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    fireEvent.change(await screen.findByLabelText('Código SIS'), { target: { value: '200100002' } })
    fireEvent.click(screen.getByRole('button', { name: 'Verificar estudiante' }))

    expect(await screen.findByText('AUXILIAR DEL EXAMEN')).toBeInTheDocument()
    expect(screen.getByText('Ana López')).toBeInTheDocument()
    expect(screen.getByText('692B')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Rechazar' })).not.toBeInTheDocument()
    expect(screen.queryByText(/Intento registrado con hora/)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Nueva verificación' })).toBeInTheDocument()
    expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).endsWith('/confirmar-ingreso'))).toBe(false)
  })

  it('filtra letras de SIS y CI al pegar y conserva los ceros iniciales del CI', async () => {
    mockApi(routes())
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const sis = await screen.findByLabelText('Código SIS')
    const ci = screen.getByLabelText('Carnet de identidad (CI)')
    const verify = screen.getByRole('button', { name: 'Verificar estudiante' })

    fireEvent.change(sis, { target: { value: '19a9912543x' } })
    fireEvent.change(ci, { target: { value: 'ab00123x' } })
    expect(sis).toHaveValue('199912543')
    expect(ci).toHaveValue('00123')
    expect(verify).toBeEnabled()
    fireEvent.click(verify)

    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()
    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ cod_sis: '199912543', ci: '00123', id_ambiente: 5 })
  })

  it('permite verificar un SIS de nueve dígitos sin validar el mes', async () => {
    mockApi(routes())
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const sis = await screen.findByLabelText('Código SIS')
    const verify = screen.getByRole('button', { name: 'Verificar estudiante' })
    fireEvent.change(sis, { target: { value: '2021' } })
    expect(verify).toBeDisabled()

    fireEvent.change(sis, { target: { value: '202150001' } })
    expect(verify).toBeEnabled()
    fireEvent.click(verify)
    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()

    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    expect(verifyBody.cod_sis).toBe('202150001')
  })

  it('sugiere estudiantes mientras se escribe el SIS y permite seleccionarlos', async () => {
    mockApi([
      ...routes(),
      { matches: (url) => url.includes('/buscar?sis=202'), body: { data: [{
        id_estudiante: 19, cod_sis: '202309201', nombre_completo: 'Carlos Daniel Rocha Mendoza',
      }] } },
    ])
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const sis = await screen.findByLabelText('Código SIS')
    fireEvent.change(sis, { target: { value: '202' } })
    expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeDisabled()
    const suggestion = await screen.findByRole('button', { name: /Carlos Daniel Rocha Mendoza/ })
    expect(suggestion).toHaveTextContent('202309201')
    fireEvent.click(suggestion)
    expect(sis).toHaveValue('202309201')
    expect(screen.queryByRole('list', { name: 'Sugerencias por código SIS' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeEnabled()
  })

  it('sugiere solo números de CI y permite elegirlo sin SIS', async () => {
    mockApi([
      ...routes(),
      { matches: (url) => url.includes('/buscar?ci=9456'), body: { data: [{
        id_estudiante: 19, cod_sis: '202309201', ci: '9456781',
        nombre_completo: 'Carlos Daniel Rocha Mendoza',
      }] } },
    ])
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const sis = await screen.findByLabelText('Código SIS')
    const ci = screen.getByLabelText('Carnet de identidad (CI)')
    fireEvent.change(ci, { target: { value: '9456' } })
    const suggestion = await screen.findByRole('button', { name: '9456781' })
    expect(suggestion).not.toHaveTextContent('Carlos Daniel Rocha Mendoza')
    expect(suggestion).not.toHaveTextContent('202309201')
    fireEvent.click(suggestion)
    expect(sis).toHaveValue('')
    expect(ci).toHaveValue('9456781')
    expect(screen.queryByRole('list', { name: 'Sugerencias por CI' })).not.toBeInTheDocument()

    fireEvent.change(ci, { target: { value: '945' } })
    fireEvent.change(ci, { target: { value: '9456' } })
    expect(await screen.findByRole('list', { name: 'Sugerencias por CI' })).toBeInTheDocument()
  })

  it('conserva el SIS al seleccionar una sugerencia de CI y verifica ambos datos', async () => {
    mockApi([
      ...routes(),
      { matches: (url) => url.includes('/buscar?ci=9456'), body: { data: [{
        id_estudiante: 19, cod_sis: '202309201', ci: '9456781',
        nombre_completo: 'Carlos Daniel Rocha Mendoza',
      }] } },
    ])
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const sis = await screen.findByLabelText('Código SIS')
    const ci = screen.getByLabelText('Carnet de identidad (CI)')
    fireEvent.change(sis, { target: { value: '202309201' } })
    fireEvent.change(ci, { target: { value: '9456' } })
    fireEvent.click(await screen.findByRole('button', { name: '9456781' }))

    expect(sis).toHaveValue('202309201')
    expect(ci).toHaveValue('9456781')
    fireEvent.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()

    const verifyCall = vi.mocked(fetch).mock.calls.find(([url]) => String(url).endsWith('/verificar'))
    const verifyBody = JSON.parse((verifyCall?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ cod_sis: '202309201', ci: '9456781', id_ambiente: 5 })
  })

  it('muestra como máximo cinco sugerencias por SIS, CI y nombre', async () => {
    const matches = Array.from({ length: 7 }, (_, index) => ({
      id_estudiante: index + 1,
      cod_sis: `20230920${index}`,
      ci: `945678${index}`,
      nombre_completo: `Estudiante ${index}`,
    }))
    mockApi([
      ...routes(),
      { matches: (url) => url.includes('/buscar?sis=202'), body: { data: matches } },
      { matches: (url) => url.includes('/buscar?ci=9456'), body: { data: matches } },
      { matches: (url) => url.includes('/buscar?nombre=Estudiante'), body: { data: matches } },
    ])
    renderWithRouter(<EntryControlPage />, {
      route: '/examenes/7/control-ingreso',
      path: '/examenes/:examId/control-ingreso',
    })

    fireEvent.change(await screen.findByLabelText('Código SIS'), { target: { value: '202' } })
    expect(within(await screen.findByRole('list', { name: 'Sugerencias por código SIS' })).getAllByRole('listitem')).toHaveLength(5)

    fireEvent.change(screen.getByLabelText('Carnet de identidad (CI)'), { target: { value: '9456' } })
    expect(within(await screen.findByRole('list', { name: 'Sugerencias por CI' })).getAllByRole('listitem')).toHaveLength(5)

    fireEvent.change(screen.getByLabelText('Buscar estudiante por nombre'), { target: { value: 'Estudiante' } })
    expect(within(await screen.findByRole('list', { name: 'Coincidencias de estudiantes' })).getAllByRole('listitem')).toHaveLength(5)
  })

  it('verifica y confirma usando solo el CI', async () => {
    mockApi(routes())
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const sis = await screen.findByLabelText('Código SIS')
    const ci = screen.getByLabelText('Carnet de identidad (CI)')
    const verify = screen.getByRole('button', { name: 'Verificar estudiante' })
    expect(verify).toBeDisabled()
    await user.type(ci, '9456781')
    expect(sis).toHaveValue('')
    expect(verify).toBeEnabled()
    await user.click(verify)
    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Aceptar ingreso' }))
    expect(await screen.findByText(/Ingreso registrado:/)).toBeInTheDocument()

    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    const confirmBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/confirmar-ingreso'))?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ ci: '9456781', id_ambiente: 5 })
    expect(confirmBody).toEqual({ id_estudiante: 19, ci: '9456781', id_ambiente: 5 })
  })

  it('verifica y confirma usando solo el código SIS', async () => {
    mockApi(routes({
      ...allowed,
      estudiante: { ...student, ci: null, ci_pendiente: true },
    }))
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    await user.type(await screen.findByLabelText('Código SIS'), '202309201')
    expect(screen.getByLabelText('Carnet de identidad (CI)')).toHaveValue('')
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()
    expect(screen.getByText('No ingresado')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Aceptar ingreso' }))
    expect(await screen.findByText(/Ingreso registrado:/)).toBeInTheDocument()

    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    const confirmBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/confirmar-ingreso'))?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ cod_sis: '202309201', id_ambiente: 5 })
    expect(confirmBody).toEqual({ id_estudiante: 19, id_ambiente: 5 })
  })

  it('busca por nombre, registra el rechazo y abre el modal de reporte', async () => {
    mockApi([
      ...routes(),
      { matches: (url) => url.includes('/buscar'), body: { data: [{
        id_estudiante: student.id_estudiante, cod_sis: student.cod_sis, nombre_completo: student.nombre_completo,
      }] } },
      { matches: (url) => url.endsWith('/rechazar-ingreso'), status: 201, body: { data: { registrado: true, id_intento: 123, motivo: 'DECISION_CONTROLADOR' } } },
    ])
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    await screen.findByLabelText('Código SIS')
    await user.type(screen.getByLabelText('Buscar estudiante por nombre'), 'Carlos')
    await user.click(await screen.findByRole('button', { name: /Carlos Daniel Rocha Mendoza/ }))
    expect(screen.getByLabelText('Código SIS')).toHaveValue('202309201')
    await user.type(screen.getByLabelText('Carnet de identidad (CI)'), '9456781')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    await screen.findByText('AUTORIZADO', {}, { timeout: 3000 })
    await user.click(screen.getByRole('button', { name: 'Rechazar' }))
    const dialog = await screen.findByRole('dialog', { name: 'Reportar incidencia' })
    expect(screen.queryByLabelText('Motivo')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar intento' })).not.toBeInTheDocument()
    expect(within(dialog).getByRole('textbox', { name: 'Descripción' })).toBeInTheDocument()
    expect(screen.getAllByRole('dialog')).toHaveLength(1)
    expect(within(dialog).getByText('Carlos Daniel Rocha Mendoza')).toBeInTheDocument()
    expect(within(dialog).getByText('691A')).toBeInTheDocument()
    expect(screen.getByText('INGRESO RECHAZADO')).toBeInTheDocument()
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))
    expect(screen.getByText('INGRESO RECHAZADO')).toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('202309201')
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reportar incidencia' })).toBeEnabled()
    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    const rejectBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/rechazar-ingreso'))?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ cod_sis: '202309201', ci: '9456781', id_ambiente: 5 })
    expect(rejectBody).toEqual({ id_estudiante: 19, ci: '9456781', id_ambiente: 5, motivo: 'DECISION_CONTROLADOR' })
  })

  it('abre el modal de HU-042 para un ingreso duplicado', async () => {
    mockApi(routes({ ...allowed, veredicto: 'DUPLICADO', id_intento: 124, autorizado: false,
      motivo: 'El estudiante ya registró su ingreso.' }))
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    await user.type(await screen.findByLabelText('Código SIS'), '202309201')
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    expect(await screen.findByText('INGRESO DUPLICADO')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Reportar incidencia' }))
    const dialog = await screen.findByRole('dialog', { name: 'Reportar incidencia' })
    expect(screen.getAllByRole('dialog')).toHaveLength(1)
    expect(within(dialog).getByText('Carlos Daniel Rocha Mendoza')).toBeInTheDocument()
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))
    expect(screen.queryByRole('dialog', { name: 'Reportar incidencia' })).not.toBeInTheDocument()
    expect(screen.getByText('INGRESO DUPLICADO')).toBeInTheDocument()
  })

  it('muestra que no hay coincidencias solo después de recibir la búsqueda', async () => {
    mockApi([
      ...routes(),
      { matches: (url) => url.includes('/buscar'), body: { data: [] } },
    ])
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    const search = await screen.findByLabelText('Buscar estudiante por nombre')
    fireEvent.change(search, { target: { value: 'José' } })
    expect(screen.queryByText('No hay coincidencias en este examen.')).not.toBeInTheDocument()
    expect(await screen.findByText('No hay coincidencias en este examen.')).toBeInTheDocument()
  })

  it('desactiva la búsqueda por nombre cuando no hay ambiente de control', async () => {
    mockApi(routes(allowed, { ...context, id_ambiente_asignado: null, ambientes: [] }))
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByLabelText('Buscar estudiante por nombre')).toBeDisabled()
    expect(screen.getByLabelText('Código SIS')).toBeDisabled()
    expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).includes('/buscar'))).toBe(false)
  })

  it('muestra que el control terminó y oculta la verificación cuando /estado devuelve 409', async () => {
    const apiRoutes = routes()
    mockApi(apiRoutes)
    vi.useFakeTimers()
    try {
      await act(async () => {
        renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
      })
      expect(screen.getByLabelText('Código SIS')).toBeInTheDocument()

      apiRoutes[1].status = 409
      apiRoutes[1].body = { message: 'El control de ingreso ha terminado.' }
      await act(async () => { await vi.advanceTimersByTimeAsync(2000) })

      expect(screen.getByText('El control de ingreso ha terminado.')).toBeInTheDocument()
      expect(screen.getByText('Ingreso terminado')).toBeInTheDocument()
      expect(screen.getByText('1 pendientes')).toBeInTheDocument()
      expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()
      const calls = vi.mocked(fetch).mock.calls.filter(([url]) => String(url).includes('/estado')).length
      await act(async () => { await vi.advanceTimersByTimeAsync(60000) })
      expect(vi.mocked(fetch).mock.calls.filter(([url]) => String(url).includes('/estado'))).toHaveLength(calls)
    } finally {
      vi.useRealTimers()
    }
  })

  it('permite al creador consultar un examen programado sin intentar verificar', async () => {
    mockApi(routes(allowed, { ...context, rol_controlador: 'DOCENTE', estado: 'PROGRAMADO' }))
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByText(/El control de ingreso no está abierto/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()
    expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).includes('/estado'))).toBe(true)
    expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).endsWith('/verificar'))).toBe(false)
  })

  it('muestra el formulario cuando el examen se abre automáticamente', async () => {
    const apiRoutes = routes(allowed, { ...context, estado: 'PROGRAMADO' })
    mockApi(apiRoutes)
    const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })

    vi.useFakeTimers()
    try {
      await act(async () => {
        renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
      })
      expect(screen.getByText(/El control de ingreso no está abierto/)).toBeInTheDocument()
      apiRoutes[0].body = { data: context }
      apiRoutes[1].body = { data: status }

      await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
      expect(screen.getByLabelText('Código SIS')).toBeInTheDocument()
      expect(screen.queryByText(/El control de ingreso no está abierto/)).not.toBeInTheDocument()
    } finally {
      vi.useRealTimers()
      if (hidden) Object.defineProperty(document, 'hidden', hidden)
    }
  })

  it.each([
    ['SIN_NOMINA', 'El examen no tiene estudiantes en su nómina.'],
    ['SIN_AMBIENTES', 'El examen no tiene ambientes asignados.'],
    ['CAPACIDAD_INSUFICIENTE', 'Capacidad 1 menor que la nómina de 2.'],
  ])('muestra el motivo %s en lugar del aviso genérico', async (codigo, mensaje) => {
    const apiRoutes = routes(allowed, { ...context, estado: 'PROGRAMADO' })
    apiRoutes[1].body = { data: { estado: 'PROGRAMADO', motivo_apertura: { codigo, mensaje } } }
    mockApi(apiRoutes)
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByText(mensaje)).toBeInTheDocument()
    expect(screen.queryByText(/El control de ingreso no está abierto/)).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()
    expect(screen.queryByText(/No se pudo actualizar el contador/)).not.toBeInTheDocument()
  })

  it('actualiza el motivo durante la espera y lo retira cuando abre', async () => {
    const apiRoutes = routes(allowed, { ...context, estado: 'PROGRAMADO' })
    mockApi(apiRoutes)
    const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')
    Object.defineProperty(document, 'hidden', { configurable: true, value: false })
    vi.useFakeTimers()
    try {
      await act(async () => {
        renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
      })
      expect(screen.getByText(/El control de ingreso no está abierto/)).toBeInTheDocument()
      const mensaje = 'Capacidad 1 menor que la nómina de 2.'
      apiRoutes[1].body = { data: { estado: 'PROGRAMADO', motivo_apertura: { codigo: 'CAPACIDAD_INSUFICIENTE', mensaje } } }
      await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
      expect(screen.getByText(mensaje)).toBeInTheDocument()
      expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()

      apiRoutes[0].body = { data: context }
      apiRoutes[1].body = { data: status }
      await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
      expect(screen.queryByText(mensaje)).not.toBeInTheDocument()
      expect(screen.getByLabelText('Código SIS')).toBeInTheDocument()

      apiRoutes[1].body = { data: { sin_cambios: true, version: status.version, conectados: [] } }
      await act(async () => { await vi.advanceTimersByTimeAsync(2000) })
      expect(screen.queryByText(mensaje)).not.toBeInTheDocument()
      expect(screen.queryByText(/No se pudo actualizar/)).not.toBeInTheDocument()
      expect(screen.getByRole('progressbar', { name: 'Estudiantes ingresados' })).toHaveAttribute('aria-valuemax', '1')
      expect(screen.getByText('1 pendientes')).toBeInTheDocument()
      expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).includes('desde=1'))).toBe(true)
    } finally {
      vi.useRealTimers()
      if (hidden) Object.defineProperty(document, 'hidden', hidden)
    }
  })

  it('permite reintentar la consulta del motivo sin mostrar errores técnicos', async () => {
    const apiRoutes = routes(allowed, { ...context, estado: 'PROGRAMADO' })
    apiRoutes[1].status = 500
    apiRoutes[1].body = { message: 'SQLSTATE[42703]: columna inexistente' }
    mockApi(apiRoutes)
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByText(/Datos sin actualizar desde \d{2}:\d{2}/)).toBeInTheDocument()
    expect(screen.queryByText(/SQLSTATE/)).not.toBeInTheDocument()
    apiRoutes[1].status = 200
    apiRoutes[1].body = { data: { estado: 'PROGRAMADO', motivo_apertura: {
      codigo: 'SIN_AMBIENTES', mensaje: 'El examen no tiene ambientes asignados.',
    } } }
    await user.click(screen.getByRole('button', { name: 'Actualizar' }))
    expect(await screen.findByText('El examen no tiene ambientes asignados.')).toBeInTheDocument()
    expect(screen.queryByText(/No se pudo actualizar/)).not.toBeInTheDocument()
  })

  it('oculta los detalles SQL cuando falla el contador', async () => {
    mockApi([
      { matches: (url) => url.endsWith('/contexto'), body: { data: context } },
      { matches: (url) => url.includes('/estado'), status: 500, body: { message: 'SQLSTATE[42703]: columna inexistente' } },
    ])
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByText(/El servidor no pudo responder/)).toBeInTheDocument()
    expect(screen.queryByText(/SQLSTATE/)).not.toBeInTheDocument()
  })

  it('bloquea la confirmación cuando el estudiante no está habilitado', async () => {
    mockApi(routes({ ...allowed, veredicto: 'NO_HABILITADO', autorizado: false, motivo: 'No está habilitado.' }))
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
    await screen.findByLabelText('Código SIS')
    await user.type(screen.getByLabelText('Código SIS'), '202309201')
    await user.type(screen.getByLabelText('Carnet de identidad (CI)'), '9456781')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))

    expect(await screen.findByText('NO HABILITADO')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Siguiente estudiante' })).toBeInTheDocument()
  })

  it('Volver y la miga de pan llevan a Control de ingreso, no a Programados', async () => {
    mockApi(routes())
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
    await screen.findByText(/Usted controla en/)

    const links = screen.getAllByRole('link', { name: /control de ingreso/i })
    const targets = links.map((link) => link.getAttribute('href'))

    // El enlace «Control de ingreso» con flecha, la miga de pan y el ítem del menú lateral.
    expect(targets.length).toBeGreaterThanOrEqual(2)
    expect(targets.every((href) => href === '/control-ingreso')).toBe(true)
    expect(screen.queryByRole('link', { name: /^exámenes$/i })).not.toBeInTheDocument()
    expect(document.querySelector('a[href="/examenes/programados"][class*="inline-flex"]')).toBeNull()
  })

  it('la miga de pan termina en el nombre del examen', async () => {
    mockApi(routes())
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
    await screen.findByText(/Usted controla en/)

    expect(screen.getAllByText('Primer parcial').length).toBeGreaterThan(0)
  })
})

async function sendIncident(user: ReturnType<typeof userEvent.setup>) {
  const dialog = await screen.findByRole('dialog', { name: 'Reportar incidencia' })
  await waitFor(() => expect(within(dialog).getByRole('combobox')).toBeEnabled())
  await user.click(within(dialog).getByRole('combobox'))
  await user.click(screen.getByRole('option', { name: 'Suplantación' }))
  await user.type(within(dialog).getByRole('textbox', { name: 'Descripción' }), 'La fotografía no corresponde a la persona presente.')
  await user.click(within(dialog).getByRole('button', { name: 'Enviar a revisión' }))
}

const attemptRegisteredAt = '2026-10-18T14:03:00Z'

// Misma hora local que muestra el componente; Testing Library normaliza los espacios al comparar.
const containsAttemptTime = (value: string) => {
  const expected = attemptTime(value).replace(/\s+/g, ' ')
  return (content: string) => content.includes(expected)
}

describe('HU-042 integrada sobre HU-032', () => {
  it.each([
    ['NO_PERTENECE', 'sis'], ['NO_PERTENECE', 'ci'], ['CI_NO_COINCIDE', 'sis'],
    ['AULA_INCORRECTA', 'sis'], ['DUPLICADO', 'sis'], ['NO_HABILITADO', 'sis'],
  ])('envía el intento real de %s al verificar por %s', async (verdict, field) => {
    const apiRoutes = routes({
      ...allowed, veredicto: verdict, autorizado: false, id_intento: 321,
      registrado_en: attemptRegisteredAt,
      motivo: verdict === 'NO_PERTENECE' ? 'No pertenece a los grupos de este examen' : 'Ingreso no autorizado.',
      grupo: verdict === 'NO_PERTENECE' ? null : allowed.grupo,
    })
    const { calls } = mockApiWith((request) => {
      if (request.url.endsWith('/intentos/321/reporte')) return {
        status: 201, body: { data: { id_reporte_est: 100, id_intento: 321, estado: 'PENDIENTE' } },
      }
      return apiRoutes.find((route) => route.matches(request.url))
    })
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
    await user.type(await screen.findByLabelText(field === 'sis' ? 'Código SIS' : 'Carnet de identidad (CI)'), field === 'sis' ? student.cod_sis : student.ci!)
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    await user.click(await screen.findByRole('button', { name: 'Reportar incidencia' }))
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    const dialog = screen.getByRole('dialog', { name: 'Reportar incidencia' })
    expect(within(dialog).getByText(student.nombre_completo)).toBeInTheDocument()
    expect(within(dialog).getByText('691A')).toBeInTheDocument()
    expect(within(dialog).getByText(containsAttemptTime(attemptRegisteredAt))).toBeInTheDocument()
    await sendIncident(user)
    expect(await screen.findByRole('button', { name: 'Ya reportado' })).toBeDisabled()
    const posts = calls.filter((call) => call.url.endsWith('/reporte'))
    expect(posts).toHaveLength(1)
    expect(posts[0].url).toMatch(/\/examenes\/7\/intentos\/321\/reporte$/)
    expect(posts[0].body).toBeInstanceOf(FormData)
    expect((posts[0].body as FormData).has('id_estudiante')).toBe(false)
    expect(calls.some((call) => call.url.endsWith('/rechazar-ingreso'))).toBe(false)
  })

  it('conserva el rechazo al cancelar y muestra Ya reportado también en la pestaña', async () => {
    let reported = false
    const apiRoutes = routes()
    const { calls } = mockApiWith((request) => {
      if (request.url.endsWith('/rechazar-ingreso')) return { status: 201, body: { data: {
        registrado: true, id_intento: 30, motivo: 'DECISION_CONTROLADOR', registrado_en: '2026-10-18T14:00:00Z',
      } } }
      if (request.url.endsWith('/intentos/30/reporte')) {
        reported = true
        return { status: 201, body: { data: { id_reporte_est: 100, id_intento: 30, estado: 'PENDIENTE' } } }
      }
      if (request.url.includes('/intentos?')) return { body: { data: [{
        id_intento: 30, estudiante: student, ambiente: { id_ambiente: 5, nro_aula: '691A' },
        motivo: 'DECISION_CONTROLADOR', observacion: null, registrado_en: '2026-10-18T14:00:00Z', reportado: reported,
      }] } }
      return apiRoutes.find((route) => route.matches(request.url))
    })
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
    await user.type(await screen.findByLabelText('Código SIS'), student.cod_sis)
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    await user.click(await screen.findByRole('button', { name: 'Rechazar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Cancelar' }))
    expect(screen.getByText('INGRESO RECHAZADO')).toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue(student.cod_sis)
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    expect(calls.some((call) => call.url.endsWith('/reporte'))).toBe(false)
    await user.click(screen.getByRole('button', { name: 'Reportar incidencia' }))
    await sendIncident(user)
    expect(await screen.findByRole('button', { name: 'Ya reportado' })).toBeDisabled()
    await user.click(screen.getByRole('button', { name: 'Siguiente estudiante' }))
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
    expect(await screen.findByRole('button', { name: 'Ya reportado' })).toBeDisabled()
    expect(screen.getByRole('tab', { name: /Rechazos/ })).toHaveAttribute('aria-selected', 'true')
    expect(calls.filter((call) => call.url.endsWith('/rechazar-ingreso'))).toHaveLength(1)
    expect(calls.filter((call) => call.url.endsWith('/reporte'))).toHaveLength(1)
  })

  it('usa el intento de la nueva verificación si confirmar devuelve 409', async () => {
    let verifyCount = 0
    const apiRoutes = routes()
    const { calls } = mockApiWith((request) => {
      if (request.url.endsWith('/verificar')) {
        verifyCount += 1
        return { body: { data: verifyCount === 1 ? allowed : {
          ...allowed, autorizado: false, veredicto: 'DUPLICADO', id_intento: 444,
        } } }
      }
      if (request.url.endsWith('/confirmar-ingreso')) return { status: 409, body: { mensaje: 'El ingreso ya fue registrado.' } }
      if (request.url.endsWith('/intentos/444/reporte')) return { status: 201, body: {
        data: { id_reporte_est: 101, id_intento: 444, estado: 'PENDIENTE' },
      } }
      return apiRoutes.find((route) => route.matches(request.url))
    })
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
    await user.type(await screen.findByLabelText('Código SIS'), student.cod_sis)
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    await user.click(await screen.findByRole('button', { name: 'Aceptar ingreso' }))
    await user.click(await screen.findByRole('button', { name: 'Reportar incidencia' }))
    await sendIncident(user)
    expect(await screen.findByRole('button', { name: 'Ya reportado' })).toBeDisabled()
    expect(calls.filter((call) => call.url.endsWith('/intentos/444/reporte'))).toHaveLength(1)
  })

  it('no abre un reporte sin intento persistido ni para una persona desconocida', async () => {
    mockApi(routes({ ...allowed, veredicto: 'NO_ENCONTRADO', autorizado: false, estudiante: null, id_intento: 555 }))
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })
    await user.type(await screen.findByLabelText('Código SIS'), '999999999')
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    await screen.findByRole('button', { name: 'Siguiente estudiante' })
    expect(screen.queryByRole('button', { name: 'Reportar incidencia' })).not.toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})