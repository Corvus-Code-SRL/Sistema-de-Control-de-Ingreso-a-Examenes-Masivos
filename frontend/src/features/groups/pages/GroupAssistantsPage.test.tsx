import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { groupDetailResponse, makeGroup, makeSubject } from '@/test/fixtures'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { GroupAssistantsPage } from './GroupAssistantsPage'

const assistant = { id_usuario: '11111111-1111-4111-8111-111111111111', nombre: 'Ana', apellido_paterno: 'Pérez', apellido_materno: null, nombre_completo: 'Ana Pérez', cod_sis: '1234567', correo: 'ana@example.test' }

function stub({ failAssignment = false, detailStatus = 200, listStatus = 200, alreadyAssigned = false, examCount = 0 } = {}) {
  let assigned = alreadyAssigned
  return mockApiWith(({ url, method }) => {
    if (url.endsWith('/grupos/100')) return { status: detailStatus, body: detailStatus === 200 ? groupDetailResponse(makeGroup({ activo: false }), makeSubject()) : { message: 'Grupo no disponible' } }
    if (url.endsWith('/docente/grupos/100/auxiliares') && method === 'POST') {
      if (failAssignment) return { status: 422, body: { message: 'No se pudo incorporar al auxiliar' } }
      assigned = true
      return { body: { data: null } }
    }
    if (url.endsWith('/grupos/100/auxiliares')) return { status: listStatus, body: listStatus === 200 ? { data: assigned ? [{ ...assistant, fecha_incorporacion: '2026-10-22', examenes: Array.from({ length: examCount }, (_, index) => ({ id_examen: index + 7, nombre_examen: index === 0 ? 'Primer parcial' : `Examen ${index + 1}`, fecha: '2026-10-22', estado: 'PROGRAMADO' })) }] : [] } : { message: 'Fallo del listado' } }
    if (url.includes('/docente/auxiliares/buscar')) return { body: { data: [assistant] } }
  })
}

function renderPage(route = '/grupos/100/auxiliares') {
  return renderWithRouter(<GroupAssistantsPage />, { route, path: '/grupos/:idGrupo/auxiliares' })
}

async function searchAssistant() {
  await userEvent.click(await screen.findByRole('button', { name: 'Asignar auxiliar' }))
  const dialog = within(screen.getByRole('dialog'))
  await userEvent.type(dialog.getByPlaceholderText('Nombre, código SIS'), '1234567{Enter}')
  await dialog.findByText('Ana Pérez')
  return dialog
}

