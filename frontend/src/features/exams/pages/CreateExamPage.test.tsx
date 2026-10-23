import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { CreateExamPage } from './CreateExamPage'

const options = {
  materias: [
    { id_carrera: 1, id_materia: 10, nombre: 'Bases de Datos I', codigo: 'BD', carrera: 'Sistemas', es_mia: true },
    { id_carrera: 2, id_materia: 10, nombre: 'Bases de Datos I', codigo: 'BD', carrera: 'Informática', es_mia: true },
  ],
  ambientes: [],
  grupos: [
    { id_grupo: 100, id_carrera: 1, id_materia: 10, num_grupo: '1', gestion: '2026', estado: 'ACTIVO', cantidad_estudiantes: 20, tiene_nomina: true },
    { id_grupo: 200, id_carrera: 2, id_materia: 10, num_grupo: '2', gestion: '2026', estado: 'ACTIVO', cantidad_estudiantes: 30, tiene_nomina: true },
  ],
}

function Location() {
  return <output data-testid="location">{useLocation().pathname}</output>
}

function renderPage(route = '/examenes/nuevo?grupo=100') {
  return renderWithRouter(<><CreateExamPage /><Location /></>, { route })
}

function stub(data = options) {
  return mockApiWith(({ url }) => url.endsWith('/examenes/formulario') ? { body: { data } } : undefined)
}

