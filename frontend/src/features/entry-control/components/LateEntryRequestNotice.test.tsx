import { act, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { makeLateEntryRequest } from '@/test/lateEntryFixtures'
import { lateEntryService } from '../services/lateEntryService'
import { LateEntryRequestNotice } from './LateEntryRequestNotice'

afterEach(() => {
  vi.restoreAllMocks()
})

async function renderNotice() {
  render(<LateEntryRequestNotice examId={7} />)
  await act(async () => { await Promise.resolve() })
}

describe('LateEntryRequestNotice', () => {
  it('lista las pendientes en una fila compacta con Rechazar y Autorizar', async () => {
    vi.spyOn(lateEntryService, 'list').mockResolvedValue([
      makeLateEntryRequest(),
      makeLateEntryRequest({ id_solicitud: 30, estado: 'AUTORIZADA' }),
    ])
    await renderNotice()

    const notice = screen.getByRole('region', { name: 'Solicitud de ingreso con retraso' })
    const rows = within(notice).getAllByRole('listitem')
    expect(rows).toHaveLength(1)
    expect(rows[0]).toHaveTextContent('Lucía Fernández Rojas · 692B · 10:12 · solicitado por Mario Quispe')
    expect(within(rows[0]).getByRole('button', { name: /Rechazar/ })).toBeInTheDocument()
    expect(within(rows[0]).getByRole('button', { name: /Autorizar/ })).toBeInTheDocument()
  })

  it('al autorizar la fila desaparece y se anuncia el resultado', async () => {
    const pending = makeLateEntryRequest()
    vi.spyOn(lateEntryService, 'list').mockResolvedValue([pending])
    const approve = vi.spyOn(lateEntryService, 'approve').mockResolvedValue({ ...pending, estado: 'AUTORIZADA' })
    await renderNotice()

    await userEvent.click(screen.getByRole('button', { name: 'Autorizar: Lucía Fernández Rojas' }))

    expect(approve).toHaveBeenCalledWith(7, 31)
    expect(screen.queryByRole('region', { name: 'Solicitud de ingreso con retraso' })).not.toBeInTheDocument()
    expect(screen.getByText('Ingreso autorizado')).toBeInTheDocument()
  })

  it('sin pendientes no muestra el aviso', async () => {
    vi.spyOn(lateEntryService, 'list').mockResolvedValue([])
    await renderNotice()

    expect(screen.queryByRole('region')).not.toBeInTheDocument()
  })
})
