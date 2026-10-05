import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { mockApiOnce, mockNetworkFailure } from '@/test/http'
import { renderWithRouter } from '@/test/render'

import { GroupExamsTab } from './GroupExamsTab'

const exam = {
  id_examen: 41,
  nombre_examen: 'Primer parcial',
  fecha: '2026-10-14',
  hora_inicio: '08:00',
  hora_fin: '09:30',
  estado: 'PROGRAMADO',
  materia: { id_materia: 10, nombre: 'Bases de Datos I', codigo: '2008034' },
}

describe('GroupExamsTab (HU-029)', () => {
  it('lista cada examen con fecha, horario, materia, estado y un enlace a su detalle', async () => {
    mockApiOnce({
      body: {
        data: [exam, { ...exam, id_examen: 42, nombre_examen: 'Final', estado: 'CANCELADO' }],
      },
    })

    renderWithRouter(<GroupExamsTab groupId={100} />)

    const link = await screen.findByRole('link', { name: /primer parcial/i })
    expect(link).toHaveAttribute('href', '/examenes/41')
    expect(link).toHaveTextContent('08:00–09:30')
    expect(link).toHaveTextContent('Bases de Datos I')
    expect(link).toHaveTextContent('Programado')
    expect(screen.getByRole('link', { name: /final/i })).toHaveAttribute('href', '/examenes/42')
    expect(screen.getByText('Cancelado')).toBeInTheDocument()
  })

  it('pide los exámenes del grupo indicado', async () => {
    mockApiOnce({ body: { data: [exam] } })

    renderWithRouter(<GroupExamsTab groupId={100} />)
    await screen.findByText('Primer parcial')

    expect((fetch as unknown as { mock: { calls: unknown[][] } }).mock.calls[0][0]).toContain(
      '/grupos/100/examenes'
    )
  })

  it('sin exámenes explica el estado y ofrece programar uno', async () => {
    mockApiOnce({ body: { data: [] } })

    renderWithRouter(<GroupExamsTab groupId={100} />)

    expect(
      await screen.findByText('Este grupo aún no participa en ningún examen')
    ).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /programar un examen/i })).toHaveAttribute(
      'href',
      '/examenes/nuevo'
    )
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('un fallo de red se muestra como error con reintento, no como lista vacía', async () => {
    mockNetworkFailure()

    renderWithRouter(<GroupExamsTab groupId={100} />)

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
    expect(
      screen.queryByText('Este grupo aún no participa en ningún examen')
    ).not.toBeInTheDocument()
  })
})
