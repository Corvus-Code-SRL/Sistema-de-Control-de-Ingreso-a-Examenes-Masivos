import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ExamSummaryCard } from './ExamSummaryCard'

function renderCard(overrides: Partial<Parameters<typeof ExamSummaryCard>[0]> = {}) {
  return render(
    <ExamSummaryCard
      title="Primer parcial"
      subject="Bases de Datos I"
      dateLabel="2026-10-31"
      startsAt="08:00"
      endsAt="10:00"
      roomsLabel="Ambientes"
      rooms="691A, 692B"
      action={<button type="button">Abrir</button>}
      {...overrides}
    />
  )
}

describe('ExamSummaryCard', () => {
  it('muestra el examen, su horario, sus ambientes y la acción', () => {
    renderCard()

    expect(screen.getByRole('heading', { name: 'Primer parcial' })).toBeInTheDocument()
    expect(screen.getByText('Bases de Datos I')).toBeInTheDocument()
    expect(screen.getByText('2026-10-31 · 08:00–10:00')).toBeInTheDocument()
    expect(screen.getByText('Ambientes: 691A, 692B')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Abrir' })).toBeInTheDocument()
  })

  it('omite la materia y el horario cuando faltan', () => {
    renderCard({ subject: null, endsAt: null, dateLabel: 'Sin fecha' })

    expect(screen.queryByText('Bases de Datos I')).not.toBeInTheDocument()
    expect(screen.getByText('Sin fecha')).toBeInTheDocument()
  })
})
