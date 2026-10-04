import { act, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { mockApi } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { EntryControlPage } from './EntryControlPage'

const context = {
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
  antecedentes: { tiene_antecedentes: false, cantidad: 0, resumen: null }, ingreso_previo: null }

function routes(verdict = allowed, examContext = context) {
  return [
    { matches: (url: string) => url.endsWith('/contexto'), body: { data: examContext } },
    { matches: (url: string) => url.includes('/estado'), body: { data: status } },
    { matches: (url: string) => url.endsWith('/verificar'), body: { data: verdict } },
    { matches: (url: string) => url.endsWith('/confirmar-ingreso'), status: 201, body: { data: { creado: true } } },
  ]
}

describe('EntryControlPage', () => {
  it('verifica con el aula fija del auxiliar y confirma el ingreso', async () => {
    mockApi(routes())
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByText(/Usted controla en/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Ambiente de control')).not.toBeInTheDocument()
    await user.type(screen.getByLabelText('Código SIS'), '202309201')
    await user.type(screen.getByLabelText('Carnet de identidad (opcional)'), '9456781')
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

  it('verifica y confirma usando solo el código SIS', async () => {
    mockApi(routes({
      ...allowed,
      estudiante: { ...student, ci: null, ci_pendiente: true },
    }))
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    await user.type(await screen.findByLabelText('Código SIS'), '202309201')
    expect(screen.getByLabelText('Carnet de identidad (opcional)')).toHaveValue('')
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()
    expect(screen.getByText('No registrado')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Aceptar ingreso' }))
    expect(await screen.findByText(/Ingreso registrado:/)).toBeInTheDocument()

    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    const confirmBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/confirmar-ingreso'))?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ cod_sis: '202309201', id_ambiente: 5 })
    expect(confirmBody).toEqual({ id_estudiante: 19, id_ambiente: 5 })
  })

  it('busca por nombre y registra el rechazo con motivo', async () => {
    mockApi([
      ...routes(),
      { matches: (url) => url.includes('/buscar'), body: { data: [{
        id_estudiante: student.id_estudiante, cod_sis: student.cod_sis, nombre_completo: student.nombre_completo,
      }] } },
      { matches: (url) => url.endsWith('/rechazar-ingreso'), status: 201, body: { data: { registrado: true } } },
    ])
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    await screen.findByLabelText('Código SIS')
    await user.click(screen.getByRole('button', { name: 'Buscar por nombre' }))
    await user.type(screen.getByLabelText('Buscar estudiante por nombre'), 'Carlos')
    await user.click(await screen.findByRole('button', { name: /Carlos Daniel Rocha Mendoza/ }))
    expect(screen.getByLabelText('Código SIS')).toHaveValue('202309201')
    await user.type(screen.getByLabelText('Carnet de identidad (opcional)'), '9456781')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    await screen.findByText('AUTORIZADO', {}, { timeout: 3000 })
    await user.click(screen.getByRole('button', { name: 'Rechazar' }))
    await user.type(screen.getByLabelText('Observación (opcional)'), 'Foto distinta')
    await user.click(screen.getByRole('button', { name: 'Registrar intento' }))

    expect(await screen.findByText('Intento rechazado y registrado.')).toBeInTheDocument()
    const calls = vi.mocked(fetch).mock.calls
    const verifyBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/verificar'))?.[1] as RequestInit).body as string)
    const rejectBody = JSON.parse((calls.find(([url]) => String(url).endsWith('/rechazar-ingreso'))?.[1] as RequestInit).body as string)
    expect(verifyBody).toEqual({ cod_sis: '202309201', ci: '9456781', id_ambiente: 5 })
    expect(rejectBody).toEqual({ id_estudiante: 19, ci: '9456781', id_ambiente: 5, motivo: 'IDENTIDAD_DUDOSA', observacion: 'Foto distinta' })
  })

  it('permite al creador consultar un examen programado sin intentar verificar', async () => {
    mockApi(routes(allowed, { ...context, rol_controlador: 'DOCENTE', estado: 'PROGRAMADO' }))
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    expect(await screen.findByText(/El control de ingreso no está abierto/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()
    expect(vi.mocked(fetch).mock.calls.some(([url]) => String(url).includes('/estado'))).toBe(false)
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

      await act(async () => { await vi.advanceTimersByTimeAsync(15000) })
      expect(screen.getByLabelText('Código SIS')).toBeInTheDocument()
      expect(screen.queryByText(/El control de ingreso no está abierto/)).not.toBeInTheDocument()
    } finally {
      vi.useRealTimers()
      if (hidden) Object.defineProperty(document, 'hidden', hidden)
    }
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
    await user.type(screen.getByLabelText('Carnet de identidad (opcional)'), '9456781')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Verificar estudiante' })).toBeEnabled())
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))

    expect(await screen.findByText('NO HABILITADO')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Aceptar ingreso' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Siguiente estudiante' })).toBeInTheDocument()
  })
})
