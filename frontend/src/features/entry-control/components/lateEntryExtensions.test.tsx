import { act, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { makeExamInProgress } from '@/test/lateEntryFixtures'
import { renderWithRouter } from '@/test/render'
import { lateEntryService } from '../services/lateEntryService'
import { lateEntryExtensions } from './lateEntryExtensions'

afterEach(() => {
  vi.restoreAllMocks()
})

describe('lateEntryExtensions', () => {
  it('aporta el acceso del encabezado a ambos roles', () => {
    renderWithRouter(<>{lateEntryExtensions.headerActions(makeExamInProgress({ rol_controlador: 'AUXILIAR' }))}</>)

    expect(screen.getByRole('link', { name: 'Registrar con retraso' })).toBeInTheDocument()
  })

  it('el aviso de solicitudes es solo del docente con el examen en curso', async () => {
    const list = vi.spyOn(lateEntryService, 'list').mockResolvedValue([])

    expect(lateEntryExtensions.panels(makeExamInProgress({ rol_controlador: 'AUXILIAR' }))).toBeNull()
    expect(lateEntryExtensions.panels(makeExamInProgress({ estado: 'FINALIZADO' }))).toBeNull()

    renderWithRouter(<>{lateEntryExtensions.panels(makeExamInProgress())}</>)
    await act(async () => { await Promise.resolve() })

    expect(list).toHaveBeenCalledTimes(1)
    expect(list).toHaveBeenCalledWith(7, expect.any(AbortSignal))
  })
})
