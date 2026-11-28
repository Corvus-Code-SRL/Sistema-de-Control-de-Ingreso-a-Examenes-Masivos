import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { TOKEN_STORAGE_KEY, meBody } from '@/test/authFixtures'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderApp } from '@/test/renderApp'

const CAREER = { id_carrera: 1, nombre: 'Ingeniería de Sistemas', codigo: 'SIS', id_facultad: 1 }

const PERIODS = [
  { id_periodo: 2, etiqueta: '2/2026', nombre_periodo: '2', gestion: 2026, vigente: true },
  { id_periodo: 1, etiqueta: '1/2026', nombre_periodo: '1', gestion: 2026, vigente: false },
]

type Subject = { id_materia: number; codigo: string; nombre: string; estado: string; grupos_count: number }

const initialSubjects = (): Subject[] => [
  { id_materia: 10, codigo: '2008019', nombre: 'Cálculo I', estado: 'ACTIVO', grupos_count: 3 },
  { id_materia: 11, codigo: '2008054', nombre: 'Programación', estado: 'ACTIVO', grupos_count: 0 },
  { id_materia: 12, codigo: '2010010', nombre: 'Inglés I', estado: 'INACTIVO', grupos_count: 0 },
]

const PATH = '/catalogo/deshabilitar-materia'

function backend(options: { subjects?: Subject[]; postStatus?: number; postBody?: unknown } = {}) {
  window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')
  const subjects = options.subjects ?? initialSubjects()

  return mockApiWith(({ url, method, body }: RecordedRequest) => {
    if (url.endsWith('/auth/yo')) return { body: meBody('administrador') }
    if (url.includes('/administracion/carreras')) return { body: { data: [CAREER] } }
    if (url.includes('/periodos')) return { body: { data: PERIODS, meta: { id_periodo_activo: 2 } } }

    if (url.includes('/catalogo/reactivar-materia')) {
    if (options.postStatus) return { status: options.postStatus, body: options.postBody }

    const { id_materia } = body as { id_materia: number }
    const row = subjects.find((s) => s.id_materia === id_materia)
    if (row) row.estado = 'ACTIVO'
      return { body: { id_carrera: 1, id_materia, id_periodo: 2, estado: 'ACTIVO', grupos_count: 0 } }
    }
    if (url.includes('/catalogo/desvincular-materia')) {
      if (method === 'POST') {
        if (options.postStatus) return { status: options.postStatus, body: options.postBody }

        const { id_materia } = body as { id_materia: number }
        const row = subjects.find((s) => s.id_materia === id_materia)
        if (row) row.estado = 'INACTIVO'

        return {
          body: { id_carrera: 1, id_materia, id_periodo: 2, estado: 'INACTIVO', grupos_count: 0 },
        }
      }

      return { body: { materias: subjects, total: subjects.length, page: 1, per_page: 7 } }
    }

    return { body: { data: [] } }
  })
}

const listCalls = (calls: RecordedRequest[]) =>
  calls.filter((c) => c.method === 'GET' && c.url.includes('/catalogo/desvincular-materia'))

const postCalls = (calls: RecordedRequest[]) => calls.filter((c) => c.method === 'POST')

/** La tabla dibuja cada fila dos veces (móvil y escritorio): se espera por el botón, que es único. */
const waitForRow = (name: string) => screen.findByRole('button', { name })

