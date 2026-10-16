import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { SubjectCareerAssignmentForm } from './SubjectCareerAssignmentForm'
import { mockApiWith, type RecordedRequest } from '@/test/http'

const career = { id_carrera: 3, nombre: 'Ingenieria de Sistemas', codigo: 'SIS', id_facultad: 1 }

/** 2/2026: el período vigente, donde se asigna. */
const periodo1 = { id_periodo: 5, etiqueta: '2/2026', nombre_periodo: 'Semestre 2', gestion: 2026, vigente: true }
/** 1/2026: período anterior, de solo consulta. */
const periodo2 = { id_periodo: 6, etiqueta: '1/2026', nombre_periodo: 'Semestre 1', gestion: 2026, vigente: false }

const READ_ONLY_NOTICE =
  'Este período es de solo consulta. Solo puedes asignar materias en el período vigente (2/2026).'

const ia = { id_materia: 10, nombre: 'Inteligencia Artificial', codigo: '2008001', descripcion: null, estado: 'ACTIVO' }
const calculo = { id_materia: 11, nombre: 'Cálculo I', codigo: '2008057', descripcion: null, estado: 'ACTIVO' }
const algebra = { id_materia: 12, nombre: 'Álgebra Lineal', codigo: '2008060', descripcion: null, estado: 'ACTIVO' }

interface BackendOptions {
  /** Materias asignables por id de período. */
  byPeriod?: Record<string, unknown[]>
  assignStatus?: number
  assignBody?: unknown
}

function stubBackend({ byPeriod, assignStatus = 201, assignBody }: BackendOptions = {}) {
  const subjectsByPeriod = byPeriod ?? { '5': [ia, calculo, algebra], '6': [algebra] }

  return mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)
    const path = url.pathname.replace(/^.*\/api/, '')

    if (path === '/administracion/carreras') {
      return { body: { data: [career] } }
    }

    if (path === '/administracion/carreras/3/materias-asignables') {
      return { body: { data: subjectsByPeriod[url.searchParams.get('id_periodo') ?? ''] ?? [] } }
    }

    if (path === '/administracion/carreras/3/materias' && request.method === 'POST') {
      const body = request.body as { id_materia: number; id_periodo: number }
      const period = body.id_periodo === 6 ? periodo2 : periodo1
      const subject = [ia, calculo, algebra].find((item) => item.id_materia === body.id_materia)

      return {
        status: assignStatus,
        body:
          assignBody ??
          {
            data: {
              id_carrera: 3,
              id_materia: body.id_materia,
              id_periodo: body.id_periodo,
              estado: 'ACTIVO',
              carrera: career,
              materia: subject,
              periodo: period,
            },
            mensaje: 'Materia asignada a la carrera en el período correctamente.',
          },
      }
    }

    return undefined
  })
}

async function pickCareer() {
  await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())
  await userEvent.click(screen.getByRole('combobox', { name: 'Carrera' }))
  await userEvent.click(await screen.findByRole('option', { name: 'SIS · Ingenieria de Sistemas' }))
}

async function openSubjects() {
  await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())
  await userEvent.click(screen.getByRole('combobox', { name: 'Materia' }))
}

async function pickSubject(optionName: string) {
  await openSubjects()
  await userEvent.click(await screen.findByRole('option', { name: optionName }))
}

function optionNames() {
  return screen.queryAllByRole('option').map((option) => option.textContent)
}

