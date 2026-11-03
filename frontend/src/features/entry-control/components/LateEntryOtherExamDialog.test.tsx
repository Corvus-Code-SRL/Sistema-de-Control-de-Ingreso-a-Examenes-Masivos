import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LateEntryOtherExamDialog } from './LateEntryOtherExamDialog'

function renderDialog(busy = false) {
  const onConfirm = vi.fn()
  const onOpenChange = vi.fn()
  render(
    <LateEntryOtherExamDialog
      open
      studentName="Lucía Fernández Rojas"
      busy={busy}
      onConfirm={onConfirm}
      onOpenChange={onOpenChange}
    />
  )

  return { dialog: screen.getByRole('dialog'), onConfirm, onOpenChange }
}

describe('LateEntryOtherExamDialog', () => {
  it('pide confirmar con el nombre del estudiante', () => {
    const { dialog } = renderDialog()

    expect(within(dialog).getByText('¿Registrar de todos modos?')).toBeInTheDocument()
    expect(within(dialog).getByText(/Lucía Fernández Rojas ya ingresó a otro examen/)).toBeInTheDocument()
  })

  it('«Registrar de todos modos» confirma', async () => {
    const { dialog, onConfirm, onOpenChange } = renderDialog()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Registrar de todos modos' }))

    expect(onConfirm).toHaveBeenCalledTimes(1)
    expect(onOpenChange).not.toHaveBeenCalled()
  })

  it('«Cancelar» cierra sin confirmar', async () => {
    const { dialog, onConfirm, onOpenChange } = renderDialog()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Cancelar' }))

    expect(onOpenChange).toHaveBeenCalledWith(false)
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('mientras registra deshabilita ambos botones de 44 px', () => {
    const { dialog } = renderDialog(true)

    for (const name of ['Cancelar', 'Registrar de todos modos']) {
      const button = within(dialog).getByRole('button', { name })
      expect(button).toBeDisabled()
      expect(button).toHaveClass('h-11', 'w-full')
    }
  })
})
