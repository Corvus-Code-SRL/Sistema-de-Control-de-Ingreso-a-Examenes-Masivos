import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { distributionFixture } from '@/test/roomDistributionFixtures'
import { DistributionFeedback } from './DistributionFeedback'

describe('DistributionFeedback — estudiantes sin ambiente', () => {
  it.each([
    'Solo el docente que creó el examen puede guardar la distribución.',
    'El examen ya no permite cambios de preparación.',
  ])('solo lectura: %s', (reason) => {
    render(<DistributionFeedback data={distributionFixture({ editable: false, motivo_solo_lectura: reason })}
      missingSeats={0} unassigned={4} error={null} mustReload={false} busy={false} onReload={vi.fn()} />)
    expect(screen.getByText(reason)).toBeInTheDocument()
    expect(screen.getByText(/consulte estos casos con el docente responsable/)).toBeInTheDocument()
    expect(screen.queryByText(/Puede ubicarlos ahora y guardar/)).not.toBeInTheDocument()
    expect(screen.queryByText(/se ubicarán automáticamente/)).not.toBeInTheDocument()
  })

  it('durante la edición conserva el aviso condicionado a lugares disponibles', () => {
    render(<DistributionFeedback data={distributionFixture()} missingSeats={0} unassigned={4}
      error={null} mustReload={false} busy={false} onReload={vi.fn()} />)
    expect(screen.getByText(/si siguen existiendo lugares disponibles/)).toBeInTheDocument()
    expect(screen.getByText(/Puede ubicarlos ahora y guardar/)).toBeInTheDocument()
  })

  it('sin capacidad no promete asignaciones futuras', () => {
    render(<DistributionFeedback data={distributionFixture()} missingSeats={2} unassigned={4}
      error={null} mustReload={false} busy={false} onReload={vi.fn()} />)
    expect(screen.getByText('Capacidad insuficiente')).toBeInTheDocument()
    expect(screen.queryByText(/se ubicarán automáticamente/)).not.toBeInTheDocument()
  })
})
