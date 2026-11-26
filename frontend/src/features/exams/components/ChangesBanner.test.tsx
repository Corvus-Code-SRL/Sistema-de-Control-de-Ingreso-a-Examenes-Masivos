import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { ChangesBanner } from './ChangesBanner'

describe('ChangesBanner compatible con HU-028', () => {
  it('conserva por defecto el texto y las dos acciones existentes', () => {
    render(<ChangesBanner count={1} isSaving={false} onSave={vi.fn()} onDiscard={vi.fn()} />)
    expect(screen.getByText(/1 cambio sin guardar.*La puerta sigue usando la lista anterior/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Descartar' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Guardar cambios' })).toBeEnabled()
  })
  it('permite el mensaje de distribución sin duplicar acciones', () => {
    render(<ChangesBanner count={2} isSaving={false} onSave={vi.fn()} onDiscard={vi.fn()}
      message="La distribución aún no se guardó." showActions={false} />)
    expect(screen.getByText(/2 cambios sin guardar.*La distribución aún no se guardó/)).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
