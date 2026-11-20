import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ExceptionalMark } from './ExceptionalMark'

describe('ExceptionalMark', () => {
  it('muestra la marca y el motivo del registro', () => {
    render(<ExceptionalMark reason="Convalidación aprobada" />)

    expect(screen.getByText('Excepcional')).toBeInTheDocument()
    expect(screen.getByText('Motivo: Convalidación aprobada')).toBeInTheDocument()
  })

  it('sin motivo muestra solo la marca', () => {
    render(<ExceptionalMark />)

    expect(screen.getByText('Excepcional')).toBeInTheDocument()
    expect(screen.queryByText(/Motivo:/)).not.toBeInTheDocument()
  })
})
