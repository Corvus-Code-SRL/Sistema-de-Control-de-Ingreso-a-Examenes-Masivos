
import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { assistantsService } from '../services/assistantsService'
import type {
  Assistant,
  ExamAssistant,
  ExamAssistantsData,
  SuggestedAssistant,
} from '../types/assistant.types'
import { ExamAssistantsPage } from './ExamAssistantsPage'

vi.mock('../services/assistantsService', () => ({
  assistantsService: {
    listExamAssistants: vi.fn(),
    suggestedForExam: vi.fn(),
    search: vi.fn(),
    enableForExam: vi.fn(),
    removeFromExam: vi.fn(),
  },
}))

const listExamAssistants = vi.mocked(assistantsService.listExamAssistants)
const suggestedForExam = vi.mocked(assistantsService.suggestedForExam)
const search = vi.mocked(assistantsService.search)
const enableForExam = vi.mocked(assistantsService.enableForExam)
const removeFromExam = vi.mocked(assistantsService.removeFromExam)

const assistant: Assistant = {
  id_usuario: '00000000-0000-4000-8000-000000000093',
  nombre: 'Iván',
  apellido_paterno: 'Choque',
  apellido_materno: null,
  nombre_completo: 'Iván Choque',
  cod_sis: '209900093',
  correo: 'ivan@umss.edu',
}

const suggested: SuggestedAssistant = {
  id_usuario: assistant.id_usuario,
  nombre_completo: assistant.nombre_completo,
  cod_sis: assistant.cod_sis,
  habilitado: false,
}

const enabledAssistant: ExamAssistant = {
  id_examen: 9,
  id_usuario: assistant.id_usuario,
  id_ambiente: null,
  nombre_completo: assistant.nombre_completo,
  cod_sis: assistant.cod_sis,
  ambiente: null,
}

function examData(
  overrides: Partial<ExamAssistantsData> = {}
): ExamAssistantsData {
  return {
    estado: 'PROGRAMADO',
    editable: true,
    ambientes: [
      { id_ambiente: 1, nro_aula: '691A', capacidad: 40 },
    ],
    auxiliares: [],
    ...overrides,
  }
}

function renderPage(examId = '9') {
  return renderWithRouter(<ExamAssistantsPage />, {
    route: `/examenes/${examId}/auxiliares`,
    path: '/examenes/:examId/auxiliares',
  })
}

