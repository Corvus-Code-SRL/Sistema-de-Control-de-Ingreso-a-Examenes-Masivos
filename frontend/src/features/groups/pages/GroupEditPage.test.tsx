import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { GroupEditPage } from './GroupEditPage'
import { makeForeignGroup, makeGroup } from '@/test/fixtures'
import { makeGroupStudents, stubGroupApi } from '@/test/groupStudentFixtures'
import type { RecordedRequest } from '@/test/http'

/** El detalle del grupo solo deja ver a dónde se llegó y con qué estado de navegación. */
function DetailProbe() {
  const { pathname, state } = useLocation()

  return <output data-testid="detail">{`${pathname} ${JSON.stringify(state)}`}</output>
}

function renderPage(route = '/grupos/100/editar') {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <Routes>
        <Route path="/grupos/:idGrupo/editar" element={<GroupEditPage />} />
        <Route path="/grupos/:idGrupo" element={<DetailProbe />} />
      </Routes>
    </MemoryRouter>
  )
}

const numberField = () => screen.findByLabelText(/N° de grupo/i)
const saveButton = () => screen.getByRole('button', { name: 'Guardar cambios' })

function updateRequests(calls: RecordedRequest[]) {
  return calls.filter((call) => call.method === 'PUT')
}

/** Peticiones GET cuya ruta coincide; la búsqueda (`?q=&page=`) no cuenta para la coincidencia. */
function getRequests(calls: RecordedRequest[], path: RegExp): number {
  return calls.filter((call) => call.method === 'GET' && path.test(new URL(call.url).pathname)).length
}

/** Deja correr los efectos pendientes: un bucle de recargas volvería a pedir en este intervalo. */
function settle() {
  return new Promise((resolve) => setTimeout(resolve, 100))
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('GroupEditPage — datos del grupo (CA-1)', () => {
  it('muestra el número actual, y materia, carrera, período y docente de solo lectura', async () => {
    stubGroupApi({ group: makeGroup({ num_grupo: '2' }), students: makeGroupStudents(3) })

    renderPage()

    expect(await numberField()).toHaveValue('2')
    expect(screen.getByRole('heading', { name: 'Editar grupo' })).toBeInTheDocument()
    expect(screen.getByText('Bases de Datos I · Ingenieria de Sistemas')).toBeInTheDocument()
    expect(screen.getByText('Paola Careaga')).toBeInTheDocument()
    expect(screen.getByText('Período académico')).toBeInTheDocument()
    expect(screen.getByText('2/2026')).toBeInTheDocument()
    // El período no se edita (HU-006): no hay selector ni se pide el catálogo de períodos.
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getAllByRole('textbox')).toHaveLength(1)
  })

  it('el total de inscritos es el de la lista del grupo, no el conteo del detalle', async () => {
    stubGroupApi({ group: makeGroup({ cantidad_estudiantes: 118 }), students: makeGroupStudents(3) })

    renderPage()

    expect(await screen.findByText('Los 3 estudiantes inscritos no se modifican')).toBeInTheDocument()
    expect(screen.queryByText(/118/)).not.toBeInTheDocument()
  })

  it('si no se pudo leer el total, omite la cifra en lugar de mostrar una equivocada', async () => {
    stubGroupApi({ group: makeGroup({ cantidad_estudiantes: 118 }), studentsStatus: 500 })

    renderPage()

    expect(await screen.findByText('Solo cambian los datos del grupo.')).toBeInTheDocument()
    expect(screen.queryByText(/estudiantes inscritos no se modifican/)).not.toBeInTheDocument()
  })
})

describe('GroupEditPage — validación (CA-2)', () => {
  it('un número vacío muestra el error junto al campo y no envía', async () => {
    const user = userEvent.setup()
    const api = stubGroupApi()
    renderPage()

    await user.clear(await numberField())
    await user.click(saveButton())

    expect(screen.getByText('Debe indicarse el número de grupo.')).toBeInTheDocument()
    expect(await numberField()).toHaveAttribute('aria-invalid', 'true')
    expect(updateRequests(api.calls)).toHaveLength(0)
  })

  it('más de 5 caracteres muestra el error junto al campo y no envía', async () => {
    const user = userEvent.setup()
    const api = stubGroupApi()
    renderPage()

    await user.clear(await numberField())
    await user.type(await numberField(), '123456')
    await user.click(saveButton())

    expect(
      screen.getByText('El número de grupo no puede superar los 5 caracteres.')
    ).toBeInTheDocument()
    expect(updateRequests(api.calls)).toHaveLength(0)
  })
})

describe('GroupEditPage — duplicado (CA-3)', () => {
  it('muestra junto al campo el grupo con el que choca', async () => {
    const user = userEvent.setup()
    stubGroupApi({
      group: makeGroup({ num_grupo: '2' }),
      updateResponse: {
        status: 422,
        body: {
          message: 'The given data was invalid.',
          errors: { num_grupo: ['Ya existe el grupo 3 para esta materia y período.'] },
        },
      },
    })
    renderPage()

    await user.clear(await numberField())
    await user.type(await numberField(), '3')
    await user.click(saveButton())

    expect(
      await screen.findByText('Ya existe el grupo 3 para esta materia y período.')
    ).toBeInTheDocument()
    expect(screen.queryByTestId('detail')).not.toBeInTheDocument()
  })
})

