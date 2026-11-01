import type { ReactNode } from 'react'
import { act, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { examInProgressService } from '@/features/exams'
import { ApiError } from '@/lib/api-client'
import {
  makeExamInProgress, makeLateEntryRequest, makeLateEntryVerification, makeOtherExamWarning,
} from '@/test/lateEntryFixtures'
import { renderWithRouter } from '@/test/render'
import { LATE_ENTRY_POLL_MS } from '../constants'
import { lateEntryService } from '../services/lateEntryService'
import { LateEntryPage } from './LateEntryPage'

vi.mock('@/components/layout/AppShell', () => ({
  AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>,
}))

const hidden = Object.getOwnPropertyDescriptor(document, 'hidden')

async function renderPage(route = '/examenes/7/retrasos') {
  renderWithRouter(<LateEntryPage />, { route, path: '/examenes/:examId/retrasos' })
  await act(async () => { await Promise.resolve() })
}

/** En flex-col, `flex-1` sin prefijo gana al `h-11` y aplasta el botón en móvil. */
function expectTouchButton(button: HTMLElement) {
  expect(button).toHaveClass('h-11', 'w-full')
  expect(button.className.split(/\s+/)).not.toContain('flex-1')
}

async function verifyStudent(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Código SIS'), '202301903')
  await user.type(screen.getByLabelText('Carnet de identidad (CI)'), '9342145')
  await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
}

beforeEach(() => {
  Object.defineProperty(document, 'hidden', { configurable: true, value: false })
  vi.spyOn(lateEntryService, 'list').mockResolvedValue([])
  vi.spyOn(lateEntryService, 'search').mockResolvedValue([])
  vi.spyOn(lateEntryService, 'searchByIdentifier').mockResolvedValue([])
})

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
  if (hidden) Object.defineProperty(document, 'hidden', hidden)
})

