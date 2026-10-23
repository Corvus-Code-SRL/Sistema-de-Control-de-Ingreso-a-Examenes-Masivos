import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { GroupDetailPage } from './GroupDetailPage'
import { makeForeignGroup, makeGroup, makeGroupWithoutRoster } from '@/test/fixtures'
import {
  makeGroupStudent,
  makeGroupStudents,
  stubGroupApi,
} from '@/test/groupStudentFixtures'
import { renderWithRouter } from '@/test/render'

function LocationProbe() {
  const { pathname, search, state } = useLocation()

  return (
    <>
      <output data-testid="location">{`${pathname}${search}`}</output>
      <output data-testid="location-state">{JSON.stringify(state)}</output>
    </>
  )
}

function renderPage(route = '/grupos/100') {
  return renderWithRouter(
    <>
      <GroupDetailPage />
      <LocationProbe />
    </>,
    { route, path: '/grupos/:idGrupo' }
  )
}

const location = () => screen.getByTestId('location').textContent

async function loadedTable() {
  return within(await screen.findByRole('table'))
}

function studentRequests(calls: { url: string }[]) {
  return calls.filter((call) => /\/grupos\/\d+\/estudiantes/.test(call.url))
}

/** Peticiones GET cuya ruta coincide; la búsqueda (`?q=&page=`) no cuenta para la coincidencia. */
function getRequests(calls: { url: string; method: string }[], path: RegExp): number {
  return calls.filter((call) => call.method === 'GET' && path.test(new URL(call.url).pathname)).length
}

/** Deja correr los efectos pendientes: un bucle de recargas volvería a pedir en este intervalo. */
function settle() {
  return new Promise((resolve) => setTimeout(resolve, 100))
}

const PAST_PERIOD_NOTICE = 'Este grupo pertenece a un período anterior; solo se puede consultar'

const maria = makeGroupStudent({
  id_estudiante: 7,
  cod_sis: '202155501',
  apellido_paterno: 'Núñez',
  apellido_materno: 'Quiroga',
  nombre: 'María',
})

/** 35 inscritos ordenados por apellido; María Núñez entre ellos. */
function thirtyFive() {
  const others = makeGroupStudents(34)

  return [...others.slice(0, 20), maria, ...others.slice(20)]
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('GroupDetailPage — datos del grupo y lista (CA-1)', () => {
  it('muestra el total de inscritos y la tabla con código SIS, apellidos y nombres', async () => {
    // El conteo del encabezado sale de la lista, no de cantidad_estudiantes del detalle.
    stubGroupApi({ group: makeGroup({ cantidad_estudiantes: 118 }), students: thirtyFive() })

    renderPage()

    const table = await loadedTable()

    expect(screen.getAllByText(/bases de datos i · grupo 1/i).length).toBeGreaterThan(0)
    expect(table.getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Código SIS',
      'Apellidos',
      'Nombres',
    ])
    expect(table.getAllByRole('row').slice(1)).toHaveLength(35)
    expect(table.getByText('Núñez Quiroga')).toBeInTheDocument()
    expect(screen.getByText('35')).toBeInTheDocument()
    expect(screen.queryByText('118')).not.toBeInTheDocument()
    expect(screen.getByText('Nómina cargada')).toBeInTheDocument()
    expect(screen.getByText('Mostrando 1–35 de 35 estudiantes')).toBeInTheDocument()
  })

  it('muestra al docente del grupo en su propia tarjeta del resumen', async () => {
    stubGroupApi({
      group: makeGroup({ docente: { nombre_completo: 'Marcelo Quiroga' } }),
      students: [maria],
    })

    renderPage()
    await loadedTable()

    // «Docente» también es el rol de la sesión en la barra lateral: se busca el rótulo de la tarjeta.
    const teacherCard = screen
      .getByText('Docente', { selector: '[data-slot="card"] > p' })
      .closest<HTMLElement>('[data-slot="card"]')

    if (teacherCard === null) throw new Error('La tarjeta «Docente» no está dentro de una Card.')

    expect(within(teacherCard).getByText('Marcelo Quiroga')).toBeInTheDocument()
  })

  it('respeta el orden por apellido que entrega el servidor', async () => {
    stubGroupApi({
      students: [
        makeGroupStudent({ id_estudiante: 1, apellido_paterno: 'Arnez', apellido_materno: null, nombre: 'Ana' }),
        makeGroupStudent({ id_estudiante: 2, apellido_paterno: 'Ávila', apellido_materno: 'Zurita', nombre: 'Bruno' }),
      ],
    })

    renderPage()

    const rows = (await loadedTable()).getAllByRole('row').slice(1)

    expect(rows.map((row) => within(row).getAllByRole('cell')[1].textContent)).toEqual([
      'Arnez',
      'Ávila Zurita',
    ])
  })

  it('es una sola vista: no tiene pestañas ni contenido de exámenes o auxiliares', async () => {
    stubGroupApi({ students: thirtyFive() })

    renderPage()
    await loadedTable()

    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
  })

  it('en móvil la misma lista sale como elementos con nombre y código SIS', async () => {
    stubGroupApi({ students: [maria] })

    renderPage()
    await loadedTable()

    const list = within(screen.getByRole('list', { name: 'Estudiantes' }))

    expect(list.getByText('Núñez Quiroga, María')).toBeInTheDocument()
    expect(list.getByText('SIS 202155501')).toBeInTheDocument()
  })

  it('señala cuando el grupo no pertenece al período activo', async () => {
    stubGroupApi({ esPeriodoActivo: false, students: [maria] })

    renderPage()
    await loadedTable()

    expect(screen.getByText('Período anterior')).toBeInTheDocument()
  })
})

