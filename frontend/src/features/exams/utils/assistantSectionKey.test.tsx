import { render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { AssistantClassroomSection } from '@/features/assistants'
import { examAssistantsData } from '@/test/assistantFixtures'
import { mockApiOnce } from '@/test/http'
import type { Classroom, ExamStatus } from '../types/exams.types'
import { assistantSectionKey } from './assistantSectionKey'

function classroom(id_ambiente: number): Classroom {
  return { id_ambiente, nro_aula: `Aula ${id_ambiente}`, capacidad: 60 }
}

const programado = { estado: 'PROGRAMADO' as ExamStatus, ambientes: [classroom(11), classroom(12)] }

describe('assistantSectionKey', () => {
  it('no cambia con el mismo estado y los mismos ambientes, en cualquier orden', () => {
    expect(assistantSectionKey({ ...programado, ambientes: [classroom(12), classroom(11)] })).toBe(
      assistantSectionKey(programado)
    )
  })

  it('cambia al cancelar el examen o al cambiar sus ambientes', () => {
    const base = assistantSectionKey(programado)

    expect(assistantSectionKey({ ...programado, estado: 'CANCELADO' })).not.toBe(base)
    expect(assistantSectionKey({ ...programado, ambientes: [classroom(12)] })).not.toBe(base)
  })

  it('tolera un examen sin ambientes cargados', () => {
    expect(assistantSectionKey({ estado: 'PROGRAMADO' })).toBe('PROGRAMADO-')
  })

  it('con esta key la sección vuelve a pedir sus auxiliares solo cuando el examen cambia', async () => {
    mockApiOnce({ body: { data: examAssistantsData() } })

    const { rerender } = render(
      <AssistantClassroomSection key={assistantSectionKey(programado)} examId={7} />
    )
    await screen.findByText('Cada cambio se guarda al momento')

    // Mismos ambientes en otro orden: la sección no se vuelve a montar.
    rerender(
      <AssistantClassroomSection
        key={assistantSectionKey({ ...programado, ambientes: [classroom(12), classroom(11)] })}
        examId={7}
      />
    )
    expect(fetch).toHaveBeenCalledTimes(1)

    rerender(
      <AssistantClassroomSection key={assistantSectionKey({ ...programado, estado: 'CANCELADO' })} examId={7} />
    )
    await waitFor(() => expect(fetch).toHaveBeenCalledTimes(2))
    await screen.findByText('Cada cambio se guarda al momento')
  })
})