describe('HU-037: registrar estudiante con retraso', () => {
  it('el docente verifica, elige el ambiente y registra', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    const verify = vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification())
    const register = vi.spyOn(lateEntryService, 'register').mockResolvedValue({
      id_estudiante: 501, estado_ingreso: 'CON_RETRASO', hora_ingreso: '10:30', id_ambiente: 6,
    })
    await renderPage()

    await verifyStudent(user)

    expect(verify).toHaveBeenCalledWith(7, { cod_sis: '202301903', ci: '9342145' })
    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()
    expect(screen.getByLabelText('Ambiente de ingreso')).toHaveTextContent('692B')
    expect(screen.queryByText(/Intento registrado/)).not.toBeInTheDocument()

    for (const name of ['Cancelar', 'Registrar con retraso']) {
      expectTouchButton(screen.getByRole('button', { name }))
    }
    // La fila se apila según el ancho de la tarjeta, no de la pantalla.
    expect(screen.getByRole('button', { name: 'Cancelar' }).parentElement).toHaveClass('flex-col', '@xl:flex-row')
    expect(screen.getByRole('heading', { name: 'AUTORIZADO' })).toHaveClass('text-ok-fg')

    await user.click(screen.getByRole('button', { name: 'Registrar con retraso' }))

    expect(register).toHaveBeenCalledWith(7, { id_estudiante: 501, id_ambiente: 6, ci: '9342145' })
    expect(await screen.findByText('Ingreso con retraso registrado: Lucía Fernández Rojas a las 10:30.')).toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
    expect(screen.getByLabelText('Código SIS')).toHaveFocus()
  })

  it('el docente ve en línea la solicitud pendiente del estudiante y la autoriza', async () => {
    const user = userEvent.setup()
    const pending = makeLateEntryRequest()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification({ solicitud_pendiente: pending }))
    const approve = vi.spyOn(lateEntryService, 'approve').mockResolvedValue({ ...pending, estado: 'AUTORIZADA' })
    const register = vi.spyOn(lateEntryService, 'register')
    await renderPage()
    await verifyStudent(user)

    expect(await screen.findByText(/Este estudiante tiene una solicitud pendiente/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar con retraso' })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Autorizar: Lucía Fernández Rojas' }))

    expect(approve).toHaveBeenCalledWith(7, 31, false)
    expect(register).not.toHaveBeenCalled()
    expect(await screen.findByText('Ingreso autorizado')).toBeInTheDocument()
  })

  it('el veredicto negativo muestra el motivo del servidor y no permite escribir', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification({
      veredicto: 'DUPLICADO', autorizado: false, motivo: 'Ya ingresó a las 10:01 por el 691A',
    }))
    await renderPage()
    await verifyStudent(user)

    expect(await screen.findByText('Ya ingresó a las 10:01 por el 691A')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar con retraso' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Siguiente estudiante' })).toBeInTheDocument()
  })

  it('el auxiliar envía la solicitud, espera y ve la decisión del docente', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    const sent = makeLateEntryRequest()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(
      makeExamInProgress({ rol_controlador: 'AUXILIAR', id_ambiente_asignado: 6 })
    )
    const verify = vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification())
    const request = vi.spyOn(lateEntryService, 'request').mockResolvedValue(sent)
    await renderPage()
    await verifyStudent(user)

    expect(verify).toHaveBeenCalledWith(7, { cod_sis: '202301903', ci: '9342145', id_ambiente: 6 })
    await user.click(await screen.findByRole('button', { name: 'Enviar solicitud' }))

    expect(request).toHaveBeenCalledWith(7, { id_estudiante: 501, id_ambiente: 6, ci: '9342145' })
    expect(await screen.findByText('Esperando al docente')).toBeInTheDocument()
    expect(screen.getByText('Solicitud enviada a las 10:12. No deje ingresar todavía.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Enviar solicitud' })).toBeDisabled()

    vi.mocked(lateEntryService.list).mockResolvedValue([{ ...sent, estado: 'AUTORIZADA' }])
    await act(async () => { await vi.advanceTimersByTimeAsync(LATE_ENTRY_POLL_MS) })

    expect(screen.getAllByText('Ingreso autorizado').length).toBeGreaterThan(0)
    expect(screen.queryByText('Esperando al docente')).not.toBeInTheDocument()
  })

  it('el auxiliar sin ambiente de control no puede verificar', async () => {
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress({ rol_controlador: 'AUXILIAR' }))
    await renderPage()

    expect(await screen.findByRole('alert')).toHaveTextContent('No tiene un ambiente de control asignado.')
    expect(screen.getByLabelText('Código SIS')).toBeDisabled()
  })

  it('un examen finalizado queda de solo lectura, con sus solicitudes', async () => {
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress({ estado: 'FINALIZADO' }))
    vi.mocked(lateEntryService.list).mockResolvedValue([makeLateEntryRequest()])
    await renderPage()

    expect(await screen.findByText('El examen no está en curso')).toBeInTheDocument()
    expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()
    const list = await screen.findByRole('list', { name: 'Solicitudes de ingreso con retraso' })
    expect(within(list).getByText('Lucía Fernández Rojas')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Autorizar/ })).not.toBeInTheDocument()
  })

  it('el examen que se cierra durante el registro vuelve a cargarse', async () => {
    const user = userEvent.setup()
    const get = vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    vi.spyOn(lateEntryService, 'verify').mockRejectedValue(
      new ApiError(409, 'Solo puede registrar ingresos con retraso mientras el examen está en curso.')
    )
    await renderPage()
    get.mockResolvedValue(makeExamInProgress({ estado: 'FINALIZADO' }))
    await verifyStudent(user)

    expect(await screen.findByText('El examen no está en curso')).toBeInTheDocument()
    expect(get).toHaveBeenCalledTimes(2)
  })

  it('un id de examen no numérico no consulta la API', async () => {
    const get = vi.spyOn(examInProgressService, 'get')
    await renderPage('/examenes/abc/retrasos')

    expect(screen.getByRole('alert')).toHaveTextContent('Examen no válido.')
    expect(get).not.toHaveBeenCalled()
  })

  it('sugiere por nombre durante EN_CURSO con la búsqueda de HU-037 y completa el SIS', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    const search = vi.mocked(lateEntryService.search).mockResolvedValue([
      { id_estudiante: 501, cod_sis: '202301903', nombre_completo: 'Lucía Fernández Rojas' },
    ])
    await renderPage()

    await user.type(await screen.findByLabelText('Buscar estudiante por nombre'), 'lucia')
    await user.click(await screen.findByRole('button', { name: /Lucía Fernández Rojas/ }))

    expect(search).toHaveBeenCalledWith(7, 'lucia', expect.any(AbortSignal))
    expect(screen.getByLabelText('Código SIS')).toHaveValue('202301903')
  })

  it('«Cancelar» vuelve a la identificación en la misma vista', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification())
    await renderPage()
    await verifyStudent(user)

    await user.click(await screen.findByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByText('AUTORIZADO')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveFocus()
    expect(screen.getByRole('link', { name: 'Volver a la lista de exámenes' })).toHaveAttribute('href', '/retrasos')
  })

  it('con otro examen el docente ve la advertencia y registra de todos modos tras confirmar', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification({
      otro_examen: makeOtherExamWarning(),
    }))
    const register = vi.spyOn(lateEntryService, 'register').mockResolvedValue({
      id_estudiante: 501, estado_ingreso: 'CON_RETRASO', hora_ingreso: '10:30', id_ambiente: 6,
    })
    await renderPage()
    await verifyStudent(user)

    expect(await screen.findByText('Ingreso en otro examen')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'AUTORIZADO' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'VERIFIQUE SU IDENTIDAD' })).toHaveClass('text-warn-fg')
    expect(screen.getByText(/Compare el nombre y el código SIS/)).toHaveClass('text-danger-fg')
    expect(screen.getByText(
      'Ya ingresó a Parcial de Física con Ana Rojas a las 08:05 por el 691A. Verifique su identidad antes de continuar.'
    )).toBeInTheDocument()
    expectTouchButton(screen.getByRole('button', { name: 'Rechazar' }))
    await user.click(screen.getByRole('button', { name: 'Registrar de todos modos' }))

    expect(register).not.toHaveBeenCalled()
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Registrar de todos modos' }))

    expect(register).toHaveBeenCalledWith(7, {
      id_estudiante: 501, id_ambiente: 6, ci: '9342145', confirmar_otro_examen: true,
    })
  })

  it('«Rechazar» ante la advertencia vuelve a la identificación sin escribir', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification({
      otro_examen: makeOtherExamWarning(),
    }))
    const register = vi.spyOn(lateEntryService, 'register')
    await renderPage()
    await verifyStudent(user)

    await user.click(await screen.findByRole('button', { name: 'Rechazar' }))

    expect(register).not.toHaveBeenCalled()
    expect(screen.queryByText('Ingreso en otro examen')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveFocus()
  })

  it('con otro examen el auxiliar envía la solicitud de todos modos', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(
      makeExamInProgress({ rol_controlador: 'AUXILIAR', id_ambiente_asignado: 6 })
    )
    vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification({
      otro_examen: makeOtherExamWarning(),
    }))
    const request = vi.spyOn(lateEntryService, 'request').mockResolvedValue(makeLateEntryRequest())
    await renderPage()
    await verifyStudent(user)

    const send = await screen.findByRole('button', { name: 'Enviar solicitud de todos modos' })
    for (const button of [send, screen.getByRole('button', { name: 'Rechazar' })]) {
      expectTouchButton(button)
      expect(button).toHaveClass('@xl:flex-1')
      expect(button.className.split(/\s+/)).not.toContain('sm:flex-1')
    }
    expect(send.closest('section')).toHaveClass('@container')
    await user.click(send)

    expect(request).toHaveBeenCalledWith(7, {
      id_estudiante: 501, id_ambiente: 6, ci: '9342145', confirmar_otro_examen: true,
    })
  })

  it('el docente ve la advertencia en la solicitud pendiente y autoriza confirmándola', async () => {
    const user = userEvent.setup()
    const pending = makeLateEntryRequest({ otro_examen: makeOtherExamWarning() })
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    vi.mocked(lateEntryService.list).mockResolvedValue([pending])
    const approve = vi.spyOn(lateEntryService, 'approve').mockResolvedValue({ ...pending, estado: 'AUTORIZADA' })
    await renderPage()

    expect((await screen.findAllByText('Ingreso en otro examen')).length).toBeGreaterThan(0)
    await user.click(screen.getAllByRole('button', { name: 'Autorizar: Lucía Fernández Rojas' })[0])

    expect(approve).toHaveBeenCalledWith(7, 31, true)
  })

  it('al llegar el fin programado reemplaza el formulario por el aviso y la vuelta a la lista', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress({
      hora_servidor_en: '2026-10-31T15:59:00+00:00',
      fin_programado_en: '2026-10-31T16:00:00+00:00',
    }))
    await renderPage()
    expect(await screen.findByLabelText('Código SIS')).toBeInTheDocument()

    await act(async () => { await vi.advanceTimersByTimeAsync(60_000) })

    expect(screen.getByText('El tiempo del examen terminó; ya no se pueden registrar ingresos con retraso.'))
      .toBeInTheDocument()
    expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver a la lista' })).toHaveAttribute('href', '/retrasos')
  })

  it('con el fin programado ya pasado no muestra el formulario', async () => {
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress({
      hora_servidor_en: '2026-10-31T16:05:00+00:00',
    }))
    await renderPage()

    expect(await screen.findByText('Tiempo del examen cumplido')).toBeInTheDocument()
    expect(screen.queryByLabelText('Código SIS')).not.toBeInTheDocument()
  })

  it('carga el examen una vez y lo recarga solo al volver a la pestaña', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const get = vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    await renderPage()
    await screen.findByLabelText('Código SIS')

    await act(async () => { await vi.advanceTimersByTimeAsync(LATE_ENTRY_POLL_MS * 2) })
    expect(get).toHaveBeenCalledTimes(1)
    expect(vi.mocked(lateEntryService.list).mock.calls.length).toBeGreaterThan(1)

    get.mockResolvedValue(makeExamInProgress({ estado: 'FINALIZADO' }))
    await act(async () => { document.dispatchEvent(new Event('visibilitychange')) })

    expect(get).toHaveBeenCalledTimes(2)
    expect(await screen.findByText('El examen no está en curso')).toBeInTheDocument()
  })

  it('deja de consultar las solicitudes cuando el examen pasa a FINALIZADO', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const get = vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    const list = vi.mocked(lateEntryService.list)
    await renderPage()
    await screen.findByLabelText('Código SIS')
    await act(async () => { await vi.advanceTimersByTimeAsync(LATE_ENTRY_POLL_MS) })
    expect(list.mock.calls.length).toBeGreaterThan(1)

    get.mockResolvedValue(makeExamInProgress({ estado: 'FINALIZADO' }))
    await act(async () => { document.dispatchEvent(new Event('visibilitychange')) })
    expect(await screen.findByText('El examen no está en curso')).toBeInTheDocument()
    const callsWhenClosed = list.mock.calls.length

    await act(async () => { await vi.advanceTimersByTimeAsync(LATE_ENTRY_POLL_MS * 3) })
    expect(list).toHaveBeenCalledTimes(callsWhenClosed)
  })

  it('deja de consultar las solicitudes al llegar el fin programado', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress({
      hora_servidor_en: '2026-10-31T15:59:00+00:00',
      fin_programado_en: '2026-10-31T16:00:00+00:00',
    }))
    const list = vi.mocked(lateEntryService.list)
    await renderPage()
    await screen.findByLabelText('Código SIS')

    await act(async () => { await vi.advanceTimersByTimeAsync(60_000) })
    expect(screen.getByText('Tiempo del examen cumplido')).toBeInTheDocument()
    expect(list.mock.calls.length).toBeGreaterThan(1)
    const callsAtDeadline = list.mock.calls.length

    await act(async () => { await vi.advanceTimersByTimeAsync(LATE_ENTRY_POLL_MS * 3) })
    expect(list).toHaveBeenCalledTimes(callsAtDeadline)
  })
})