describe('GroupDetailPage — búsqueda (CA-2)', () => {
  it('«nunez» deja solo a María Núñez, sin cambiar el total, y queda en la URL', async () => {
    const user = userEvent.setup()
    const api = stubGroupApi({ students: thirtyFive() })
    renderPage()
    await loadedTable()

    await user.type(
      screen.getByRole('searchbox', { name: 'Buscar por apellido, nombre o código SIS' }),
      'nunez'
    )

    await waitFor(() => expect(location()).toBe('/grupos/100?q=nunez'), { timeout: 3000 })
    await waitFor(async () => expect((await loadedTable()).getAllByRole('row')).toHaveLength(2), {
      timeout: 3000,
    })

    expect((await loadedTable()).getByText('Núñez Quiroga')).toBeInTheDocument()
    expect(screen.getByText('Mostrando 1–1 de 1 estudiantes')).toBeInTheDocument()
    expect(screen.getByText('35')).toBeInTheDocument()

    // Espera a que termine de escribir: no pide una consulta por cada letra.
    const typed = studentRequests(api.calls).map((call) => new URL(call.url).searchParams.get('q'))

    expect(typed).not.toContain('n')
    expect(typed).not.toContain('nun')
    expect(typed).toContain('nunez')
  })

  it('también busca por código SIS', async () => {
    const user = userEvent.setup()
    stubGroupApi({ students: thirtyFive() })
    renderPage()
    await loadedTable()

    await user.click(screen.getByRole('searchbox'))
    await user.paste('202155501')

    expect(
      await screen.findByText('Mostrando 1–1 de 1 estudiantes', {}, { timeout: 3000 })
    ).toBeInTheDocument()
    expect((await loadedTable()).getByText('Núñez Quiroga')).toBeInTheDocument()
  })

  it('una búsqueda en la URL se aplica al cargar y llena el campo', async () => {
    stubGroupApi({ students: thirtyFive() })

    renderPage('/grupos/100?q=maria')

    expect((await loadedTable()).getByText('Núñez Quiroga')).toBeInTheDocument()
    expect(screen.getByRole('searchbox')).toHaveValue('maria')
  })

  it('sin coincidencias lo explica y ofrece limpiar la búsqueda', async () => {
    const user = userEvent.setup()
    stubGroupApi({ students: thirtyFive() })
    renderPage('/grupos/100?q=zzz')

    expect(await screen.findByText('Ningún estudiante coincide con la búsqueda')).toBeInTheDocument()
    expect(screen.getByText(/contenga «zzz»/)).toBeInTheDocument()
    expect(screen.queryByText('Este grupo aún no tiene estudiantes')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Limpiar búsqueda' }))

    await waitFor(() => expect(location()).toBe('/grupos/100'))
    expect(screen.getByRole('searchbox')).toHaveValue('')
    expect(await screen.findByText('Mostrando 1–35 de 35 estudiantes')).toBeInTheDocument()
  })
})

describe('GroupDetailPage — paginación (CA-3)', () => {
  it('muestra 50 por página y avanza con la URL', async () => {
    const user = userEvent.setup()
    stubGroupApi({ students: makeGroupStudents(120) })
    renderPage()

    expect((await loadedTable()).getAllByRole('row').slice(1)).toHaveLength(50)
    expect(screen.getByText('Mostrando 1–50 de 120 estudiantes')).toBeInTheDocument()
    expect(screen.getByText('120')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /siguiente/i }))

    await waitFor(() => expect(location()).toBe('/grupos/100?page=2'))
    expect(await screen.findByText('Mostrando 51–100 de 120 estudiantes')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /siguiente/i }))

    expect(await screen.findByText('Mostrando 101–120 de 120 estudiantes')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /siguiente/i })).toBeDisabled()
  })

  it('una página fuera de rango vuelve a la última que existe', async () => {
    stubGroupApi({ students: makeGroupStudents(120) })

    renderPage('/grupos/100?page=9')

    await waitFor(() => expect(location()).toBe('/grupos/100?page=3'))
    expect(await screen.findByText('Mostrando 101–120 de 120 estudiantes')).toBeInTheDocument()
  })

  it('buscar vuelve a la primera página', async () => {
    const user = userEvent.setup()
    stubGroupApi({ students: makeGroupStudents(120) })
    renderPage('/grupos/100?page=2')
    await screen.findByText('Mostrando 51–100 de 120 estudiantes')

    await user.type(screen.getByRole('searchbox'), 'Estudiante')

    await waitFor(() => expect(location()).toBe('/grupos/100?q=Estudiante'))
  })

  it('un valor inválido en la URL se limpia sin avisar', async () => {
    stubGroupApi({ students: [maria] })

    renderPage('/grupos/100?page=abc')

    await waitFor(() => expect(location()).toBe('/grupos/100'))
  })
})

