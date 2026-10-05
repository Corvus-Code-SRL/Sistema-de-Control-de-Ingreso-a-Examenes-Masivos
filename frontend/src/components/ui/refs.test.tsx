import { createRef } from 'react'
import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { CardTitle } from './card'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from './dialog'

describe('primitivas con ref', () => {
  it('CardTitle entrega el ref a su elemento: el título puede recibir el foco', () => {
    const ref = createRef<HTMLDivElement>()

    render(<CardTitle ref={ref}>Confirmar importación</CardTitle>)

    expect(ref.current).toBe(screen.getByText('Confirmar importación'))
  })

  it('abrir un Dialog no provoca el aviso de componentes de función sin forwardRef', () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => {})

    render(
      <Dialog open>
        <DialogContent>
          <DialogTitle>Título</DialogTitle>
          <DialogDescription>Descripción</DialogDescription>
        </DialogContent>
      </Dialog>
    )

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(error.mock.calls.flat().join(' ')).not.toMatch(/cannot be given refs/i)
  })
})