describe('GroupEditPage — guardar y cancelar (CA-4 y CA-6)', () => {
  it('guardar envía solo número y período y vuelve al detalle con «Grupo actualizado»', async () => {
    const user = userEvent.setup()
    const api = stubGroupApi()
    renderPage()

    await user.clear(await numberField())
    await user.type(await numberField(), 'B')
    await user.click(saveButton())

    const detail = await screen.findByTestId('detail')

    expect(detail).toHaveTextContent('/grupos/100')
    expect(detail).toHaveTextContent('Grupo actualizado')

    const [request] = updateRequests(api.calls)

    expect(request.url).toMatch(/\/grupos\/100$/)
    expect(request.body).toEqual({ num_grupo: 'B' })
  })

  it('cancelar vuelve al detalle sin guardar ni dejar aviso', async () => {
    const user = userEvent.setup()
    const api = stubGroupApi()
    renderPage()

    await user.clear(await numberField())
    await user.type(await numberField(), 'B')
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(await screen.findByTestId('detail')).toHaveTextContent('/grupos/100 null')
    expect(updateRequests(api.calls)).toHaveLength(0)
  })
})

describe('GroupEditPage — examen en curso (CA-5)', () => {
  it('avisa al abrir y no permite guardar', async () => {
    stubGroupApi({ edicionBloqueada: true })

    renderPage()

    expect(
      await screen.findByText('No se puede editar mientras un examen del grupo está en curso')
    ).toBeInTheDocument()
    expect(saveButton()).toBeDisabled()
    expect(await numberField()).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeEnabled()
  })

  it('si el examen empezó después de abrir la vista, muestra el rechazo del servidor', async () => {
    const user = userEvent.setup()
    stubGroupApi({
      updateResponse: {
        status: 422,
        body: { message: 'No se puede editar mientras un examen del grupo está en curso.' },
      },
    })
    renderPage()

    await user.clear(await numberField())
    await user.type(await numberField(), 'B')
    await user.click(saveButton())

    expect(
      await screen.findByText('No se puede editar mientras un examen del grupo está en curso.')
    ).toBeInTheDocument()
    expect(screen.queryByText(/Revise 0 campos/)).not.toBeInTheDocument()
  })
})

describe('GroupEditPage — acceso y solo lectura', () => {
  it('un grupo inexistente muestra «El grupo no existe»', async () => {
    stubGroupApi({ detailStatus: 404, studentsStatus: 404 })

    renderPage('/grupos/999/editar')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
  })

  it('un id inválido muestra «El grupo no existe» sin consultar', async () => {
    const api = stubGroupApi()

    renderPage('/grupos/abc/editar')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
    expect(api.calls).toHaveLength(0)
  })

  it('un grupo de otro docente niega el acceso y no muestra el formulario', async () => {
    stubGroupApi({ group: makeForeignGroup(), studentsStatus: 403 })

    renderPage('/grupos/200/editar')

    expect(await screen.findByText('Este curso no es suyo')).toBeInTheDocument()
    expect(screen.queryByLabelText(/N° de grupo/i)).not.toBeInTheDocument()
  })

  it.each([
    [
      'de un período anterior',
      { esPeriodoActivo: false },
      'Este grupo pertenece a un período anterior; solo se puede consultar',
    ],
    ['inactivo', { group: makeGroup({ activo: false }) }, 'Este grupo ya no se puede editar'],
  ])('un grupo %s no se edita, no envía nada y ofrece volver al grupo', async (_label, options, title) => {
    const api = stubGroupApi(options)

    renderPage()

    expect(await screen.findByText(title)).toBeInTheDocument()
    expect(screen.queryByLabelText(/N° de grupo/i)).not.toBeInTheDocument()
    expect(updateRequests(api.calls)).toHaveLength(0)

    const back = screen.getAllByRole('link', { name: 'Volver al grupo' })

    expect(back.length).toBeGreaterThan(0)
    back.forEach((link) => expect(link).toHaveAttribute('href', '/grupos/100'))
  })
})

describe('GroupEditPage — peticiones', () => {
  it('pide el grupo y su total una sola vez, y nunca el catálogo de períodos', async () => {
    const api = stubGroupApi({ students: makeGroupStudents(3) })

    renderPage()

    expect(await screen.findByText('Los 3 estudiantes inscritos no se modifican')).toBeInTheDocument()
    await settle()

    expect(getRequests(api.calls, /\/grupos\/100$/)).toBe(1)
    expect(getRequests(api.calls, /\/grupos\/100\/estudiantes/)).toBe(1)
    expect(getRequests(api.calls, /\/periodos/)).toBe(0)
  })
})
