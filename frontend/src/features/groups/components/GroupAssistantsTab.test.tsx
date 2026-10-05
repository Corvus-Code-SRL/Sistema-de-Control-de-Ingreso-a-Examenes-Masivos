import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { mockApiOnce, mockNetworkFailure } from '@/test/http'
import { renderWithRouter } from '@/test/render'

import { GroupAssistantsTab } from './GroupAssistantsTab'

const assistant = {
  id_usuario: '33333333-3333-4333-8333-000000000001',
  nombre_completo: 'María López Arnez',
  cod_sis: '201900233',
  correo: '201900233@umss.edu',
  fecha_incorporacion: '2026-09-01',
  examenes: [
    { id_examen: 41, nombre_examen: 'Primer parcial', fecha: '2026-10-14', estado: 'PROGRAMADO' },
  ],
}

describe('GroupAssistantsTab (HU-029)', () => {
  it('lista SIS, nombre y los exámenes del grupo donde está habilitado', async () => {
    mockApiOnce({ body: { data: [assistant] } })

    renderWithRouter(<GroupAssistantsTab groupId={100} />)

    expect(await screen.findByText('María López Arnez')).toBeInTheDocument()
    expect(screen.getByText('SIS 201900233')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /primer parcial/i })).toHaveAttribute(
      'href',
      '/examenes/41'
    )
  })

  it('un auxiliar sin habilitaciones lo dice con texto', async () => {
    mockApiOnce({ body: { data: [{ ...assistant, examenes: [] }] } })

    renderWithRouter(<GroupAssistantsTab groupId={100} />)

    expect(
      await screen.findByText(/aún no está habilitado para ningún examen de este grupo/i)
    ).toBeInTheDocument()
  })

  it('ofrece gestionar en Mis auxiliares y no duplica añadir ni quitar', async () => {
    mockApiOnce({ body: { data: [assistant] } })

    renderWithRouter(<GroupAssistantsTab groupId={100} />)
    await screen.findByText('María López Arnez')

    expect(screen.getByRole('link', { name: 'Gestionar en Mis auxiliares' })).toHaveAttribute(
      'href',
      '/mis-auxiliares'
    )
    expect(
      screen.queryByRole('button', { name: /quitar|añadir|asignar|habilitar/i })
    ).not.toBeInTheDocument()
  })

  it('sin auxiliares explica el estado y mantiene el enlace', async () => {
    mockApiOnce({ body: { data: [] } })

    renderWithRouter(<GroupAssistantsTab groupId={100} />)

    expect(await screen.findByText('Este grupo aún no tiene auxiliares')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Gestionar en Mis auxiliares' })).toBeInTheDocument()
  })

  it('un fallo de red se muestra como error, no como lista vacía', async () => {
    mockNetworkFailure()

    renderWithRouter(<GroupAssistantsTab groupId={100} />)

    expect(await screen.findByRole('alert')).toBeInTheDocument()
    expect(screen.queryByText('Este grupo aún no tiene auxiliares')).not.toBeInTheDocument()
  })
})
