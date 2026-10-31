import type { ReactNode } from 'react'
import { act, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { examInProgressService } from '@/features/exams'
import { ApiError } from '@/lib/api-client'
import { makeExamInProgress, makeLateEntryRequest, makeLateEntryVerification } from '@/test/lateEntryFixtures'
import { renderWithRouter } from '@/test/render'
import { ENTRY_CONTROL_POLL_MS } from '../constants'
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

async function verifyStudent(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Código SIS'), '202301903')
  await user.type(screen.getByLabelText('Carnet de identidad (CI)'), '9342145')
  await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
}

beforeEach(() => {
  Object.defineProperty(document, 'hidden', { configurable: true, value: false })
  vi.spyOn(lateEntryService, 'list').mockResolvedValue([])
})

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
  if (hidden) Object.defineProperty(document, 'hidden', hidden)
})

describe('HU-037: registrar estudiante con retraso', () => {
  it('el docente verifica sin sugerencias, elige el ambiente y registra', async () => {
    const user = userEvent.setup()
    vi.spyOn(examInProgressService, 'get').mockResolvedValue(makeExamInProgress())
    const verify = vi.spyOn(lateEntryService, 'verify').mockResolvedValue(makeLateEntryVerification())
    const register = vi.spyOn(lateEntryService, 'register').mockResolvedValue({
      id_estudiante: 501, estado_ingreso: 'CON_RETRASO', hora_ingreso: '10:30', id_ambiente: 6,
    })
    await renderPage()

    expect(screen.queryByLabelText('Buscar por nombre')).not.toBeInTheDocument()
    await verifyStudent(user)

    expect(verify).toHaveBeenCalledWith(7, { cod_sis: '202301903', ci: '9342145' })
    expect(await screen.findByText('AUTORIZADO')).toBeInTheDocument()
    expect(screen.getByLabelText('Ambiente de ingreso')).toHaveTextContent('692B')
    expect(screen.queryByText(/Intento registrado/)).not.toBeInTheDocument()

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

    expect(approve).toHaveBeenCalledWith(7, 31)
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
    await act(async () => { await vi.advanceTimersByTimeAsync(ENTRY_CONTROL_POLL_MS) })

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
})
