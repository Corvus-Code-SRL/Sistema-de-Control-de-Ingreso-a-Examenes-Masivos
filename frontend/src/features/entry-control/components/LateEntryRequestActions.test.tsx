import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { makeLateEntryRequest } from '@/test/lateEntryFixtures'
import { LateEntryRequestActions } from './LateEntryRequestActions'

describe('LateEntryRequestActions', () => {
  it('rechaza y autoriza la solicitud con botones de 44 px', async () => {
    const onApprove = vi.fn()
    const onReject = vi.fn()
    const request = makeLateEntryRequest()
    render(<LateEntryRequestActions request={request} busy={false} onApprove={onApprove} onReject={onReject} />)

    const reject = screen.getByRole('button', { name: 'Rechazar: Lucía Fernández Rojas' })
    const approve = screen.getByRole('button', { name: 'Autorizar: Lucía Fernández Rojas' })
    for (const button of [reject, approve]) {
      expect(button).toHaveClass('h-11', 'w-full')
      expect(button.className.split(/\s+/)).not.toContain('flex-1')
    }

    await userEvent.click(reject)
    await userEvent.click(approve)
    expect(onReject).toHaveBeenCalledWith(31)
    expect(onApprove).toHaveBeenCalledWith(request)
  })

  it('se deshabilita mientras se resuelve una solicitud', () => {
    render(<LateEntryRequestActions request={makeLateEntryRequest()} busy onApprove={vi.fn()} onReject={vi.fn()} />)

    for (const button of screen.getAllByRole('button')) expect(button).toBeDisabled()
  })
})
