import { screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

import { makeGroup } from '@/test/fixtures'
import { renderWithRouter } from '@/test/render'

import { RosterUploadPanel } from './RosterUploadPanel'

const subjectName = 'Bases de Datos I'

describe('RosterUploadPanel', () => {
  it('arranca en el paso de selección de archivo', () => {
    renderWithRouter(
      <RosterUploadPanel
        group={makeGroup()}
        subjectName={subjectName}
        onReload={vi.fn()}
      />
    )

    expect(screen.getByText('Seleccionar archivo')).toBeInTheDocument()
  })

  it('muestra los tres pasos del flujo', () => {
    renderWithRouter(
      <RosterUploadPanel
        group={makeGroup()}
        subjectName={subjectName}
        onReload={vi.fn()}
      />
    )

    expect(screen.getByText('Archivo')).toBeInTheDocument()
    expect(screen.getByText('Previsualización')).toBeInTheDocument()
    expect(screen.getByText('Confirmación')).toBeInTheDocument()
  })

  it('muestra el nombre de la materia en el encabezado', () => {
    renderWithRouter(
      <RosterUploadPanel
        group={makeGroup()}
        subjectName={subjectName}
        onReload={vi.fn()}
      />
    )

    expect(screen.getAllByText(/Bases de Datos I/).length).toBeGreaterThan(0)
  })

  it('no invoca onReload mientras está en el paso de selección', () => {
    const onReload = vi.fn()

    renderWithRouter(
      <RosterUploadPanel
        group={makeGroup()}
        subjectName={subjectName}
        onReload={onReload}
      />
    )

    expect(onReload).not.toHaveBeenCalled()
  })

  it('anuncia que la nómina solo se asocia a este grupo', () => {
    renderWithRouter(
      <RosterUploadPanel
        group={makeGroup()}
        subjectName={subjectName}
        onReload={vi.fn()}
      />
    )

    expect(
      screen.getByText(/La nómina se asocia solo a este grupo/)
    ).toBeInTheDocument()
  })
})