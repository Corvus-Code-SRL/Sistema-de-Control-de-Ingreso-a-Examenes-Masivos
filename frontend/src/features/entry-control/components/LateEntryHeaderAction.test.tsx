import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { makeExamInProgress } from '@/test/lateEntryFixtures'
import { renderWithRouter } from '@/test/render'
import { LateEntryHeaderAction } from './LateEntryHeaderAction'

describe('LateEntryHeaderAction', () => {
  it('lleva al ingreso con retraso del examen en curso', () => {
    renderWithRouter(<LateEntryHeaderAction exam={makeExamInProgress()} />)

    expect(screen.getByRole('link', { name: 'Registrar con retraso' })).toHaveAttribute('href', '/examenes/7/retrasos')
  })

  it('no aparece en un examen finalizado', () => {
    renderWithRouter(<LateEntryHeaderAction exam={makeExamInProgress({ estado: 'FINALIZADO' })} />)

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })
})