describe('SubjectCareerAssignmentForm', () => {
  it('mantiene la materia bloqueada con «Selecciona primero una carrera» hasta elegir carrera', async () => {
    stubBackend()

    render(<SubjectCareerAssignmentForm period={periodo1} />)

    const subjectSelect = screen.getByRole('combobox', { name: 'Materia' })

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())

    expect(subjectSelect).toBeDisabled()
    expect(subjectSelect).toHaveTextContent('Selecciona primero una carrera')
    expect(screen.getByRole('button', { name: 'Asignar materia' })).toBeDisabled()
  })

  it('al elegir carrera pide las asignables del período y habilita la materia', async () => {
    const api = stubBackend()

    render(<SubjectCareerAssignmentForm period={periodo1} />)
    await pickCareer()

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Materia' })).toBeEnabled())

    const call = api.calls.find((item) => item.url.includes('/materias-asignables'))

    expect(call).toBeDefined()
    expect(new URL(call!.url).searchParams.get('id_periodo')).toBe('5')
    expect(screen.getByRole('combobox', { name: 'Materia' })).toHaveTextContent('Selecciona una materia')
  })

  it('busca la materia sin tildes ni mayúsculas y por código', async () => {
    stubBackend()

    render(<SubjectCareerAssignmentForm period={periodo1} />)
    await pickCareer()
    await openSubjects()

    expect(optionNames()).toEqual([
      '2008001 · Inteligencia Artificial',
      '2008057 · Cálculo I',
      '2008060 · Álgebra Lineal',
    ])

    const search = screen.getByRole('combobox', { name: 'Buscar: Materia' })

    await userEvent.type(search, 'calculo')
    expect(optionNames()).toEqual(['2008057 · Cálculo I'])

    await userEvent.clear(search)
    await userEvent.type(search, 'ALGEBRA')
    expect(optionNames()).toEqual(['2008060 · Álgebra Lineal'])

    await userEvent.clear(search)
    await userEvent.type(search, '2008001')
    expect(optionNames()).toEqual(['2008001 · Inteligencia Artificial'])
  })

  it('muestra «Sin resultados» cuando nada coincide', async () => {
    stubBackend()

    render(<SubjectCareerAssignmentForm period={periodo1} />)
    await pickCareer()
    await openSubjects()

    await userEvent.type(screen.getByRole('combobox', { name: 'Buscar: Materia' }), 'zzzz')

    expect(await screen.findByText('Sin resultados')).toBeInTheDocument()
    expect(optionNames()).toEqual([])
  })

  it('el diálogo muestra materia, carrera y período, y nada se guarda hasta confirmar', async () => {
    const api = stubBackend()

    render(<SubjectCareerAssignmentForm period={periodo1} />)
    await pickCareer()
    await pickSubject('2008057 · Cálculo I')

    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).getByRole('heading', { name: 'Confirmar asignación' })).toBeInTheDocument()
    expect(within(dialog).getByText('SIS · Ingenieria de Sistemas')).toBeInTheDocument()
    expect(within(dialog).getByText('2008057 · Cálculo I')).toBeInTheDocument()
    expect(within(dialog).getByText('2/2026')).toBeInTheDocument()
    expect(api.calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it('confirma y asigna con el período, y anuncia «Materia asignada: … → … (período)»', async () => {
    const api = stubBackend()
    const onAssigned = vi.fn()

    render(<SubjectCareerAssignmentForm period={periodo1} onAssigned={onAssigned} />)
    await pickCareer()
    await pickSubject('2008057 · Cálculo I')

    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    expect(
      await screen.findByText('Materia asignada: Cálculo I → Ingenieria de Sistemas (2/2026)')
    ).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())

    const post = api.calls.find((call) => call.method === 'POST')

    expect(post?.url).toMatch(/\/administracion\/carreras\/3\/materias$/)
    expect(post?.body).toEqual({ id_materia: 11, id_periodo: 5 })
    expect(onAssigned).toHaveBeenCalledTimes(1)
    expect(onAssigned.mock.calls[0][0]).toMatchObject({ id_materia: 11, estado: 'ACTIVO' })
  })

  it('Cancelar cierra el diálogo sin asignar nada', async () => {
    const api = stubBackend()
    const onAssigned = vi.fn()

    render(<SubjectCareerAssignmentForm period={periodo1} onAssigned={onAssigned} />)
    await pickCareer()
    await pickSubject('2008057 · Cálculo I')

    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(api.calls.filter((call) => call.method === 'POST')).toHaveLength(0)
    expect(onAssigned).not.toHaveBeenCalled()
    expect(screen.queryByText(/Materia asignada:/)).not.toBeInTheDocument()
  })

  it('muestra el error de validación cuando el backend rechaza la asignación', async () => {
    stubBackend({
      assignStatus: 422,
      assignBody: {
        message: 'Los datos proporcionados no son validos.',
        errors: { id_materia: ['La materia ya está asignada a la carrera en este período.'] },
      },
    })

    render(<SubjectCareerAssignmentForm period={periodo1} />)
    await pickCareer()
    await pickSubject('2008057 · Cálculo I')

    await userEvent.click(screen.getByRole('button', { name: 'Asignar materia' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar asignación' }))

    expect(
      await screen.findByText('La materia ya está asignada a la carrera en este período.')
    ).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(document.getElementById('subject-select')).toHaveAttribute('aria-invalid', 'true')
  })

  it('al cambiar de período descarta la materia elegida y pide las del nuevo período', async () => {
    const api = stubBackend()

    const { rerender } = render(<SubjectCareerAssignmentForm period={periodo1} />)
    await pickCareer()
    await pickSubject('2008057 · Cálculo I')

    expect(screen.getByRole('combobox', { name: 'Materia' })).toHaveTextContent('2008057 · Cálculo I')

    rerender(<SubjectCareerAssignmentForm period={periodo2} />)

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Materia' })).toHaveTextContent('Selecciona una materia')
    )
    expect(screen.getByRole('button', { name: 'Asignar materia' })).toBeDisabled()

    await openSubjects()
    expect(optionNames()).toEqual(['2008060 · Álgebra Lineal'])

    const periods = api.calls
      .filter((call) => call.url.includes('/materias-asignables'))
      .map((call) => new URL(call.url).searchParams.get('id_periodo'))

    expect(periods).toEqual(['5', '6'])
  })

  it('en un período de solo consulta deshabilita carrera, materia y asignar, y muestra el aviso', async () => {
    const api = stubBackend()

    render(<SubjectCareerAssignmentForm period={periodo2} readOnlyNotice={READ_ONLY_NOTICE} />)

    expect(screen.getByText(READ_ONLY_NOTICE)).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: 'Materia' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Asignar materia' })).toBeDisabled()
    expect(api.calls.filter((call) => call.url.includes('/materias-asignables'))).toHaveLength(0)
    expect(api.calls.filter((call) => call.method === 'POST')).toHaveLength(0)
  })

  it('en el período vigente queda habilitado y sin aviso de solo consulta', async () => {
    stubBackend()

    render(<SubjectCareerAssignmentForm period={periodo1} readOnlyNotice={null} />)

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Carrera' })).toBeEnabled())
    expect(screen.queryByText(/solo consulta/)).not.toBeInTheDocument()
  })

  it('sin período vigente el aviso explica que no se puede asignar hasta marcar uno', async () => {
    const notice =
      'Este período es de solo consulta. No hay un período vigente, así que no es posible asignar materias hasta que se marque uno como vigente.'

    render(<SubjectCareerAssignmentForm period={periodo1} readOnlyNotice={notice} />)

    expect(screen.getByText(notice)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Asignar materia' })).toBeDisabled()
  })

  it('indica cuando la carrera no tiene materias pendientes en el período', async () => {
    stubBackend({ byPeriod: { '5': [] } })

    render(<SubjectCareerAssignmentForm period={periodo1} />)
    await pickCareer()

    expect(
      await screen.findByText(/No hay materias activas pendientes de asignación para esta carrera en 2\/2026/)
    ).toBeInTheDocument()
  })
})