describe('HU-008 · Desvincular materia de carrera', () => {
  afterEach(() => {
    window.localStorage.clear()
  })

  it('CA-1: sin carrera elegida pide elegirla y no consulta la lista', async () => {
    const { calls } = backend()
    renderApp(`${PATH}`)

    expect(await screen.findByText('Elija una carrera', { selector: 'p' })).toBeInTheDocument()
    expect(listCalls(calls)).toHaveLength(0)
  })

  it('CA-1: lista las materias del período vigente con grupos y estado', async () => {
    const { calls } = backend()
    renderApp(`${PATH}?carrera=1`)

    expect(await waitForRow('Desvincular Cálculo I')).toBeInTheDocument()
    expect(screen.getAllByText('3 grupos').length).toBeGreaterThanOrEqual(1)
    expect(screen.getAllByText('Desvinculada').length).toBeGreaterThanOrEqual(1)
    expect(screen.getByText(/1–3 de 3 materias/)).toBeInTheDocument()

    const url = new URL(listCalls(calls)[0].url)

    expect(url.searchParams.get('carrera')).toBe('1')
    expect(url.searchParams.get('periodo')).toBe('2')
    expect(url.searchParams.get('per_page')).toBe('7')
  })

  it('CA-2: la búsqueda se manda al servidor', async () => {
    const user = userEvent.setup()
    const { calls } = backend()
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Desvincular Cálculo I')
    await user.type(screen.getByLabelText('Buscar'), 'simulacion')

    await waitFor(() =>
      expect(listCalls(calls).some((c) => new URL(c.url).searchParams.get('buscar') === 'simulacion')).toBe(true)
    )
  })

  it('CA-3 y CA-7: el diálogo pide confirmación y Cancelar no envía nada', async () => {
    const user = userEvent.setup()
    const { calls } = backend()
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Desvincular Programación')
    await user.click(screen.getByRole('button', { name: 'Desvincular Programación' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).getByText('¿Desvincular Programación?')).toBeInTheDocument()
    expect(
      within(dialog).getByText('2008054 · Ingeniería de Sistemas · período 2/2026')
    ).toBeInTheDocument()

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(postCalls(calls)).toHaveLength(0)
  })

  it('CA-4: confirmar envía el POST, avisa y deja la fila como «Desvinculada»', async () => {
    const user = userEvent.setup()
    const { calls } = backend()
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Desvincular Programación')
    await user.click(screen.getByRole('button', { name: 'Desvincular Programación' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Desvincular' }))

    expect(await screen.findByText('Materia desvinculada')).toBeInTheDocument()
    expect(screen.getByText('Programación ya no se oferta en Ingeniería de Sistemas en 2/2026.')).toBeInTheDocument()
    expect(postCalls(calls)[0].body).toEqual({ id_carrera: 1, id_periodo: 2, id_materia: 11 })

    await waitFor(() => expect(screen.getAllByText('Desvinculada')).toHaveLength(2))
    expect(screen.getAllByText('Programación').length).toBeGreaterThanOrEqual(1)
  })

  it('CA-5: una materia con grupos explica el bloqueo y no abre el diálogo', async () => {
    const user = userEvent.setup()
    const { calls } = backend()
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Desvincular Cálculo I')
    await user.click(screen.getByRole('button', { name: 'Desvincular Cálculo I' }))

    expect(
      await screen.findByText('No se puede desvincular: la materia tiene 3 grupos registrados en este período')
    ).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(postCalls(calls)).toHaveLength(0)
  })

  it('CA-5: un 422 del servidor se muestra tal cual y recarga la lista', async () => {
    const user = userEvent.setup()
    const message = 'No se puede desvincular: la materia tiene 1 grupos registrados en este período.'
    const { calls } = backend({
      postStatus: 422,
      postBody: { message: 'The given data was invalid.', errors: { id_materia: [message] } },
    })
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Desvincular Programación')
    await user.click(screen.getByRole('button', { name: 'Desvincular Programación' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Desvincular' }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    await waitFor(() => expect(listCalls(calls).length).toBeGreaterThan(1))
  })

  it('CA-6: un período anterior es de solo consulta, sin botón Desvincular', async () => {
    backend()
    renderApp(`${PATH}?carrera=1&periodo=1`)

    expect(await screen.findByText('Período anterior: solo consulta')).toBeInTheDocument()
    expect(screen.getByText('Solo se puede desvincular o reactivar en el período vigente (2/2026).')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Desvincular / })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Reactivar / })).not.toBeInTheDocument()
  })

  it('CA-8: una carrera sin materias lo dice con el período', async () => {
    backend({ subjects: [] })
    renderApp(`${PATH}?carrera=1`)

    expect(await screen.findByText('Esta carrera no tiene materias asignadas en 2/2026')).toBeInTheDocument()
  })

  it('un identificador inválido en la URL se descarta y no consulta la lista', async () => {
    const { calls } = backend()
    renderApp(`${PATH}?carrera=abc`)

    expect(await screen.findByText('Elija una carrera', { selector: 'p' })).toBeInTheDocument()
    expect(listCalls(calls)).toHaveLength(0)
  })

    it('Reactivar: abre la confirmación y Cancelar no envía nada', async () => {
    const user = userEvent.setup()
    const { calls } = backend()
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Reactivar Inglés I')
    await user.click(screen.getByRole('button', { name: 'Reactivar Inglés I' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).getByText('¿Reactivar Inglés I?')).toBeInTheDocument()

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(postCalls(calls)).toHaveLength(0)
  })

  it('Reactivar: confirmar envía el POST, avisa y la fila vuelve a «Activo»', async () => {
    const user = userEvent.setup()
    const { calls } = backend()
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Reactivar Inglés I')
    await user.click(screen.getByRole('button', { name: 'Reactivar Inglés I' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Reactivar' }))

    expect(await screen.findByText('Materia reactivada')).toBeInTheDocument()
    expect(screen.getByText('Inglés I vuelve a ofertarse en Ingeniería de Sistemas en 2/2026.')).toBeInTheDocument()

    const post = postCalls(calls)[0]

    expect(new URL(post.url).pathname.endsWith('/catalogo/reactivar-materia')).toBe(true)
    expect(post.body).toEqual({ id_carrera: 1, id_periodo: 2, id_materia: 12 })

    await waitFor(() => expect(screen.queryByText('Desvinculada')).not.toBeInTheDocument())
    expect(screen.getByRole('button', { name: 'Desvincular Inglés I' })).toBeInTheDocument()
  })

  it('Reactivar: un 422 del servidor se muestra tal cual y recarga la lista', async () => {
    const user = userEvent.setup()
    const message = 'No se puede reactivar: la materia o su asignación a la carrera está inactiva.'
    const { calls } = backend({
      postStatus: 422,
      postBody: { message: 'The given data was invalid.', errors: { id_materia: [message] } },
    })
    renderApp(`${PATH}?carrera=1`)

    await waitForRow('Reactivar Inglés I')
    await user.click(screen.getByRole('button', { name: 'Reactivar Inglés I' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Reactivar' }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    await waitFor(() => expect(listCalls(calls).length).toBeGreaterThan(1))
  })
})
