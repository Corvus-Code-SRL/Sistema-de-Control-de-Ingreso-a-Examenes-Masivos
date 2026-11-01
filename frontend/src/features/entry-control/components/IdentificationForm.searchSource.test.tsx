import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { StudentSearchSource } from '../types/entry-control.types'
import { IdentificationForm } from './IdentificationForm'

const lucia = { id_estudiante: 501, cod_sis: '202301903', nombre_completo: 'Lucía Fernández Rojas', ci: '9342145' }

function source(): StudentSearchSource {
  return { byName: vi.fn().mockResolvedValue([lucia]), byIdentifier: vi.fn().mockResolvedValue([lucia]) }
}

function renderForm(searchSource: StudentSearchSource, sis = '', onSelect = vi.fn()) {
  render(
    <IdentificationForm
      examId={7}
      sis={sis}
      ci=""
      disabled={false}
      busy={false}
      onSisChange={vi.fn()}
      onCiChange={vi.fn()}
      onSelect={onSelect}
      onVerify={vi.fn()}
      searchSource={searchSource}
    />
  )
  return onSelect
}

afterEach(() => {
  vi.useRealTimers()
})

describe('IdentificationForm con otra fuente de sugerencias', () => {
  it('la búsqueda por nombre usa la fuente recibida', async () => {
    const custom = source()
    const onSelect = renderForm(custom)

    await userEvent.type(screen.getByLabelText('Buscar estudiante por nombre'), 'lucia')
    await userEvent.click(await screen.findByRole('button', { name: /Lucía Fernández Rojas/ }))

    expect(custom.byName).toHaveBeenCalledWith(7, 'lucia', expect.any(AbortSignal))
    expect(onSelect).toHaveBeenCalledWith(lucia, 'sis')
  })

  it('las sugerencias del SIS usan la fuente recibida', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const custom = source()
    renderForm(custom, '2023')

    await act(async () => { await vi.advanceTimersByTimeAsync(300) })

    expect(custom.byIdentifier).toHaveBeenCalledWith(7, 'sis', '2023', expect.any(AbortSignal))
  })
})