describe('CreateExamPage — contexto inicial', () => {
  it('Crear otro examen restaura la materia, carrera y grupo 100 tras una creación completada', async () => {
    const { calls } = mockApiWith(({ url, method }) => {
      if (url.endsWith('/examenes/formulario')) return {
        body: { data: { ...options, ambientes: [{ id_ambiente: 1, nro_aula: '691A', capacidad: 40 }] } },
      }
      if (url.endsWith('/examenes') && method === 'POST') return {
        body: { data: { id_examen: 77, nombre_examen: 'Parcial', fecha: '2099-01-01', hora_inicio: '08:00', hora_fin: '09:30' } },
      }
    })
    renderPage()
    const subject = await screen.findByRole('combobox', { name: 'Materia *' })
    await waitFor(() => expect(subject).toHaveTextContent('Bases de Datos I — Sistemas'))
    await userEvent.type(screen.getByLabelText('Nombre del examen *'), 'Parcial')
    fireEvent.change(screen.getByLabelText('Fecha *'), { target: { value: '2099-01-01' } })
    await userEvent.click(screen.getByRole('checkbox', { name: /Aula 691A/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Guardar examen' }))
    expect(await screen.findByRole('link', { name: 'Ver detalle del examen' })).toHaveAttribute('href', '/examenes/77?grupo=100')
    await userEvent.click(await screen.findByRole('button', { name: 'Crear otro examen' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Nombre del examen *')).toHaveValue('')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia *' })).toHaveTextContent('Bases de Datos I — Sistemas'))
    expect(within(screen.getByRole('region', { name: 'Resumen de capacidad del examen' })).getByText('20')).toBeInTheDocument()
    expect(calls.filter((call) => call.method === 'POST')).toEqual([
      expect.objectContaining({ body: expect.objectContaining({ id_carrera: 1, id_materia: 10, grupos: [100] }) }),
    ])
  })

  it('inicializa carrera, materia y grupo tras cargar las opciones, sin volver a imponerlos', async () => {
    const { calls } = stub()
    renderPage()
    expect(screen.getByText(/Cargando formulario/)).toBeInTheDocument()
    const subject = await screen.findByRole('combobox', { name: 'Materia *' })
    await waitFor(() => expect(subject).toHaveTextContent('Bases de Datos I — Sistemas'))
    expect(within(screen.getByRole('region', { name: 'Resumen de capacidad del examen' })).getByText('20')).toBeInTheDocument()
    await userEvent.click(subject)
    await userEvent.click(await screen.findByRole('option', { name: 'Bases de Datos I — Informática' }))
    expect(subject).toHaveTextContent('Bases de Datos I — Informática')
    expect(screen.getByRole('button', { name: /Grupo 2/ })).toBeEnabled()
    expect(screen.queryByRole('button', { name: /Grupo 1/ })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: /Grupo 2/ }))
    await userEvent.type(screen.getByLabelText('Nombre del examen *'), 'Otro examen')
    expect(subject).toHaveTextContent('Bases de Datos I — Informática')
    expect(within(screen.getByRole('region', { name: 'Resumen de capacidad del examen' })).getByText('30')).toBeInTheDocument()
    expect(calls).toHaveLength(1)
  })

  it('permite quitar el grupo preseleccionado sin reimponerlo al editar otro campo', async () => {
    stub()
    renderPage()
    const group = await screen.findByRole('button', { name: /Grupo 1/ })
    await waitFor(() => expect(within(screen.getByRole('region', { name: 'Resumen de capacidad del examen' })).getByText('20')).toBeInTheDocument())
    await userEvent.click(group)
    await userEvent.type(screen.getByLabelText('Nombre del examen *'), 'Parcial')
    expect(screen.queryByRole('region', { name: 'Resumen de capacidad del examen' })).not.toBeInTheDocument()
  })

  it.each([
    ['/examenes/nuevo?grupo=100', '/grupos/100/examenes'],
    ['/examenes/nuevo', '/examenes/programados'],
  ])('Cancelar desde %s lleva a %s', async (route, destination) => {
    stub()
    renderPage(route)
    await userEvent.click(await screen.findByRole('button', { name: 'Cancelar' }))
    expect(screen.getByTestId('location')).toHaveTextContent(destination)
  })

  it('la entrada general conserva la materia y los grupos inicialmente vacíos', async () => {
    stub()
    renderPage('/examenes/nuevo')
    expect(await screen.findByRole('combobox', { name: 'Materia *' })).toHaveTextContent('Seleccionar materia...')
    expect(screen.queryByRole('button', { name: /Grupo 1/ })).not.toBeInTheDocument()
  })

  it('explica que el grupo no está entre las opciones y ofrece regresar', async () => {
    stub()
    renderPage('/examenes/nuevo?grupo=999')
    expect(await screen.findByRole('alert')).toHaveTextContent('El grupo no está disponible')
    expect(screen.getByRole('link', { name: 'Volver a los exámenes del grupo' })).toHaveAttribute('href', '/grupos/999/examenes')
    expect(screen.queryByRole('button', { name: 'Guardar examen' })).not.toBeInTheDocument()
  })

  it('un identificador inválido no abre el formulario ni consulta opciones', () => {
    const { calls } = stub()
    renderPage('/examenes/nuevo?grupo=abc')
    expect(screen.getByRole('alert')).toHaveTextContent('El identificador del grupo no es válido')
    expect(calls).toHaveLength(0)
  })

  it('respeta la restricción existente de grupos sin nómina y permite regresar', async () => {
    stub({ ...options, grupos: options.grupos.map((group) => ({ ...group, tiene_nomina: false })) })
    renderPage()
    expect(await screen.findByRole('alert')).toHaveTextContent('El grupo no tiene nómina')
    expect(screen.getByRole('link', { name: 'Volver a los exámenes del grupo' })).toHaveAttribute('href', '/grupos/100/examenes')
  })


  it('quitar el grupo origen antes de crear conserva la selección nueva y abre el detalle sin ese contexto', async () => {
    const otherGroup = { ...options.grupos[1], id_carrera: 1 }
    const { calls } = mockApiWith(({ url, method }) => {
      if (url.endsWith('/examenes/formulario')) return { body: { data: {
        ...options,
        grupos: [options.grupos[0], otherGroup],
        ambientes: [{ id_ambiente: 1, nro_aula: '691A', capacidad: 40 }],
      } } }
      if (url.endsWith('/examenes') && method === 'POST') return { body: { data: {
        id_examen: 77, nombre_examen: 'Parcial', fecha: '2099-01-01', hora_inicio: '08:00', hora_fin: '09:30', grupos: [otherGroup],
      } } }
    })
    renderPage()
    await waitFor(() => expect(screen.getByRole('region', { name: 'Resumen de capacidad del examen' })).toHaveTextContent('20'))
    await userEvent.click(screen.getByRole('button', { name: /Grupo 2/ }))
    await userEvent.click(screen.getByRole('button', { name: /Grupo 1/ }))
    await userEvent.type(screen.getByLabelText('Nombre del examen *'), 'Parcial')
    fireEvent.change(screen.getByLabelText('Fecha *'), { target: { value: '2099-01-01' } })
    await userEvent.click(screen.getByRole('checkbox', { name: /Aula 691A/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Guardar examen' }))
    expect(await screen.findByRole('link', { name: 'Ver detalle del examen' })).toHaveAttribute('href', '/examenes/77')
    expect(calls.filter((call) => call.method === 'POST')).toEqual([
      expect.objectContaining({ body: expect.objectContaining({ grupos: [200] }) }),
    ])
    expect(calls).toHaveLength(2)
  })

})
