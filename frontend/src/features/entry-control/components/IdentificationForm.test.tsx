import { afterEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { IdentificationForm } from './IdentificationForm'

function renderForm(overrides: Partial<Parameters<typeof IdentificationForm>[0]> = {}) {
  const props = {
    examId: 1, sis: '', ci: '', disabled: false, busy: false,
    onSisChange: vi.fn(), onCiChange: vi.fn(), onSelect: vi.fn(), onVerify: vi.fn(),
    ...overrides,
  }
  render(<IdentificationForm {...props} />)
  return props
}

afterEach(() => vi.unstubAllGlobals())

describe('IdentificationForm', () => {
  it('por omisión ofrece la búsqueda por nombre', () => {
    renderForm()

    expect(screen.getByLabelText('Buscar estudiante por nombre')).toBeInTheDocument()
  })

  it('sin sugerencias oculta la búsqueda y no consulta al servidor', () => {
    const fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
    renderForm({ sis: '202150001', ci: '7100001', suggestions: false })

    expect(screen.queryByLabelText('Buscar estudiante por nombre')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toBeInTheDocument()
    expect(screen.getByLabelText('Carnet de identidad (CI)')).toBeInTheDocument()
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('sin sugerencias sigue verificando con SIS y CI', () => {
    const props = renderForm({ sis: '202150001', ci: '7100001', suggestions: false })

    fireEvent.click(screen.getByRole('button', { name: 'Verificar estudiante' }))

    expect(props.onVerify).toHaveBeenCalledTimes(1)
  })
})
