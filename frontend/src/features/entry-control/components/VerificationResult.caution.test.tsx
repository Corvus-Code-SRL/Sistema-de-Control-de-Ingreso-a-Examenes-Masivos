import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { makeLateEntryVerification } from '@/test/lateEntryFixtures'
import { VerificationResult } from './VerificationResult'

const COMPARE = /Compare el nombre y el código SIS/

function renderResult(props: { caution?: string; actionsLayout?: 'viewport' | 'card' } = {}) {
  return render(
    <VerificationResult
      result={makeLateEntryVerification()}
      busy={false}
      onConfirm={vi.fn()}
      onReject={vi.fn()}
      onNext={vi.fn()}
      {...props}
    />
  )
}

function classesOf(element: HTMLElement): string[] {
  return element.className.split(/\s+/)
}

describe('VerificationResult: precaución y fila de botones (HU-037)', () => {
  it('sin precaución se ve como en el control de ingreso', () => {
    const { container } = renderResult()

    expect(screen.getByRole('heading', { name: 'AUTORIZADO' })).toHaveClass('text-ok-fg')
    expect(screen.getByText(COMPARE)).toHaveClass('text-ok-fg')
    expect(container.querySelector('section')).not.toHaveClass('@container')
    const accept = screen.getByRole('button', { name: 'Aceptar ingreso' })
    expect(accept).toHaveClass('h-11', 'w-full', 'sm:w-auto', 'sm:flex-1')
    expect(accept.parentElement).toHaveClass('flex-col', 'sm:flex-row')
  })

  it('con precaución el título y la nota no se leen como «todo bien»', () => {
    renderResult({ caution: 'VERIFIQUE SU IDENTIDAD' })

    expect(screen.queryByRole('heading', { name: 'AUTORIZADO' })).not.toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'VERIFIQUE SU IDENTIDAD' })).toHaveClass('text-warn-fg')
    expect(screen.getByText(COMPARE)).toHaveClass('text-danger-fg')
    expect(screen.getByText(COMPARE)).not.toHaveClass('text-ok-fg')
  })

  it('la precaución no cambia un resultado que no autoriza', () => {
    render(
      <VerificationResult
        result={makeLateEntryVerification({ veredicto: 'DUPLICADO', autorizado: false, motivo: 'Ya ingresó' })}
        busy={false}
        caution="VERIFIQUE SU IDENTIDAD"
        onConfirm={vi.fn()}
        onReject={vi.fn()}
        onNext={vi.fn()}
      />
    )

    expect(screen.queryByRole('heading', { name: 'VERIFIQUE SU IDENTIDAD' })).not.toBeInTheDocument()
  })

  it('con la fila según la tarjeta apila por el ancho de la tarjeta, sin flex-1 de pantalla', () => {
    const { container } = renderResult({ actionsLayout: 'card' })

    expect(container.querySelector('section')).toHaveClass('@container')
    for (const name of ['Rechazar', 'Aceptar ingreso']) {
      const button = screen.getByRole('button', { name })
      expect(button).toHaveClass('h-11', 'w-full', '@xl:w-auto', '@xl:flex-1')
      expect(classesOf(button)).not.toContain('flex-1')
      expect(classesOf(button)).not.toContain('sm:flex-1')
    }
    expect(screen.getByRole('button', { name: 'Aceptar ingreso' }).parentElement).toHaveClass('flex-col', '@xl:flex-row')
  })
})
