import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { MyAssistantsPage } from './MyAssistantsPage'

const assistant = { id_usuario: '11111111-1111-4111-8111-111111111111', nombre: 'Ana', apellido_paterno: 'Pérez', apellido_materno: null, nombre_completo: 'Ana Pérez', cod_sis: '1234567', correo: 'ana@example.test' }
const groups = [
  { id_grupo: 100, num_grupo: '1', label: 'Bases de Datos · Grupo 1' },
  { id_grupo: 200, num_grupo: '2', label: 'Cálculo · Grupo 2' },
]

function stub(existing = false) {
  return mockApiWith(({ url, method }) => {
    if (url.endsWith('/docente/auxiliares')) return { body: { data: existing ? [{ ...assistant, grupos: [{ ...groups[0], tiene_examen_programado: false, examen_programado: null }], examenes: [], examenes_disponibles: [] }] : [] } }
    if (url.endsWith('/docente/grupos')) return { body: { data: groups } }
    if (url.includes('/docente/auxiliares/buscar')) return { body: { data: [assistant] } }
    if (url.endsWith(`/docente/auxiliares/${assistant.id_usuario}/grupos`) && method === 'POST') return { body: { data: null } }
  })
}

async function openModal() {
  await userEvent.click((await screen.findAllByRole('button', { name: 'Asignar auxiliar' }))[0])
  const dialog = within(screen.getByRole('dialog'))
  await userEvent.type(dialog.getByPlaceholderText('Nombre, código SIS'), '1234567{Enter}')
  await dialog.findByText('Ana Pérez')
  return dialog
}

describe('MyAssistantsPage — regresión y modal general', () => {
  it('conserva la selección múltiple y la operación general aunque exista ?grupo=', async () => {
    const { calls } = stub()
    renderWithRouter(<MyAssistantsPage />, { route: '/mis-auxiliares?grupo=100' })
    await screen.findByText('Aún no tiene auxiliares')
    const dialog = await openModal()
    const checkboxes = dialog.getAllByRole('checkbox')
    expect(checkboxes).toHaveLength(2)
    expect(checkboxes[0]).not.toBeChecked()
    expect(checkboxes[1]).not.toBeChecked()
    await userEvent.click(checkboxes[0])
    await userEvent.click(checkboxes[1])
    await userEvent.click(dialog.getByRole('button', { name: 'Asignar a 2 grupos' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(calls.filter((call) => call.method === 'POST')).toEqual([expect.objectContaining({ url: expect.stringContaining(`/docente/auxiliares/${assistant.id_usuario}/grupos`), body: { grupos: [100, 200] } })])
    expect(calls.filter((call) => call.url.endsWith('/docente/auxiliares'))).toHaveLength(2)
    expect(calls.some((call) => /\/grupos\/100\/auxiliares$/.test(call.url))).toBe(false)
  })

  it('mantiene los grupos ya asignados marcados y deshabilitados', async () => {
    stub(true)
    renderWithRouter(<MyAssistantsPage />, { route: '/mis-auxiliares' })
    await screen.findByRole('cell', { name: /Ana Pérez/ })
    const dialog = await openModal()
    const checkboxes = dialog.getAllByRole('checkbox')
    expect(checkboxes[0]).toBeChecked()
    expect(checkboxes[0]).toBeDisabled()
    expect(checkboxes[1]).not.toBeChecked()
    expect(checkboxes[1]).toBeEnabled()
    expect(dialog.getByRole('button', { name: 'Seleccione al menos un grupo' })).toBeDisabled()
  })
})
