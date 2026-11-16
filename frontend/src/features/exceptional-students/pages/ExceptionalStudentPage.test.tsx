import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { EXCEPTIONAL_MESSAGES } from '../components/exceptionalStudentMessages'
import type { ExceptionalCandidate, ExceptionalGroup } from '../types/exceptional-student.types'
import { ExceptionalStudentPage } from './ExceptionalStudentPage'

const sistemas = { id_carrera: 1, nombre: 'Ingeniería de Sistemas', principal: true }
const informatica = { id_carrera: 2, nombre: 'Ingeniería Informática', principal: false }

const group: ExceptionalGroup = {
  id_grupo: 7,
  num_grupo: '1',
  label: 'Cálculo II · G1',
  bloqueado: false,
  carreras: [sistemas]
}

const candidate: ExceptionalCandidate = {
  id_estudiante: 55,
  cod_sis: '202600777',
  nombre_completo: 'Ana Quispe Mamani',
  ci: '9876543',
  carrera: { id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
  ya_inscrito: false
}

const REASON = 'Convalidación aprobada por la Dirección de Carrera'

interface Options {
  groups?: ExceptionalGroup[]
  candidate?: ExceptionalCandidate | null
  register?: { status: number; body: unknown }
}

function setup(options: Options = {}) {
  const api = mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)

    if (url.pathname.endsWith('/docente/estudiantes-excepcionales/grupos')) {
      return { body: { data: options.groups ?? [group] } }
    }

    if (url.pathname.endsWith('/estudiantes-excepcionales/buscar')) {
      if (options.candidate === null) {
        return { status: 404, body: { message: EXCEPTIONAL_MESSAGES.notFound } }
      }

      return { body: { data: options.candidate ?? candidate } }
    }

    if (url.pathname.endsWith('/grupos/7/estudiantes-excepcionales') && request.method === 'POST') {
      return (
        options.register ?? {
          status: 201,
          body: {
            data: {
              id_grupo: 7,
              id_estudiante: 55,
              cod_sis: '202600777',
              nombre_completo: 'Ana Quispe Mamani',
              carrera: { id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
              es_excepcional: true,
              motivo_excepcional: REASON,
              fecha_inscripcion: '2026-11-15'
            },
            mensaje: 'Estudiante excepcional registrado.'
          }
        }
      )
    }

    return undefined
  })

  renderWithRouter(<ExceptionalStudentPage />)

  return api
}

const posts = (calls: RecordedRequest[]) => calls.filter((call) => call.method === 'POST')

async function chooseGroup(user: ReturnType<typeof userEvent.setup>) {
  await user.selectOptions(await screen.findByRole('combobox', { name: 'Grupo' }), '7')
}

async function search(user: ReturnType<typeof userEvent.setup>, code = '202600777') {
  await user.type(screen.getByLabelText('Código SIS'), code)
  await user.click(screen.getByRole('button', { name: 'Buscar' }))
}

