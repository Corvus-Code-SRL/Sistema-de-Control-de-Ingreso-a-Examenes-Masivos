import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { LateEntryRequestStatusBadge } from './LateEntryRequestStatusBadge'

describe('LateEntryRequestStatusBadge', () => {
  it.each([
    ['PENDIENTE', 'Pendiente', 'bg-warn-soft'],
    ['AUTORIZADA', 'Autorizada', 'bg-ok-soft'],
    ['RECHAZADA', 'Rechazada', 'bg-danger-soft'],
  ] as const)('%s se muestra con texto, icono y su tono', (status, label, tone) => {
    const { container } = render(<LateEntryRequestStatusBadge status={status} />)

    expect(screen.getByText(label)).toHaveClass(tone)
    expect(container.querySelector('svg')).toHaveAttribute('aria-hidden', 'true')
  })

  it('acepta el texto propio del flujo', () => {
    render(<LateEntryRequestStatusBadge status="PENDIENTE" label="Esperando al docente" />)

    expect(screen.getByText('Esperando al docente')).toHaveClass('bg-warn-soft')
  })
})
