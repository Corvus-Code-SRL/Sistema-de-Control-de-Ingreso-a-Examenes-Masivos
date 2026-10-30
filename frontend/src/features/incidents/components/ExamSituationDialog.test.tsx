import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { ExamSituationDialog } from './ExamSituationDialog'
import { ExamSituationsPanel } from './ExamSituationsPanel'
import type { ExamSituation, ExamSituationContext } from '../types/exam-situation.types'

const context: ExamSituationContext = {
  examId: 7, examName: 'Parcial de programación',
  student: { id_estudiante: 19, cod_sis: '202309201', nombre_completo: 'Carlos Rocha' },
  room: { id_ambiente: 5, nro_aula: '691A' },
  rooms: [{ id_ambiente: 5, nro_aula: '691A' }, { id_ambiente: 6, nro_aula: '691B' }],
  serverTime: '2026-10-26T14:40:00Z',
}

const situation: ExamSituation = {
  id_situacion: 1, tipo: 'CAMBIO_AMBIENTE', motivo: 'Ruido', registrado_en: context.serverTime,
  estudiante: context.student, ambiente_origen: context.room, ambiente_destino: context.rooms[1],
  registrante: { id_usuario: 'actor', nombre_completo: 'Controlador de prueba' },
}

function setup(options: { context?: ExamSituationContext; status?: number; body?: unknown } = {}) {
  const { calls } = mockApiWith(() => ({
    status: options.status ?? 201,
    body: options.body ?? { data: situation, mensaje: 'Situación registrada' },
  }))
  const onClose = vi.fn()
  const onRegistered = vi.fn()
  render(<ExamSituationDialog context={options.context ?? context} onClose={onClose} onRegistered={onRegistered} />)
  return { calls, onClose, onRegistered, user: userEvent.setup() }
}

async function choose(user: ReturnType<typeof userEvent.setup>, field: string, value: string) {
  await user.click(screen.getByRole('combobox', { name: field }))
  await user.click(screen.getByRole('option', { name: value }))
}
const posts = (calls: ReturnType<typeof mockApiWith>['calls']) => calls.filter((request) => request.method === 'POST')
const payload = (calls: ReturnType<typeof mockApiWith>['calls']) => posts(calls)[0].body

async function fillRoomChange(user: ReturnType<typeof userEvent.setup>, reason = 'Ruido') {
  await choose(user, 'Ambiente de destino', '691B')
  await user.type(screen.getByLabelText('Motivo'), reason)
}

