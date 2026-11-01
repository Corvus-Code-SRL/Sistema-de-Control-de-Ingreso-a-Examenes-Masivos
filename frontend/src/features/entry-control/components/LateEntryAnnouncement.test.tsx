import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LateEntryAnnouncement } from './LateEntryAnnouncement'

describe('LateEntryAnnouncement', () => {
  it('deja la región aria-live montada aunque no haya aviso', () => {
    const { container } = render(<LateEntryAnnouncement message={null} onClose={vi.fn()} />)

    expect(container.querySelector('[aria-live="polite"]')).toBeEmptyDOMElement()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('anuncia el aviso, lo cierra y muestra el error aparte', async () => {
    const onClose = vi.fn()
    render(<LateEntryAnnouncement message="Ingreso autorizado" error="La solicitud ya fue resuelta." onClose={onClose} />)

    expect(screen.getByText('Ingreso autorizado')).toBeInTheDocument()
    expect(screen.getByRole('alert')).toHaveTextContent('La solicitud ya fue resuelta.')

    await userEvent.click(screen.getByRole('button', { name: 'Cerrar aviso' }))
    expect(onClose).toHaveBeenCalledTimes(1)
  })
})
