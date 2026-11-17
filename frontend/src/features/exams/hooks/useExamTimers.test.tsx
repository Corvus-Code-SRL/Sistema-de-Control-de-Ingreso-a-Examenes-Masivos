import { act, fireEvent, screen } from '@testing-library/react'
import { Link } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { renderWithRouter } from '@/test/render'
import { examTimerService, type ExamTimer } from '../services/examTimerService'
import { ExamTimersProvider, useExamTimers } from './useExamTimers'

const initialTime = '2026-11-17T18:20:00Z'
const exam: ExamTimer = {
  id_examen: 7, nombre_examen: 'Parcial', estado: 'EN_CURSO', fecha: '2026-11-17',
  hora_inicio: '14:00', hora_fin: '16:00', duracion: 120, fin_permitido_en: '2026-11-17T20:00:00Z',
  cronometro_iniciado_en: initialTime, cronometro_vence_en: '2026-11-17T18:40:00Z',
}

function Probe() {
  const { exams, accept } = useExamTimers()
  return <>
    <span>{exams.length} exámenes</span>
    <Link to="/otra">Navegar</Link>
    <button onClick={() => accept({ examen: exam, hora_servidor_en: initialTime })}>Guardar</button>
  </>
}

async function advance(ms: number) {
  await act(async () => { await vi.advanceTimersByTimeAsync(ms) })
}

function mount(exams: ExamTimer[]) {
  const list = vi.spyOn(examTimerService, 'list').mockImplementation(async () => ({
    examenes: exams, hora_servidor_en: new Date().toISOString(),
  }))
  renderWithRouter(<ExamTimersProvider><Probe /></ExamTimersProvider>)
  return list
}

describe('Sondeo del cronómetro (HU-038)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance', 'Date'] })
    vi.setSystemTime(Date.parse(initialTime))
  })
  afterEach(() => vi.useRealTimers())

  it.each<{ exams: ExamTimer[] }>([
    { exams: [] },
    { exams: [{ ...exam, cronometro_iniciado_en: null, cronometro_vence_en: null }] },
  ])(
    'sin cronómetros activos solo consulta al navegar o recuperar foco', async ({ exams }) => {
      const list = mount(exams)
      await advance(0)
      expect(screen.getByText(`${exams.length} exámenes`)).toBeInTheDocument()
      await advance(60_000)
      expect(list).toHaveBeenCalledTimes(1)
      fireEvent.click(screen.getByRole('link', { name: 'Navegar' }))
      await advance(0)
      expect(list).toHaveBeenCalledTimes(2)
      fireEvent.focus(window)
      await advance(0)
      expect(list).toHaveBeenCalledTimes(3)
      fireEvent(document, new Event('visibilitychange'))
      await advance(0)
      expect(list).toHaveBeenCalledTimes(4)
    },
  )

  it('consulta cada 15 segundos y deja de sondear al llegar a cero', async () => {
    const list = mount([{ ...exam, cronometro_vence_en: '2026-11-17T18:20:31Z' }])
    await advance(0)
    await advance(14_999)
    expect(list).toHaveBeenCalledTimes(1)
    await advance(1)
    expect(list).toHaveBeenCalledTimes(2)
    await advance(15_000)
    expect(list).toHaveBeenCalledTimes(3)
    await advance(60_000)
    expect(list).toHaveBeenCalledTimes(3)
  })

  it('refresca después de guardar y activa el sondeo', async () => {
    const list = mount([{ ...exam, cronometro_iniciado_en: null, cronometro_vence_en: null }])
    await advance(0)
    list.mockResolvedValue({ examenes: [exam], hora_servidor_en: initialTime })
    fireEvent.click(screen.getByRole('button', { name: 'Guardar' }))
    await advance(0)
    expect(list).toHaveBeenCalledTimes(2)
    await advance(15_000)
    expect(list).toHaveBeenCalledTimes(3)
  })

  it('respeta Retry-After incluso al navegar, enfocar o guardar', async () => {
    const list = mount([exam])
    await advance(0)
    list.mockRejectedValueOnce(new ApiError(429, 'Demasiadas solicitudes', {}, undefined, { retryAfter: 45 }))
    await advance(15_000)
    expect(list).toHaveBeenCalledTimes(2)
    fireEvent.focus(window)
    fireEvent.click(screen.getByRole('link', { name: 'Navegar' }))
    fireEvent.click(screen.getByRole('button', { name: 'Guardar' }))
    await advance(44_999)
    expect(list).toHaveBeenCalledTimes(2)
    await advance(1)
    expect(list).toHaveBeenCalledTimes(3)
  })

  it('al reducir el tiempo detiene el sondeo aunque falle el refresco', async () => {
    const list = mount([{ ...exam, cronometro_vence_en: '2026-11-17T19:00:00Z' }])
    await advance(0)
    list.mockRejectedValue(new TypeError('Sin conexión'))
    fireEvent.click(screen.getByRole('button', { name: 'Guardar' }))
    await advance(0)
    await advance(20 * 60_000)
    const completed = list.mock.calls.length
    await advance(60_000)
    expect(list).toHaveBeenCalledTimes(completed)
  })
})
