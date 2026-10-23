import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Link } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { groupDetailResponse, makeGroup, makeSubject } from '@/test/fixtures'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { GroupExamsPage } from './GroupExamsPage'

function stub({ detailStatus = 200, listStatus = 200, empty = false, endTime = '' } = {}) {
  return mockApiWith(({ url }) => {
    if (url.endsWith('/grupos/100')) return {
      status: detailStatus,
      body: detailStatus === 200 ? groupDetailResponse(makeGroup(), makeSubject()) : { message: 'Grupo no disponible' },
    }
    if (url.endsWith('/grupos/100/examenes')) return {
      status: listStatus,
      body: listStatus === 200 ? { data: empty ? [] : [{ id_examen: 7, nombre_examen: 'Parcial finalizado', fecha: '2026-10-20', hora_inicio: '08:00', hora_fin: endTime || null, estado: 'FINALIZADO' }] } : { message: 'Fallo del listado' },
    }
  })
}

function renderPage(route = '/grupos/100/examenes') {
  return renderWithRouter(<GroupExamsPage />, { route, path: '/grupos/:idGrupo/examenes' })
}

describe('GroupExamsPage', () => {
  it('consulta el grupo, muestra estados históricos y enlaza creación, detalle y regreso', async () => {
    const { calls } = stub()
    renderPage()
    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(await screen.findByRole('cell', { name: 'Parcial finalizado' })).toBeInTheDocument()
    expect(within(screen.getByRole('table')).getByText('Finalizado')).toBeInTheDocument()
    expect(screen.getAllByText(/Bases de Datos I · Grupo 1/).length).toBeGreaterThan(0)
    expect(screen.getByRole('link', { name: 'Volver al grupo' })).toHaveAttribute('href', '/grupos/100')
    expect(within(screen.getByRole('main')).getByRole('link', { name: 'Nuevo examen' })).toHaveAttribute('href', '/examenes/nuevo?grupo=100')
    expect(screen.getByRole('link', { name: 'Ver detalle' })).toHaveAttribute('href', '/examenes/7?grupo=100')
    expect(screen.queryByText(/Estudiantes|Ambientes/)).not.toBeInTheDocument()
    expect(calls.map((call) => new URL(call.url).pathname)).toEqual(['/api/grupos/100', '/api/grupos/100/examenes'])
  })

  it('distingue un grupo sin exámenes de un error', async () => {
    stub({ empty: true })
    renderPage()
    expect(await screen.findByText('Este grupo aún no participa en ningún examen')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('permite reintentar el listado', async () => {
    const { calls } = stub({ listStatus: 500 })
    renderPage()
    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))
    await waitFor(() => expect(calls.filter((call) => call.url.endsWith('/examenes'))).toHaveLength(2))
    expect(screen.getByText('Fallo del listado')).toBeInTheDocument()
  })

  it.each([403, 404])('no consulta exámenes si el detalle responde %s', async (detailStatus) => {
    const { calls } = stub({ detailStatus })
    renderPage()
    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
    expect(calls).toHaveLength(1)
    expect(within(screen.getByRole('main')).queryByRole('link', { name: 'Nuevo examen' })).not.toBeInTheDocument()
  })

  it('rechaza un id inválido sin consultar', () => {
    const { calls } = stub()
    renderPage('/grupos/abc/examenes')
    expect(calls).toHaveLength(0)
    expect(screen.getByText('El grupo no existe')).toBeInTheDocument()
  })

  it('al cambiar de grupo descarta el listado anterior y consulta el nuevo contexto', async () => {
    const { calls } = mockApiWith(({ url }) => {
      const groupId = url.includes('/grupos/200') ? 200 : 100
      if (url.endsWith(`/grupos/${groupId}`)) return { body: groupDetailResponse(makeGroup({ id_grupo: groupId, num_grupo: String(groupId) }), makeSubject()) }
      if (url.endsWith(`/grupos/${groupId}/examenes`)) return { body: { data: [{ id_examen: groupId, nombre_examen: `Examen del grupo ${groupId}`, fecha: '2026-10-20', hora_inicio: '08:00', hora_fin: null, estado: 'PROGRAMADO' }] } }
    })
    renderWithRouter(<><GroupExamsPage /><Link to="/grupos/200/examenes">Cambiar grupo</Link></>, { route: '/grupos/100/examenes', path: '/grupos/:idGrupo/examenes' })
    await screen.findByRole('cell', { name: 'Examen del grupo 100' })
    await userEvent.click(screen.getByRole('link', { name: 'Cambiar grupo' }))
    expect(await screen.findByRole('cell', { name: 'Examen del grupo 200' })).toBeInTheDocument()
    expect(screen.queryByText('Examen del grupo 100')).not.toBeInTheDocument()
    expect(calls.filter((call) => call.url.endsWith('/grupos/200/examenes'))).toHaveLength(1)
  })


  it('la lista móvil conserva nombre, fecha, horario, estado y enlace accesible con una sola consulta', async () => {
    const { calls } = stub({ endTime: '09:30' })
    renderPage()
    const mobile = within(await screen.findByRole('list', { name: 'Exámenes del grupo' }))
    expect(mobile.getByRole('heading', { name: 'Parcial finalizado' })).toBeInTheDocument()
    expect(mobile.getByText('2026-10-20')).toBeInTheDocument()
    expect(mobile.getByText('08:00–09:30')).toBeInTheDocument()
    expect(mobile.getByText('Finalizado')).toBeInTheDocument()
    expect(mobile.getByRole('link', { name: 'Ver detalle de Parcial finalizado' })).toHaveAttribute('href', '/examenes/7?grupo=100')
    expect(within(screen.getByRole('table')).getByRole('cell', { name: 'Parcial finalizado' })).toBeInTheDocument()
    expect(calls.filter((call) => call.url.endsWith('/grupos/100/examenes'))).toHaveLength(1)
  })

})
