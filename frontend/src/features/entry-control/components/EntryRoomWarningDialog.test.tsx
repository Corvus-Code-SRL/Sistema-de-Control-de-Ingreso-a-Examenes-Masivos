import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import type { EntryRoomWarningGate } from '../hooks/useEntryRoomWarning'
import { EntryRoomWarningDialog } from './EntryRoomWarningDialog'

function gate(overrides: Partial<EntryRoomWarningGate> = {}): EntryRoomWarningGate {
  return {
    warnings: [
      { tipo: 'FUERA_DE_AMBIENTE', mensaje: 'Este estudiante tiene asignado el ambiente 691A' },
      { tipo: 'AMBIENTE_LLENO', mensaje: 'El ambiente 692B ya está lleno (30 de 30)' },
    ],
    capture: vi.fn(), confirm: vi.fn(), cancel: vi.fn(),
    ...overrides,
  }
}

describe('EntryRoomWarningDialog', () => {
  it('muestra los avisos y permite registrar de todos modos', async () => {
    const current = gate()
    render(<EntryRoomWarningDialog gate={current} busy={false} />)

    expect(screen.getByText('Este estudiante tiene asignado el ambiente 691A')).toBeInTheDocument()
    expect(screen.getByText('El ambiente 692B ya está lleno (30 de 30)')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Registrar de todos modos' }))

    expect(current.confirm).toHaveBeenCalledTimes(1)
  })

  it('cancelar no registra', async () => {
    const current = gate()
    render(<EntryRoomWarningDialog gate={current} busy={false} />)

    await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(current.cancel).toHaveBeenCalled()
    expect(current.confirm).not.toHaveBeenCalled()
  })

  it('sin avisos no se abre', () => {
    render(<EntryRoomWarningDialog gate={gate({ warnings: null })} busy={false} />)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
