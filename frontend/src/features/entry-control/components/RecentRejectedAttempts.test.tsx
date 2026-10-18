import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { mockApiWith } from '@/test/http'
import { RecentRejectedAttempts } from './RecentRejectedAttempts'

const attempt = {
  id_intento: 30, estudiante: { id_estudiante: 19, cod_sis: '202309201', nombre_completo: 'Carlos Rocha' },
  ambiente: { id_ambiente: 5, nro_aula: '691A' }, motivo: 'IDENTIDAD_DUDOSA',
  observacion: null, registrado_en: '2026-10-16T14:00:00Z', reportado: false,
}

const props = { examId: 7, examName: 'Parcial', roomId: 5, refreshVersion: 0 }

describe('HU-042 últimos rechazos', () => {
  it('obtiene el estado reportado del servidor y lo conserva al remontar', async () => {
    const { calls } = mockApiWith(() => ({ body: { data: [{ ...attempt, reportado: true }] } }))
    const first = render(<RecentRejectedAttempts {...props} onReport={vi.fn()} />)
    expect(await screen.findByRole('button', { name: 'Ya reportado' })).toBeDisabled()
    first.unmount()
    render(<RecentRejectedAttempts {...props} onReport={vi.fn()} />)
    expect(await screen.findByRole('button', { name: 'Ya reportado' })).toBeDisabled()
    expect(calls).toHaveLength(2)
    expect(calls[0].url).toMatch(/\/intentos\?id_ambiente=5$/)
  })

  it('no permite reportar un intento sin estudiante identificado', async () => {
    mockApiWith(() => ({ body: { data: [{ ...attempt, estudiante: null }] } }))
    render(<RecentRejectedAttempts {...props} onReport={vi.fn()} />)
    expect(await screen.findByText('Sin estudiante identificado')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reportar incidencia' })).not.toBeInTheDocument()
  })

  it('abre el modal con los datos del intento seleccionado', async () => {
    mockApiWith(() => ({ body: { data: [attempt] } }))
    const onReport = vi.fn()
    render(<RecentRejectedAttempts {...props} onReport={onReport} />)
    await userEvent.click(await screen.findByRole('button', { name: 'Reportar incidencia' }))
    expect(onReport).toHaveBeenCalledWith(expect.objectContaining({ attemptId: 30, examId: 7, roomName: '691A' }))
  })

  it('recarga los rechazos al cambiar la versión', async () => {
    let reported = false
    const { calls } = mockApiWith(() => ({ body: { data: [{ ...attempt, reportado: reported }] } }))
    const { rerender } = render(<RecentRejectedAttempts {...props} onReport={vi.fn()} />)
    await screen.findByRole('button', { name: 'Reportar incidencia' })
    reported = true
    rerender(<RecentRejectedAttempts {...props} refreshVersion={1} onReport={vi.fn()} />)
    await waitFor(() => expect(screen.getByRole('button', { name: 'Ya reportado' })).toBeDisabled())
    expect(calls).toHaveLength(2)
  })
})