describe('ExceptionalStudentPage (HU-023)', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('ofrece solo los grupos del docente en una lista propia', async () => {
    setup({ groups: [group, { ...group, id_grupo: 8, num_grupo: '2', label: 'Física I · G2' }] })

    const select = await screen.findByRole('combobox', { name: 'Grupo' })

    expect(select).toHaveTextContent('Cálculo II · G1')
    expect(select).toHaveTextContent('Física I · G2')
  })

  it('sin grupos muestra el estado vacío con su propio texto', async () => {
    setup({ groups: [] })

    expect(await screen.findByText(EXCEPTIONAL_MESSAGES.emptyTitle)).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Grupo' })).not.toBeInTheDocument()
  })

  it('busca por código SIS y muestra nombre completo, CI y carrera', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseGroup(user)
    await search(user)

    expect(await screen.findByText('Ana Quispe Mamani')).toBeInTheDocument()
    expect(screen.getByText('9876543')).toBeInTheDocument()
    expect(screen.getByText('Ingeniería de Sistemas', { selector: 'dd' })).toBeInTheDocument()

    const searchCall = api.calls.find((call) => call.url.includes('/buscar'))

    expect(new URL(searchCall!.url).searchParams.get('cod_sis')).toBe('202600777')
  })

  it('sin carrera previa dice «Sin carrera registrada» y sin CI, «Sin CI registrado»', async () => {
    const user = userEvent.setup()
    setup({ candidate: { ...candidate, carrera: null, ci: null } })

    await chooseGroup(user)
    await search(user)

    expect(await screen.findByText(EXCEPTIONAL_MESSAGES.noCareer)).toBeInTheDocument()
    expect(screen.getByText(EXCEPTIONAL_MESSAGES.noCi)).toBeInTheDocument()
  })

  it('un código que no existe muestra el mensaje y no deja continuar', async () => {
    const user = userEvent.setup()
    setup({ candidate: null })

    await chooseGroup(user)
    await search(user, '209999999')

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.notFound)
    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
  })

  it('sin código SIS pide escribirlo y no consulta al servidor', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseGroup(user)
    await user.click(screen.getByRole('button', { name: 'Buscar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.sisRequired)
    expect(api.calls.some((call) => call.url.includes('/buscar'))).toBe(false)
  })

  it('el motivo es obligatorio y no se registra nada sin él', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseGroup(user)
    await search(user)
    await user.click(await screen.findByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.reasonRequired)
    expect(posts(api.calls)).toHaveLength(0)
  })

  it('registra con el motivo, sin enviar carrera si el grupo atiende una sola', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseGroup(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), REASON)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByText('Estudiante excepcional registrado.')).toBeInTheDocument()
    expect(posts(api.calls)).toHaveLength(1)
    expect(posts(api.calls)[0].body).toEqual({ id_estudiante: 55, motivo: REASON })
    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
  })

  it('con varias carreras preselecciona la última del estudiante y deja cambiarla', async () => {
    const user = userEvent.setup()
    const api = setup({ groups: [{ ...group, carreras: [sistemas, informatica] }] })

    await chooseGroup(user)
    await search(user)

    const career = await screen.findByRole('combobox', { name: 'Carrera' })

    expect(career).toHaveValue('1')

    await user.selectOptions(career, '2')
    await user.type(screen.getByLabelText('Motivo del registro excepcional'), REASON)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    await waitFor(() => expect(posts(api.calls)).toHaveLength(1))
    expect(posts(api.calls)[0].body).toEqual({ id_estudiante: 55, motivo: REASON, id_carrera: 2 })
  })

  it('con varias carreras y sin una previa que el grupo atienda exige elegir', async () => {
    const user = userEvent.setup()
    const api = setup({
      groups: [{ ...group, carreras: [sistemas, informatica] }],
      candidate: { ...candidate, carrera: null }
    })

    await chooseGroup(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), REASON)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.careerRequired)
    expect(posts(api.calls)).toHaveLength(0)
  })

  it('un estudiante ya inscrito en el grupo lo avisa y no ofrece registrarlo', async () => {
    const user = userEvent.setup()
    setup({ candidate: { ...candidate, ya_inscrito: true } })

    await chooseGroup(user)
    await search(user)

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.alreadyEnrolled)
    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
  })

  it('un grupo con examen en ingreso o en curso lo avisa y no deja buscar', async () => {
    const user = userEvent.setup()
    setup({ groups: [{ ...group, bloqueado: true }] })

    await chooseGroup(user)

    expect(await screen.findByRole('status')).toHaveTextContent(EXCEPTIONAL_MESSAGES.blocked)
    expect(screen.getByLabelText('Código SIS')).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Buscar' })).toBeDisabled()
  })

  it('si el servidor rechaza el registro con 409 muestra su mensaje', async () => {
    const user = userEvent.setup()
    const message =
      'No se puede registrar un estudiante excepcional mientras el grupo tiene un examen.'
    setup({ register: { status: 409, body: { message } } })

    await chooseGroup(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), REASON)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.queryByText('Estudiante excepcional registrado.')).not.toBeInTheDocument()
  })

  it('Cancelar vuelve a la búsqueda sin agregar nada', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseGroup(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), REASON)
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
    expect(posts(api.calls)).toHaveLength(0)
  })
})