describe('GroupDetailPage — grupo sin estudiantes (CA-4)', () => {
  it('lo dice y ofrece «Cargar nómina» hacia la vista de carga', async () => {
    stubGroupApi({ group: makeGroupWithoutRoster(), students: [] })

    renderPage('/grupos/300')

    expect(await screen.findByText('Este grupo aún no tiene estudiantes')).toBeInTheDocument()
    expect(screen.getByText('Sin inscritos')).toBeInTheDocument()
    expect(screen.getByText('Sin nómina')).toBeInTheDocument()
    expect(screen.queryByRole('searchbox')).not.toBeInTheDocument()

    const actions = screen.getAllByRole('link', { name: /cargar nómina/i })

    expect(actions.length).toBeGreaterThan(0)
    actions.forEach((action) => expect(action).toHaveAttribute('href', '/grupos/300/nomina'))
  })
})

describe('GroupDetailPage — acciones (CA-5)', () => {
  it('cada acción lleva a su propia vista del grupo', async () => {
    stubGroupApi({ students: [maria] })

    renderPage()
    await loadedTable()

    expect(screen.getByRole('link', { name: /cargar nómina/i })).toHaveAttribute(
      'href',
      '/grupos/100/nomina'
    )
    expect(screen.getByRole('link', { name: /ver exámenes/i })).toHaveAttribute(
      'href',
      '/grupos/100/examenes'
    )
    expect(screen.getByRole('link', { name: 'Auxiliares' })).toHaveAttribute(
      'href',
      '/grupos/100/auxiliares'
    )
  })

  it('«Editar grupo» lleva a la vista de edición del grupo, sin abrir un modal', async () => {
    stubGroupApi({ students: [maria] })

    renderPage()
    await loadedTable()

    expect(screen.getByRole('link', { name: /editar grupo/i })).toHaveAttribute(
      'href',
      '/grupos/100/editar'
    )
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('al volver de editar muestra «Grupo actualizado» una sola vez y no vuelve a pedir datos', async () => {
    const api = stubGroupApi({ students: [maria] })

    render(
      <MemoryRouter
        initialEntries={[
          {
            pathname: '/grupos/100',
            state: {
              flash: {
                title: 'Grupo actualizado',
                description: 'Los cambios se guardaron correctamente.',
              },
            },
          },
        ]}
      >
        <Routes>
          <Route
            path="/grupos/:idGrupo"
            element={
              <>
                <GroupDetailPage />
                <LocationProbe />
              </>
            }
          />
        </Routes>
      </MemoryRouter>
    )

    expect(await screen.findByText('Grupo actualizado')).toBeInTheDocument()
    expect(screen.getByText('Los cambios se guardaron correctamente.')).toBeInTheDocument()
    // El aviso se borra del historial: recargar la página no lo repite.
    await waitFor(() => expect(screen.getByTestId('location-state')).toHaveTextContent('null'))
    expect(location()).toBe('/grupos/100')

    // Borrar el aviso del historial no cambia la ruta ni la búsqueda: nada se vuelve a pedir.
    await settle()
    expect(getRequests(api.calls, /\/grupos\/100$/)).toBe(1)
    expect(getRequests(api.calls, /\/grupos\/100\/estudiantes$/)).toBe(1)
  })

  it('pide el grupo y sus estudiantes una sola vez', async () => {
    const api = stubGroupApi({ students: [maria] })

    renderPage()
    await loadedTable()
    await settle()

    expect(getRequests(api.calls, /\/grupos\/100$/)).toBe(1)
    expect(getRequests(api.calls, /\/grupos\/100\/estudiantes$/)).toBe(1)
  })

  it('sin aviso en la navegación no muestra ningún mensaje', async () => {
    stubGroupApi({ students: [maria] })

    renderPage()
    await loadedTable()

    expect(screen.queryByText('Grupo actualizado')).not.toBeInTheDocument()
    expect(screen.queryByText(PAST_PERIOD_NOTICE)).not.toBeInTheDocument()
  })

  it('un grupo de un período anterior es de solo lectura: sin editar ni cargar nómina', async () => {
    stubGroupApi({ group: makeGroupWithoutRoster(), esPeriodoActivo: false, students: [] })

    renderPage('/grupos/300')

    expect(await screen.findByText('Este grupo aún no tiene estudiantes')).toBeInTheDocument()
    // CA-6 (HU-019): el aviso explica por qué no se ofrece editar.
    expect(screen.getByText(PAST_PERIOD_NOTICE)).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /editar grupo/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /cargar nómina/i })).not.toBeInTheDocument()
    expect(screen.getByText('Este grupo ya no admite cargar la nómina.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /ver exámenes/i })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Auxiliares' })).toBeInTheDocument()
  })

  it('un grupo inactivo tampoco ofrece editar ni cargar nómina', async () => {
    stubGroupApi({ group: makeGroup({ activo: false }), students: [maria] })

    renderPage()
    await loadedTable()

    expect(screen.queryByRole('link', { name: /editar grupo/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /cargar nómina/i })).not.toBeInTheDocument()
  })
})

