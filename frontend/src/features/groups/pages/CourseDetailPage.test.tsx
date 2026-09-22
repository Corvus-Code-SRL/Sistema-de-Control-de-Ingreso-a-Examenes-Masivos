import { screen, waitForElementToBeRemoved } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { CourseDetailPage } from './CourseDetailPage'
import {
  groupDetailResponse,
  makeForeignGroup,
  makeGroup,
  makeGroupWithoutRoster,
  makeSubject,
} from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'

function renderPage(groupId = 100) {
  return renderWithRouter(<CourseDetailPage />, {
    route: `/cursos/${groupId}`,
    path: '/cursos/:idGrupo',
  })
}

async function waitForLoad() {
  await waitForElementToBeRemoved(() => screen.queryByRole('status'))
}

describe('CourseDetailPage', () => {
  const materia = makeSubject()

  it('muestra la cabecera y el armazón de pestañas de un curso propio', async () => {
    mockApiOnce({ body: groupDetailResponse(makeGroup(), materia) })

    renderPage()
    await waitForLoad()

    expect(screen.getAllByText(/bases de datos i · grupo 1/i).length).toBeGreaterThan(0)
    expect(screen.getByRole('tab', { name: /nómina/i })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: /auxiliares/i })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: /exámenes/i })).toBeInTheDocument()
    // El conteo aparece en el resumen y en la pestaña de nómina.
    expect(screen.getAllByText('118')).toHaveLength(2)
  })

  it('muestra la carga de nómina y mantiene pendientes las otras pestañas', async () => {
    mockApiOnce({ body: groupDetailResponse(makeGroup(), materia) })

    renderPage()
    await waitForLoad()

    // La pestaña activa anuncia su contenido pendiente en lugar de mostrar datos a medias.
    expect(screen.getByText(/cargar nómina/i)).toBeInTheDocument()
  })

  it('niega el acceso a un curso de otro docente y no muestra su contenido', async () => {
    mockApiOnce({ body: groupDetailResponse(makeForeignGroup(), materia) })

    renderPage(200)
    await waitForLoad()

    expect(screen.getByText('Este curso no es suyo')).toBeInTheDocument()
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(screen.queryByText('Victor Perez')).not.toBeInTheDocument()
  })

  it('niega el acceso igual cuando el servidor responde 403', async () => {
    mockApiOnce({ status: 403, body: { message: 'No autorizado.' } })

    renderPage(200)
    await waitForLoad()

    expect(screen.getByText('Este curso no es suyo')).toBeInTheDocument()
    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    // No se ofrece reintentar: repetir la petición daría el mismo resultado.
    expect(screen.queryByRole('button', { name: /reintentar/i })).not.toBeInTheDocument()
  })

  it('muestra un curso propio sin nómina como caso normal, no como error', async () => {
    mockApiOnce({ body: groupDetailResponse(makeGroupWithoutRoster(), materia) })

    renderPage(300)
    await waitForLoad()

    expect(screen.getAllByText('Sin nómina').length).toBeGreaterThan(0)
    expect(screen.getByRole('tab', { name: /nómina/i })).toBeInTheDocument()
    expect(screen.getByText('Sin inscritos')).toBeInTheDocument()
    expect(screen.queryByText('Este curso no es suyo')).not.toBeInTheDocument()
  })

  it('distingue un curso inexistente de uno sin permiso', async () => {
    mockApiOnce({ status: 404, body: { message: 'No existe el grupo indicado.' } })

    renderPage(999999)
    await waitForLoad()

    expect(screen.getByText('El curso no existe')).toBeInTheDocument()
    expect(screen.queryByText('Este curso no es suyo')).not.toBeInTheDocument()
  })

  it('muestra el error del servidor con opción de reintentar', async () => {
    mockApiOnce({ status: 500, body: { message: 'Error interno del servidor.' } })

    renderPage()
    await waitForLoad()

    expect(screen.getByRole('alert')).toBeInTheDocument()
    expect(screen.getByText('Error interno del servidor.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })

  it('señala cuando el curso no pertenece al período activo', async () => {
    mockApiOnce({ body: groupDetailResponse(makeGroup(), materia, false) })

    renderPage()
    await waitForLoad()

    expect(screen.getByText('Período anterior')).toBeInTheDocument()
  })
})
