import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderApp } from '@/test/renderApp'
import { distributionLoaded, mockDistributionBackend } from '@/test/roomDistributionBackend'
import { distributionExamFixture, distributionFixture, savedDistributionFixture } from '@/test/roomDistributionFixtures'
import { roomDistributionService } from '../services/roomDistributionService'
import { DISTRIBUTION_MESSAGES } from '../utils/roomDistributionMessages'

const PATH = '/examenes/distribucion'
afterEach(() => { window.localStorage.clear(); window.sessionStorage.clear(); vi.unstubAllGlobals() })
async function ready() {
  renderApp(PATH)
  await distributionLoaded()
}

describe('RoomDistributionPage — ruta y estados', () => {
  it.each(['docente', 'invitado'] as const)('el %s entra directamente y consulta sin pasar por el detalle', async (role) => {
    const api = mockDistributionBackend({ role })
    await ready()
    expect(screen.getByRole('heading', { name: 'Distribuir habilitados' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Examen' })).toBeInTheDocument()
    for (const label of ['Habilitados', 'No habilitados', 'Capacidad total', 'Asignados', 'Sin ambiente', 'Lugares faltantes']) {
      expect(screen.getByText(label, { selector: 'dt' })).toBeInTheDocument()
    }
    expect(api.calls.some((call) => /\/examenes\/12$/.test(call.url))).toBe(false)
  })
  it.each(['administrador', 'auxiliar'] as const)('el %s no obtiene acceso a la ruta de preparación', async (role) => {
    const api = mockDistributionBackend({ role })
    const app = renderApp(PATH)
    await waitFor(() => expect(app.pathname).not.toBe(PATH))
    expect(screen.queryByRole('heading', { name: 'Distribuir habilitados' })).not.toBeInTheDocument()
    expect(api.calls.some((call) => call.url.endsWith('/examenes/distribucion'))).toBe(false)
  })
  it('sin sesión redirige al login', async () => {
    mockDistributionBackend({ role: null })
    const app = renderApp(PATH)
    await waitFor(() => expect(app.pathname).toBe('/login'))
  })
  it('muestra vacío cuando no hay Programados', async () => {
    mockDistributionBackend({ exams: [] })
    renderApp(PATH)
    expect(await screen.findByText('No tiene exámenes Programados')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar distribución' })).not.toBeInTheDocument()
  })
  it('anuncia la carga mientras se consulta el selector', async () => {
    mockDistributionBackend()
    vi.spyOn(roomDistributionService, 'getExams').mockReturnValue(new Promise(() => undefined))
    renderApp(PATH)
    expect(await screen.findByText('Cargando exámenes Programados')).toBeInTheDocument()
  })
  it.each([403, 404, 500])('un GET %i muestra error y reintento', async (status) => {
    mockDistributionBackend({ getError: status })
    renderApp(PATH)
    const message = status === 404 ? DISTRIBUTION_MESSAGES.notFound
      : status === 500 ? DISTRIBUTION_MESSAGES.failure : 'No se puede consultar esta distribución.'
    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
  it('un ambiente único se consulta sin controles de edición', async () => {
    const data = savedDistributionFixture()
    mockDistributionBackend({ view: () => ({ ...data, editable: false,
      motivo_solo_lectura: 'Todos los habilitados quedan en el ambiente 691A.',
      ambientes: data.ambientes.slice(0, 1).map((room) => ({ ...room, capacidad: 6, asignados: 4 })),
      estudiantes: data.estudiantes.map((student) => ({ ...student, id_ambiente: 10 })),
    }) })
    await ready()
    expect(screen.getByText('Todos los habilitados quedan en el ambiente 691A.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Guardar distribución' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Mover a/ })).not.toBeInTheDocument()
  })
  it('capacidad insuficiente impide guardar y no promete asignación automática al abrir', async () => {
    mockDistributionBackend({ view: () => distributionFixture({ capacidad_total: 2, faltan_asientos: 2,
      ambientes: distributionFixture().ambientes.map((room) => ({ ...room, capacidad: 1 })),
    }) })
    await ready()
    expect(screen.getByText('Capacidad insuficiente')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Guardar distribución' })).toBeDisabled()
    expect(screen.queryByText(/se ubicarán automáticamente/)).not.toBeInTheDocument()
  })
  it('colaborador o control abierto conservan la vista sin acciones', async () => {
    mockDistributionBackend({ view: () => savedDistributionFixture({ editable: false,
      motivo_solo_lectura: 'El examen ya no permite cambios de preparación.',
    }) })
    await ready()
    expect(screen.getByText('Solo lectura')).toBeInTheDocument()
    expect(screen.queryByRole('group', { name: 'Acciones de distribución' })).not.toBeInTheDocument()
  })
  it('cero habilitados permite confirmar una lista vacía', async () => {
    const zero = distributionFixture({ total_habilitados: 0, estudiantes: [] })
    const api = mockDistributionBackend({ view: () => zero, save: () => ({ body: { data: zero, mensaje: 'Distribución guardada' } }) })
    await ready()
    expect(screen.getByText('Sin estudiantes habilitados')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Distribuir por apellido' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'Guardar distribución' }))
    await waitFor(() => expect(api.calls.some((call) => call.method === 'PUT')).toBe(true))
    expect(api.calls.find((call) => call.method === 'PUT')?.body).toMatchObject({ asignaciones: [] })
  })
})

describe('RoomDistributionPage — cambios locales', () => {
  it('permite ubicar manualmente a un estudiante sin ambiente y actualiza los contadores', async () => {
    const api = mockDistributionBackend()
    await ready()
    await userEvent.click(screen.getByRole('button', { name: /Asignar ambiente: Álvarez Ana/ }))
    await userEvent.click(screen.getByRole('menuitem', { name: /691A/ }))
    expect(within(screen.getByRole('region', { name: 'Ambiente 691A' })).getByText('Álvarez Ana')).toBeInTheDocument()
    expect(screen.getByText('Asignados', { selector: 'dt' }).nextElementSibling).toHaveTextContent('1')
    expect(screen.getByText('Sin ambiente', { selector: 'dt' }).nextElementSibling).toHaveTextContent('3')
    expect(screen.getByRole('button', { name: 'Guardar distribución' })).toBeDisabled()
    expect(api.calls.filter((call) => call.method === 'PUT')).toHaveLength(0)
  })
  it('aplica la propuesta del servidor y guarda explícitamente', async () => {
    const api = mockDistributionBackend()
    await ready()
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    await screen.findByText(/4 cambios sin guardar/)
    expect(api.calls.filter((call) => call.method === 'PUT')).toHaveLength(0)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar distribución' }))
    expect(await screen.findByText('Distribución guardada')).toBeInTheDocument()
    expect(screen.queryByText(/cambios sin guardar/)).not.toBeInTheDocument()
    expect(api.calls.filter((call) => call.method === 'PUT')).toHaveLength(1)
  })
  it('mover a un ambiente lleno muestra el aviso y no modifica el aforo', async () => {
    mockDistributionBackend({ view: () => savedDistributionFixture() })
    await ready()
    await userEvent.click(screen.getByRole('button', { name: /Mover a 691B: Álvarez/ }))
    expect(screen.getByRole('region', { name: 'Ambiente 691B' })).toHaveTextContent('3 / 3')
    await userEvent.click(screen.getByRole('button', { name: /Mover a 691B: Gutiérrez/ }))
    expect(await screen.findByText('El ambiente ya está lleno')).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Ambiente 691B' })).toHaveTextContent('3 / 3')
    expect(within(screen.getByRole('region', { name: 'Ambiente 691A' })).getByText('Gutiérrez Andrea')).toBeInTheDocument()
  })
  it('una nueva propuesta pide confirmación y se puede cancelar sin perder ajustes', async () => {
    const api = mockDistributionBackend({ view: () => savedDistributionFixture() })
    await ready()
    await userEvent.click(screen.getByRole('button', { name: /Mover a 691B: Álvarez/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    expect(screen.getByRole('dialog', { name: '¿Reemplazar los ajustes actuales?' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Seguir editando' }))
    expect(api.calls.filter((call) => call.url.endsWith('/propuesta'))).toHaveLength(0)
    expect(screen.getByRole('region', { name: 'Ambiente 691B' })).toHaveTextContent('Álvarez Ana')
  })
  it('descartar requiere confirmación y restaura la distribución anterior', async () => {
    mockDistributionBackend({ view: () => savedDistributionFixture() })
    await ready()
    await userEvent.click(screen.getByRole('button', { name: /Mover a 691B: Álvarez/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Descartar cambios' }))
    const dialog = screen.getByRole('dialog', { name: '¿Descartar los cambios?' })
    await userEvent.click(within(dialog).getByRole('button', { name: 'Descartar cambios' }))
    expect(within(screen.getByRole('region', { name: 'Ambiente 691A' })).getByText('Álvarez Ana')).toBeInTheDocument()
    expect(screen.queryByText(/cambio sin guardar/)).not.toBeInTheDocument()
  })
  it('409 conserva el borrador y recargar requiere confirmación', async () => {
    const api = mockDistributionBackend({ save: () => ({ status: 409, body: { message: 'La distribución cambió.' } }) })
    await ready()
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    await screen.findByText(/4 cambios sin guardar/)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar distribución' }))
    const reload = await screen.findByRole('button', { name: 'Recargar distribución' })
    expect(screen.getByRole('region', { name: 'Ambiente 691A' })).toHaveTextContent('Álvarez Ana')
    expect(screen.getByRole('button', { name: 'Guardar distribución' })).toBeDisabled()
    await userEvent.click(reload)
    expect(screen.getByRole('dialog', { name: '¿Recargar y revisar?' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Seguir editando' }))
    expect(api.calls.filter((call) => call.method === 'PUT')).toHaveLength(1)
  })
  it('422 muestra errors en español y mantiene los cambios locales', async () => {
    mockDistributionBackend({ save: () => ({ status: 422, body: { message: 'The given data was invalid.',
      errors: { asignaciones: ['La nómina del examen cambió.'] },
    } }) })
    await ready()
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    await screen.findByText(/4 cambios sin guardar/)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar distribución' }))
    expect(await screen.findByText('La nómina del examen cambió.')).toBeInTheDocument()
    expect(screen.getByText(/4 cambios sin guardar/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Recargar distribución' })).toBeInTheDocument()
  })
  it('protege beforeunload mientras hay cambios y libera la protección al descartar', async () => {
    mockDistributionBackend()
    await ready()
    await userEvent.click(screen.getByRole('button', { name: 'Distribuir por apellido' }))
    await screen.findByText(/4 cambios sin guardar/)
    const unload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(unload)
    expect(unload.defaultPrevented).toBe(true)
    await userEvent.click(screen.getByRole('button', { name: 'Descartar cambios' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Descartar cambios' }))
    const clean = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(clean)
    expect(clean.defaultPrevented).toBe(false)
  })
  it('con el control abierto deja de usar la distribución tras confirmar', async () => {
    const opened = distributionExamFixture({ estado: 'EN_CURSO', editable: false, puede_dejar_de_usar: true })
    const stopped = savedDistributionFixture({
      editable: false, motivo_solo_lectura: 'El docente dejó de usar esta distribución.',
      examen: distributionExamFixture({ estado: 'EN_CURSO', editable: false, distribucion_suspendida: true }),
    })
    const api = mockDistributionBackend({
      exams: [opened],
      view: () => savedDistributionFixture({ editable: false, motivo_solo_lectura: 'Solo lectura.', examen: opened }),
      stop: () => ({ body: { data: stopped } }),
    })
    renderApp('/examenes/distribucion')

    await userEvent.click(await screen.findByRole('button', { name: 'Dejar de usar la distribución' }))
    const dialog = await screen.findByRole('dialog', { name: '¿Dejar de usar la distribución?' })
    expect(api.calls.some((call) => call.url.endsWith('/dejar-de-usar'))).toBe(false)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Dejar de usar' }))

    expect(await screen.findByText('El docente dejó de usar esta distribución.')).toBeInTheDocument()
    expect(api.calls.filter((call) => call.url.endsWith('/dejar-de-usar') && call.method === 'POST')).toHaveLength(1)
    expect(screen.queryByRole('button', { name: 'Dejar de usar la distribución' })).not.toBeInTheDocument()
  })
})