describe('GroupDetailPage — acceso y errores', () => {
  it('niega el acceso a un grupo de otro docente y no muestra su contenido', async () => {
    stubGroupApi({ group: makeForeignGroup(), students: [maria] })

    renderPage('/grupos/200')

    expect(await screen.findByText('Este curso no es suyo')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(screen.queryByText('Victor Perez')).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /ver exámenes/i })).not.toBeInTheDocument()
  })

  it('muestra el acceso denegado cuando el servidor responde 403', async () => {
    stubGroupApi({ detailStatus: 403, studentsStatus: 403 })

    renderPage('/grupos/200')

    expect(await screen.findByText('Este curso no es suyo')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /reintentar/i })).not.toBeInTheDocument()
  })

  it('distingue un grupo inexistente de uno sin permiso', async () => {
    stubGroupApi({ detailStatus: 404, studentsStatus: 404 })

    renderPage('/grupos/999999')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
    expect(screen.queryByText('Este curso no es suyo')).not.toBeInTheDocument()
  })

  it('un id que no es un entero acotado muestra «El grupo no existe» sin consultar', async () => {
    const api = stubGroupApi()

    renderPage('/grupos/1234567890')

    expect(await screen.findByText('El grupo no existe')).toBeInTheDocument()
    expect(api.calls).toHaveLength(0)
  })

  it('el error del detalle se muestra con opción de reintentar', async () => {
    stubGroupApi({ detailStatus: 500 })

    renderPage()

    expect(await screen.findByText('Error interno del servidor.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })

  it('si falla la lista, el detalle se muestra y la tabla ofrece reintentar', async () => {
    stubGroupApi({ studentsStatus: 500 })

    renderPage()

    expect(await screen.findByText('No se pudo leer la lista de estudiantes.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
    expect(screen.getByText('No disponible: inscritos')).toBeInTheDocument()
    expect(screen.queryByText('Este grupo aún no tiene estudiantes')).not.toBeInTheDocument()
  })
})
