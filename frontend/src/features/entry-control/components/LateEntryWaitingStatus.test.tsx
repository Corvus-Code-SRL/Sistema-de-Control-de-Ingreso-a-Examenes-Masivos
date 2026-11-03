import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { makeLateEntryRequest } from '@/test/lateEntryFixtures'
import { LateEntryWaitingStatus } from './LateEntryWaitingStatus'

describe('LateEntryWaitingStatus', () => {
  it('mientras espera muestra la hora del servidor y no permite reenviar', () => {
    render(<LateEntryWaitingStatus request={makeLateEntryRequest()} onNext={vi.fn()} />)

    expect(screen.getByText('Esperando al docente')).toHaveClass('bg-warn-soft')
    expect(screen.getByText('Solicitud enviada a las 10:12. No deje ingresar todavía.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Enviar solicitud' })).toBeDisabled()
  })

  it('muestra exactamente «Ingreso autorizado» y permite seguir', async () => {
    const onNext = vi.fn()
    render(<LateEntryWaitingStatus request={makeLateEntryRequest({ estado: 'AUTORIZADA' })} onNext={onNext} />)

    expect(screen.getByText('Ingreso autorizado')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Enviar solicitud' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Siguiente estudiante' }))
    expect(onNext).toHaveBeenCalledTimes(1)
  })

  it('muestra exactamente «Solicitud rechazada por el docente»', () => {
    render(<LateEntryWaitingStatus request={makeLateEntryRequest({ estado: 'RECHAZADA' })} onNext={vi.fn()} />)

    expect(screen.getByText('Solicitud rechazada por el docente')).toBeInTheDocument()
  })
})