describe('HU-057 guía actualizada: solo ambiente e identificación', () => {
  it('precarga estudiante, ambiente y hora de Bolivia sin pedir tipos de falta', () => {
    const { calls } = setup()
    expect(screen.getByText('Carlos Rocha · 202309201')).toBeInTheDocument()
    expect(screen.getByText('10:40')).toBeInTheDocument()
    expect(calls).toHaveLength(0)
  })
  it('ofrece exactamente dos tipos y no contiene expulsión ni evidencia', async () => {
    const { user } = setup()
    await user.click(screen.getByRole('combobox', { name: 'Tipo de situación' }))
    expect(screen.getAllByRole('option')).toHaveLength(2)
    expect(screen.getByRole('option', { name: 'Cambio de ambiente' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Problema de identificación' })).toBeInTheDocument()
    expect(screen.queryByText('Expulsión')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Evidencia (opcional)')).not.toBeInTheDocument()
  })
  it('no ofrece el aula actual como destino', async () => {
    const { user } = setup()
    await user.click(screen.getByRole('combobox', { name: 'Ambiente de destino' }))
    expect(screen.queryByRole('option', { name: '691A' })).not.toBeInTheDocument()
    expect(screen.getByRole('option', { name: '691B' })).toBeInTheDocument()
  })
  it('un cambio sin motivo presenta el texto exacto del CA-5', async () => {
    const { user, calls } = setup()
    await choose(user, 'Ambiente de destino', '691B')
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(screen.getByText('Indique el motivo del cambio.')).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(0)
  })
  it('un motivo breve válido no hereda el mínimo de identificación', async () => {
    const { user, calls, onRegistered } = setup()
    await fillRoomChange(user)
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    await waitFor(() => expect(onRegistered).toHaveBeenCalledOnce())
    expect(payload(calls)).toEqual({ tipo: 'CAMBIO_AMBIENTE', motivo: 'Ruido', id_ambiente_destino: 6 })
    expect(posts(calls)[0].headers['Content-Type']).toBe('application/json')
  })
  it('exige destino para el cambio de ambiente', async () => {
    const { user, calls } = setup()
    await user.type(screen.getByLabelText('Motivo'), 'Ruido')
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(screen.getByText('Seleccione otro ambiente de este examen.')).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(0)
  })
  it('un solo ambiente deshabilita traslado y permite identificación', async () => {
    const { user } = setup({ context: { ...context, rooms: [context.room] } })
    expect(screen.getByLabelText('Descripción')).toBeInTheDocument()
    await user.click(screen.getByRole('combobox', { name: 'Tipo de situación' }))
    expect(screen.getByRole('option', { name: 'Cambio de ambiente' })).toHaveAttribute('aria-disabled', 'true')
  })
  it('identificación con menos de diez caracteres muestra el CA-7', async () => {
    const { user, calls } = setup()
    await choose(user, 'Tipo de situación', 'Problema de identificación')
    await user.type(screen.getByLabelText('Descripción'), 'Duda')
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(screen.getByText('Describa lo ocurrido (mínimo 10 caracteres).')).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(0)
  })
  it('identificación envía JSON sin destino, actor, estado, reporte ni evidencia', async () => {
    const { user, calls, onRegistered } = setup({ body: { data: { ...situation, tipo: 'IDENTIFICACION', ambiente_destino: null }, mensaje: 'Situación registrada' } })
    await choose(user, 'Tipo de situación', 'Problema de identificación')
    await user.type(screen.getByLabelText('Descripción'), '  Fotografía deteriorada.  ')
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    await waitFor(() => expect(onRegistered).toHaveBeenCalledOnce())
    expect(payload(calls)).toEqual({ tipo: 'IDENTIFICACION', motivo: 'Fotografía deteriorada.' })
    expect(screen.getByText(/El estudiante seguirá presente/)).toBeInTheDocument()
  })
  it('cancelar no envía petición', async () => {
    const { user, calls, onClose } = setup()
    await user.type(screen.getByLabelText('Motivo'), 'Cambio por ruido')
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))
    expect(onClose).toHaveBeenCalledOnce()
    expect(calls).toHaveLength(0)
  })
  it('muestra el error 422 del backend junto al campo', async () => {
    const { user, onRegistered } = setup({ status: 422, body: { errors: { id_ambiente_destino: ['Ese ambiente no pertenece al examen.'] } } })
    await fillRoomChange(user)
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText('Ese ambiente no pertenece al examen.')).toBeInTheDocument()
    expect(onRegistered).not.toHaveBeenCalled()
  })
  it('el cierre concurrente bloquea la escritura y no anuncia éxito', async () => {
    const { user, onRegistered } = setup({ status: 409, body: { message: 'El examen ya finalizó.' } })
    await fillRoomChange(user)
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText(/El examen ya finalizó/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Registrar situación' })).toBeDisabled()
    expect(onRegistered).not.toHaveBeenCalled()
  })
  it('un fallo no confirmado no reintenta el POST automáticamente', async () => {
    const { user, calls, onRegistered } = setup({ status: 500, body: {} })
    await fillRoomChange(user)
    await user.click(screen.getByRole('button', { name: 'Registrar situación' }))
    expect(await screen.findByText(/revise Situaciones antes de volver a registrar/)).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(1)
    expect(onRegistered).not.toHaveBeenCalled()
  })
  it('el panel consulta el filtro de aula y muestra el vacío', async () => {
    const { calls } = mockApiWith(() => ({ body: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } }))
    render(<ExamSituationsPanel examId={7} roomId={5} refreshKey={0} />)
    expect(await screen.findByText('No se registraron situaciones en este examen.')).toBeInTheDocument()
    expect(calls[0].url).toContain('id_ambiente=5')
  })
  it('el panel muestra tipo, origen, destino, hora y registrante', async () => {
    mockApiWith(() => ({ body: { data: [situation], meta: { current_page: 1, last_page: 1, total: 1 } } }))
    render(<ExamSituationsPanel examId={7} refreshKey={0} />)
    expect(await screen.findByText('Cambio desde 691A a 691B')).toBeInTheDocument()
    expect(screen.getByText('Registrado por Controlador de prueba')).toBeInTheDocument()
    expect(screen.getByText('10:40')).toBeInTheDocument()
    expect(screen.queryByText(/Revisión:/)).not.toBeInTheDocument()
  })
})