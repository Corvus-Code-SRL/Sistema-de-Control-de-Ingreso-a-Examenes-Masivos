import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { EnrolledCount, RosterStatusBadge } from './RosterStatusBadge'
import { makeGroup, makeGroupWithoutRoster } from '@/test/fixtures'

describe('RosterStatusBadge', () => {
  it('anuncia la ausencia de nómina con texto, no solo con color', () => {
    render(<RosterStatusBadge group={makeGroupWithoutRoster()} />)

    expect(screen.getByText('Sin nómina')).toBeInTheDocument()
  })

  it('marca como cargada la nómina de un grupo con inscritos', () => {
    render(<RosterStatusBadge group={makeGroup()} />)

    expect(screen.getByText('Nómina cargada')).toBeInTheDocument()
    expect(screen.queryByText('Sin nómina')).not.toBeInTheDocument()
  })

  it('trata cero inscritos y sin nómina como el mismo estado', () => {
    render(<RosterStatusBadge group={makeGroup({ cantidad_estudiantes: 0 })} />)

    expect(screen.getByText('Sin nómina')).toBeInTheDocument()
  })
})

describe('EnrolledCount', () => {
  it('muestra el número de inscritos', () => {
    render(<EnrolledCount group={makeGroup()} />)

    expect(screen.getByText('118')).toBeInTheDocument()
  })

  it('da un texto accesible al guion de «sin inscritos»', () => {
    render(<EnrolledCount group={makeGroupWithoutRoster()} />)

    expect(screen.getByText('Sin inscritos')).toBeInTheDocument()
  })
})
