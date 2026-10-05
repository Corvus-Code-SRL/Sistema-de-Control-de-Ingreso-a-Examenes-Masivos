import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { FormSelect } from './FormSelect'


beforeAll(() => {
  Element.prototype.hasPointerCapture = vi.fn(() => false)
  Element.prototype.setPointerCapture = vi.fn()
  Element.prototype.releasePointerCapture = vi.fn()
  Element.prototype.scrollIntoView = vi.fn()
})

function ControlledSelect() {
  const [value, setValue] = useState('')

  return (
    <FormSelect
      id="test-select"
      value={value}
      placeholder="Seleccione una opción"
      options={[
        { value: 'one', label: 'Opción uno' },
        { value: 'two', label: 'Opción dos' },
      ]}
      onValueChange={setValue}
    />
  )
}

describe('FormSelect', () => {
  it('permanece abierto después del clic inicial y permite seleccionar una opción', async () => {
    const user = userEvent.setup()
    render(<ControlledSelect />)

    const trigger = screen.getByRole('combobox')
    await user.click(trigger)

    expect(screen.getByRole('listbox')).toBeVisible()
    await user.click(screen.getByRole('option', { name: 'Opción dos' }))

    expect(trigger).toHaveTextContent('Opción dos')
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })
})
