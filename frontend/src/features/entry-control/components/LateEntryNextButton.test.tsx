import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { LateEntryNextButton } from './LateEntryNextButton'

describe('LateEntryNextButton', () => {
  it('pasa al siguiente estudiante', async () => {
    const onNext = vi.fn()
    render(<LateEntryNextButton onNext={onNext} />)

    await userEvent.click(screen.getByRole('button', { name: 'Siguiente estudiante' }))
    expect(onNext).toHaveBeenCalledTimes(1)
  })
})