describe('GroupAssistantsPage', () => {
  it('asigna solo al grupo de la URL, sin selector, y recarga tras cerrar', async () => {
    const { calls } = stub()
    renderPage()
    expect(await screen.findByText('Este grupo aún no tiene auxiliares')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Volver al grupo' })).toHaveAttribute('href', '/grupos/100')
    const dialog = await searchAssistant()
    expect(dialog.getByText(/Bases de Datos I · Grupo 1/)).toBeInTheDocument()
    expect(dialog.queryByRole('checkbox')).not.toBeInTheDocument()
    await userEvent.click(dialog.getByRole('button', { name: 'Asignar al grupo' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(await screen.findByRole('cell', { name: 'Ana Pérez' })).toBeInTheDocument()
    expect(screen.getByText('Auxiliar asignado al grupo')).toBeInTheDocument()
    expect(calls.filter((call) => call.method === 'POST')).toEqual([expect.objectContaining({ url: expect.stringContaining('/docente/grupos/100/auxiliares'), body: { id_usuario: assistant.id_usuario } })])
    expect(calls.filter((call) => call.url.endsWith('/grupos/100/auxiliares') && call.method === 'GET')).toHaveLength(2)
    expect(calls.some((call) => call.url.endsWith('/docente/auxiliares'))).toBe(false)
  })

  it('conserva el modal y el error si falla la asignación, sin recargar ni otras escrituras', async () => {
    const { calls } = stub({ failAssignment: true })
    renderPage()
    const dialog = await searchAssistant()
    await userEvent.click(dialog.getByRole('button', { name: 'Asignar al grupo' }))
    expect(await dialog.findByText('No se pudo incorporar al auxiliar')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(1)
    expect(calls.filter((call) => call.url.endsWith('/grupos/100/auxiliares') && call.method === 'GET')).toHaveLength(1)
  })

  it('impide repetir una incorporación que ya aparece en el listado', async () => {
    const { calls } = stub({ alreadyAssigned: true })
    renderPage()
    await screen.findByRole('cell', { name: 'Ana Pérez' })
    const dialog = await searchAssistant()
    expect(dialog.getByRole('button', { name: 'Ya está asignado' })).toBeDisabled()
    expect(calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it.each([403, 404])('no monta el listado ni la asignación cuando el grupo responde %s', async (detailStatus) => {
    const { calls } = stub({ detailStatus })
    renderPage()
    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
    expect(calls).toHaveLength(1)
    expect(screen.queryByRole('button', { name: 'Asignar auxiliar' })).not.toBeInTheDocument()
  })

  it('muestra el error del listado y permite reintentar', async () => {
    const { calls } = stub({ listStatus: 500 })
    renderPage()
    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))
    await waitFor(() => expect(calls.filter((call) => call.url.endsWith('/auxiliares'))).toHaveLength(2))
  })

  it('no consulta un identificador inválido', () => {
    const { calls } = stub()
    renderPage('/grupos/abc/auxiliares')
    expect(calls).toHaveLength(0)
  })

  it.each([false, true])('la lista móvil conserva identidad, incorporación y habilitaciones (con exámenes: %s)', async (withExams) => {
    const { calls } = stub({ alreadyAssigned: true, examCount: withExams ? 1 : 0 })
    renderPage()
    const mobile = within(await screen.findByRole('list', { name: 'Auxiliares del grupo' }))
    expect(mobile.getByRole('heading', { name: 'Ana Pérez' })).toBeInTheDocument()
    expect(await mobile.findByText('AP')).toBeInTheDocument()
    expect(mobile.getByText('SIS 1234567')).toBeInTheDocument()
    expect(mobile.getByText('2026-10-22')).toBeInTheDocument()
    if (withExams) {
      expect(mobile.getByRole('list', { name: 'Exámenes habilitados de Ana Pérez' })).toHaveTextContent('Primer parcial')
      expect(mobile.queryByText('Sin habilitar')).not.toBeInTheDocument()
    } else {
      expect(mobile.getByText('Sin habilitar')).toBeInTheDocument()
    }
    expect(within(screen.getByRole('table')).getByRole('cell', { name: 'Ana Pérez' })).toBeInTheDocument()
    expect(calls.filter((call) => call.url.endsWith('/grupos/100/auxiliares'))).toHaveLength(1)
  })


  it.each([0, 1, 3])('desktop muestra identidad y cada habilitación por separado (%s exámenes)', async (examCount) => {
    stub({ alreadyAssigned: true, examCount })
    renderPage()
    await screen.findByRole('cell', { name: 'Ana Pérez' })
    const table = within(screen.getByRole('table'))
    expect(await table.findByText('AP')).toBeInTheDocument()
    if (examCount === 0) {
      expect(table.getByText('Sin habilitar')).toBeInTheDocument()
      expect(table.queryByRole('list', { name: 'Exámenes habilitados de Ana Pérez' })).not.toBeInTheDocument()
    } else {
      const list = within(table.getByRole('list', { name: 'Exámenes habilitados de Ana Pérez' }))
      expect(list.getAllByRole('listitem')).toHaveLength(examCount)
      expect(list.getByText('Primer parcial')).toBeInTheDocument()
      for (let number = 2; number <= examCount; number++) {
        expect(list.getByText(`Examen ${number}`)).toBeInTheDocument()
      }
      expect(table.queryByText('Sin habilitar')).not.toBeInTheDocument()
    }
  })

})