describe('ExamAssistantsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks()

    listExamAssistants.mockResolvedValue(examData())
    suggestedForExam.mockResolvedValue([])
    search.mockResolvedValue([])
    enableForExam.mockResolvedValue(undefined)
    removeFromExam.mockResolvedValue(undefined)
  })

  it.each(['ivan', '209900093'])(
    'busca auxiliares por nombre o SIS usando %s',
    async (criteria) => {
      search.mockResolvedValue([assistant])

      renderPage()

      const input = await screen.findByRole('textbox', {
        name: /buscar auxiliar por nombre o código sis/i,
      })

      fireEvent.change(input, {
        target: { value: criteria },
      })

      await waitFor(() => {
        expect(search).toHaveBeenCalledWith(
          criteria,
          expect.any(AbortSignal)
        )
      })

      expect(await screen.findByText('Iván Choque')).toBeVisible()
      expect(screen.getByText('SIS: 209900093')).toBeVisible()
    }
  )

  it('habilita un auxiliar sugerido y actualiza la lista', async () => {
    suggestedForExam.mockResolvedValue([suggested])

    listExamAssistants
      .mockResolvedValueOnce(examData())
      .mockResolvedValueOnce(
        examData({ auxiliares: [enabledAssistant] })
      )

    renderPage()

    expect(await screen.findByText('Iván Choque')).toBeVisible()

    fireEvent.click(
      screen.getByRole('button', { name: 'Habilitar' })
    )

    await waitFor(() => {
      expect(enableForExam).toHaveBeenCalledWith(
        9,
        assistant.id_usuario
      )
    })

    expect(
      await screen.findByText('Auxiliar habilitado correctamente.')
    ).toBeVisible()

    const section = screen
      .getByRole('heading', { name: 'Auxiliares habilitados' })
      .closest('section')

    expect(section).not.toBeNull()
    expect(within(section!).getByText('Iván Choque')).toBeVisible()
  })

  it('muestra Habilitado y desactiva el botón de un auxiliar existente', async () => {
    suggestedForExam.mockResolvedValue([suggested])

    listExamAssistants.mockResolvedValue(
      examData({ auxiliares: [enabledAssistant] })
    )

    renderPage()

    const button = await screen.findByRole('button', {
      name: 'Habilitado',
    })

    expect(button).toBeDisabled()

    fireEvent.click(button)

    expect(enableForExam).not.toHaveBeenCalled()
  })

  it('quita un auxiliar únicamente después de confirmar', async () => {
    listExamAssistants
      .mockResolvedValueOnce(
        examData({ auxiliares: [enabledAssistant] })
      )
      .mockResolvedValueOnce(examData())

    renderPage()

    expect(await screen.findByText('Iván Choque')).toBeVisible()

    fireEvent.click(
      screen.getByRole('button', { name: 'Quitar' })
    )

    const dialog = await screen.findByRole('dialog')

    expect(
      within(dialog).getByText('Quitar auxiliar del examen')
    ).toBeVisible()

    expect(removeFromExam).not.toHaveBeenCalled()

    fireEvent.click(
      within(dialog).getByRole('button', { name: 'Sí, quitar' })
    )

    await waitFor(() => {
      expect(removeFromExam).toHaveBeenCalledWith(
        9,
        assistant.id_usuario
      )
    })

    expect(
      await screen.findByText('Auxiliar quitado correctamente.')
    ).toBeVisible()

    expect(
      screen.getByText('Todavía no hay auxiliares habilitados.')
    ).toBeVisible()
  })

  it('muestra el error 422 al intentar habilitar un auxiliar', async () => {
    suggestedForExam.mockResolvedValue([suggested])

    enableForExam.mockRejectedValue(
      new Error(
        'El auxiliar ya está asignado a otro examen en un ambiente diferente durante un horario que se superpone.'
      )
    )

    renderPage()

    expect(await screen.findByText('Iván Choque')).toBeVisible()

    fireEvent.click(
      screen.getByRole('button', { name: 'Habilitar' })
    )

    expect(
      await screen.findByRole('alert')
    ).toHaveTextContent(
      'El auxiliar ya está asignado a otro examen en un ambiente diferente durante un horario que se superpone.'
    )

    expect(
      screen.getByText('Todavía no hay auxiliares habilitados.')
    ).toBeVisible()
  })

  it('permite consultar pero no modificar auxiliares en EN_INGRESO', async () => {
    listExamAssistants.mockResolvedValue(
      examData({
        estado: 'EN_INGRESO',
        editable: false,
        auxiliares: [enabledAssistant],
      })
    )

    suggestedForExam.mockResolvedValue([
        suggested,
        {
            id_usuario: '00000000-0000-4000-8000-000000000095',
            nombre_completo: 'Carlos Mendoza',
            cod_sis: '209900095',
            habilitado: false,
        },
    ])

    renderPage()

    expect((await screen.findAllByText('Iván Choque')).length).toBeGreaterThan(0)

    expect(
      screen.getByRole('status')
    ).toHaveTextContent(
      'Esta vista es de solo lectura.'
    )

    expect(
      screen.queryByRole('textbox', {
        name: /buscar auxiliar/i,
      })
    ).not.toBeInTheDocument()

    expect(
      screen.queryByRole('button', { name: 'Quitar' })
    ).not.toBeInTheDocument()

    expect(
      screen.queryByRole('button', { name: 'Habilitar' })
    ).not.toBeInTheDocument()

    expect(enableForExam).not.toHaveBeenCalled()
    expect(removeFromExam).not.toHaveBeenCalled()
  })

  it('rechaza un identificador de examen inválido', async () => {
    renderPage('abc')

    expect(
      await screen.findByRole('alert')
    ).toHaveTextContent(
      'El identificador del examen no es válido.'
    )

    expect(listExamAssistants).not.toHaveBeenCalled()
    expect(suggestedForExam).not.toHaveBeenCalled()
  })
})
