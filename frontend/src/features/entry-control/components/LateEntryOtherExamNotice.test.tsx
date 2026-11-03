import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { makeOtherExamWarning } from '@/test/lateEntryFixtures'
import { LateEntryOtherExamNotice } from './LateEntryOtherExamNotice'

describe('LateEntryOtherExamNotice', () => {
  it('muestra el título y el examen, el docente, la hora y el aula', () => {
    render(<LateEntryOtherExamNotice warning={makeOtherExamWarning()} />)

    expect(screen.getByRole('status')).toHaveClass('border-warn-border', 'bg-warn-soft')
    expect(screen.getByText('Ingreso en otro examen')).toBeInTheDocument()
    expect(screen.getByText(
      'Ya ingresó a Parcial de Física con Ana Rojas a las 08:05 por el 691A. Verifique su identidad antes de continuar.'
    )).toBeInTheDocument()
  })

  it('omite los datos que el servidor no tiene', () => {
    render(<LateEntryOtherExamNotice warning={makeOtherExamWarning({ docente: null, hora_ingreso: null, nro_aula: null })} />)

    expect(screen.getByText('Ya ingresó a Parcial de Física. Verifique su identidad antes de continuar.'))
      .toBeInTheDocument()
  })

  it('no trae botones: las acciones por rol las pone la vista', () => {
    render(<LateEntryOtherExamNotice warning={makeOtherExamWarning()} />)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
