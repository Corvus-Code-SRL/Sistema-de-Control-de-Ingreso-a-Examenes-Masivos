import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { EXCEPTIONAL_MESSAGES } from '../components/exceptionalStudentMessages'
import type {
  ExceptionalCandidate,
  ExceptionalExam,
  ExceptionalOptions
} from '../types/exceptional-student.types'
import { ExceptionalStudentPage } from './ExceptionalStudentPage'

const exam: ExceptionalExam = {
  id_examen: 7,
  nombre_examen: 'Parcial de Cálculo II',
  fecha: '2026-11-20',
  hora_inicio: '08:00',
  estado: 'EN_CURSO'
}

const candidate: ExceptionalCandidate = {
  id_estudiante: 55,
  cod_sis: '202600777',
  nombre_completo: 'Ana Quispe Mamani',
  carrera: { id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
  ya_en_examen: false
}

const singleGroupOptions: ExceptionalOptions = {
  grupos: [{ id_grupo: 11, nombre: 'Grupo A' }],
  carreras: [{ id_grupo: 11, id_carrera: 1, nombre: 'Ingeniería de Sistemas' }],
  ambientes: [
    { id_ambiente: 21, nombre: 'Aula 101', capacidad: 40, ocupados: 12 },
    { id_ambiente: 22, nombre: 'Aula 102', capacidad: 30, ocupados: 30 }
  ]
}

const twoGroupsOptions: ExceptionalOptions = {
  grupos: [
    { id_grupo: 11, nombre: 'Grupo A' },
    { id_grupo: 12, nombre: 'Grupo B' }
  ],
  carreras: [
    { id_grupo: 11, id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
    { id_grupo: 12, id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
    { id_grupo: 12, id_carrera: 2, nombre: 'Ingeniería Informática' }
  ],
  ambientes: singleGroupOptions.ambientes
}

const REASON = 'Convalidación aprobada por la Dirección de Carrera'

interface Setup {
  exams?: ExceptionalExam[]
  /** Respuestas sucesivas de la lista de exámenes; la última se repite. */
  examsAfterReload?: ExceptionalExam[]
  options?: ExceptionalOptions
  candidate?: ExceptionalCandidate | null
  register?: { status: number; body: unknown }
}

// El selector de la librería (Radix) usa APIs del navegador que jsdom no trae.
beforeAll(() => {
  if (!Element.prototype.hasPointerCapture) Element.prototype.hasPointerCapture = () => false
  if (!Element.prototype.setPointerCapture) Element.prototype.setPointerCapture = () => undefined
  if (!Element.prototype.releasePointerCapture) {
    Element.prototype.releasePointerCapture = () => undefined
  }
  if (!Element.prototype.scrollIntoView) Element.prototype.scrollIntoView = () => undefined
})

function setup(setupOptions: Setup = {}) {
  let examsCalls = 0

  const api = mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)

    if (url.pathname.endsWith('/docente/estudiantes-excepcionales/examenes')) {
      examsCalls += 1
      const reloaded = examsCalls > 1 && setupOptions.examsAfterReload !== undefined

      return { body: { data: reloaded ? setupOptions.examsAfterReload : (setupOptions.exams ?? [exam]) } }
    }

    if (url.pathname.endsWith('/examenes/7/estudiantes-excepcionales/opciones')) {
      return { body: { data: setupOptions.options ?? singleGroupOptions } }
    }

    if (url.pathname.endsWith('/estudiantes-excepcionales/buscar')) {
      if (setupOptions.candidate === null) {
        return { status: 404, body: { message: EXCEPTIONAL_MESSAGES.notFound } }
      }

      return { body: { data: setupOptions.candidate ?? candidate } }
    }

    if (url.pathname.endsWith('/examenes/7/estudiantes-excepcionales') && request.method === 'POST') {
      return (
        setupOptions.register ?? {
          status: 201,
          body: {
            data: {
              id_examen: 7,
              id_estudiante: 55,
              cod_sis: '202600777',
              nombre_completo: 'Ana Quispe Mamani',
              carrera: { id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
              motivo_excepcional: REASON,
              fecha_registro: '2026-11-17T09:00:00-04:00'
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

type User = ReturnType<typeof userEvent.setup>

async function pick(user: User, combobox: string, option: RegExp | string) {
  await user.click(await screen.findByRole('combobox', { name: combobox }))
  await user.click(await screen.findByRole('option', { name: option }))
}

async function chooseExam(user: User) {
  await pick(user, 'Examen', /Parcial de Cálculo II/)
}

async function search(user: User, code = '202600777') {
  await user.type(screen.getByLabelText('Código SIS'), code)
  await user.click(screen.getByRole('button', { name: 'Buscar' }))
}

async function fillReason(user: User, reason = REASON) {
  await user.type(await screen.findByLabelText('Motivo del registro excepcional'), reason)
}

describe('ExceptionalStudentPage (HU-023)', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('ofrece los exámenes En curso del docente con su fecha y hora', async () => {
    const user = userEvent.setup()
    setup()

    await user.click(await screen.findByRole('combobox', { name: 'Examen' }))

    expect(await screen.findByRole('option', { name: /Parcial de Cálculo II/ })).toHaveTextContent(
      '20/11/2026 08:00'
    )
  })

  it('sin exámenes En curso muestra el estado vacío con su propio texto', async () => {
    setup({ exams: [] })

    expect(await screen.findByText('No tiene exámenes En curso')).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Examen' })).not.toBeInTheDocument()
  })

  it('busca por código SIS y muestra nombre completo y carrera, sin el CI', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)

    expect(await screen.findByText('Ana Quispe Mamani')).toBeInTheDocument()
    expect(screen.getByText('Ingeniería de Sistemas', { selector: 'dd' })).toBeInTheDocument()
    expect(screen.queryByText('CI')).not.toBeInTheDocument()

    const searchCall = api.calls.find((call) => call.url.includes('/buscar'))

    expect(searchCall!.url).toContain('/examenes/7/estudiantes-excepcionales/buscar')
    expect(new URL(searchCall!.url).searchParams.get('cod_sis')).toBe('202600777')
  })

  it('sin carrera previa dice «Sin carrera registrada»', async () => {
    const user = userEvent.setup()
    setup({ candidate: { ...candidate, carrera: null } })

    await chooseExam(user)
    await search(user)

    expect(await screen.findByText(EXCEPTIONAL_MESSAGES.noCareer)).toBeInTheDocument()
  })

  it('un código que no existe muestra el mensaje y no deja continuar', async () => {
    const user = userEvent.setup()
    setup({ candidate: null })

    await chooseExam(user)
    await search(user, '209999999')

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.notFound)
    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
  })

  it('sin código SIS pide escribirlo y no consulta al servidor', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await user.click(screen.getByRole('button', { name: 'Buscar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.sisRequired)
    expect(api.calls.some((call) => call.url.includes('/buscar'))).toBe(false)
  })

  it('un código SIS que no tiene entre 8 y 9 dígitos se rechaza sin consultar al servidor', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)

    for (const code of ['1234567', 'ABC12345']) {
      await user.clear(screen.getByLabelText('Código SIS'))
      await search(user, code)

      expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.sisInvalid)
    }

    expect(api.calls.some((call) => call.url.includes('/buscar'))).toBe(false)
  })

  it('con un solo grupo y una sola carrera preselecciona el grupo y no pregunta la carrera', async () => {
    const user = userEvent.setup()
    setup()

    await chooseExam(user)
    await search(user)

    const group = await screen.findByRole('combobox', { name: EXCEPTIONAL_MESSAGES.groupLabel })

    expect(group).toHaveTextContent('Grupo A')
    expect(screen.queryByRole('combobox', { name: 'Carrera' })).not.toBeInTheDocument()
    // El ambiente se elige siempre: no viene preseleccionado.
    expect(screen.getByRole('combobox', { name: 'Ambiente' })).toHaveTextContent('Elija un ambiente')
  })

  it('el ambiente es obligatorio y no se registra nada sin elegirlo', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await fillReason(user)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.roomRequired)
    expect(posts(api.calls)).toHaveLength(0)
  })

  it('con varios grupos hay que elegir el grupo y, si ofrece varias carreras, la carrera', async () => {
    const user = userEvent.setup()
    const api = setup({ options: twoGroupsOptions })

    await chooseExam(user)
    await search(user)

    const group = await screen.findByRole('combobox', { name: EXCEPTIONAL_MESSAGES.groupLabel })

    expect(group).toHaveTextContent('Elija un grupo')

    await pick(user, 'Ambiente', /Aula 101/)
    await fillReason(user)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.groupRequired)

    // El grupo A ofrece una carrera: no se pregunta.
    await pick(user, EXCEPTIONAL_MESSAGES.groupLabel, 'Grupo A')
    expect(screen.queryByRole('combobox', { name: 'Carrera' })).not.toBeInTheDocument()

    // El grupo B ofrece dos: se pregunta y es obligatoria.
    await pick(user, EXCEPTIONAL_MESSAGES.groupLabel, 'Grupo B')
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))
    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.careerRequired)

    await pick(user, 'Carrera', 'Ingeniería Informática')
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByText('Estudiante excepcional registrado.')).toBeInTheDocument()
    expect(posts(api.calls)[0].body).toEqual({
      id_estudiante: 55,
      motivo: REASON,
      id_grupo: 12,
      id_carrera: 2,
      id_ambiente: 21
    })
  })

  it('el motivo es obligatorio y no se registra nada sin él', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await pick(user, 'Ambiente', /Aula 101/)
    await user.click(await screen.findByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.reasonRequired)
    expect(posts(api.calls)).toHaveLength(0)
  })

  it('un motivo demasiado corto se rechaza sin llamar al servidor', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await pick(user, 'Ambiente', /Aula 101/)
    await fillReason(user, 'abc')
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.reasonTooShort)
    expect(posts(api.calls)).toHaveLength(0)
  })

  it('registra al estudiante en el examen elegido con su grupo, ambiente y motivo', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await pick(user, 'Ambiente', /Aula 101/)
    await fillReason(user)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByText('Estudiante excepcional registrado.')).toBeInTheDocument()
    expect(posts(api.calls)).toHaveLength(1)
    expect(posts(api.calls)[0].url).toContain('/examenes/7/estudiantes-excepcionales')
    expect(posts(api.calls)[0].body).toEqual({
      id_estudiante: 55,
      motivo: REASON,
      id_grupo: 11,
      id_carrera: 1,
      id_ambiente: 21
    })
    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
  })

  it('un ambiente lleno avisa pero deja registrar', async () => {
    const user = userEvent.setup()
    setup()

    await chooseExam(user)
    await search(user)
    await pick(user, 'Ambiente', /Aula 102/)

    expect(await screen.findByRole('status')).toHaveTextContent(EXCEPTIONAL_MESSAGES.roomFull)
    expect(screen.getByRole('button', { name: 'Registrar estudiante' })).toBeEnabled()
  })

  it('un estudiante que ya figura en la lista del examen lo avisa y no ofrece registrarlo', async () => {
    const user = userEvent.setup()
    setup({ candidate: { ...candidate, ya_en_examen: true } })

    await chooseExam(user)
    await search(user)

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.alreadyInExam)
    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
  })

  it('si el examen dejó de estar En curso (409) muestra el mensaje y vuelve a cargar la lista', async () => {
    const user = userEvent.setup()
    const message = 'El examen ya no está en curso: no se pueden registrar estudiantes excepcionales.'
    const api = setup({ register: { status: 409, body: { message } }, examsAfterReload: [] })

    await chooseExam(user)
    await search(user)
    await pick(user, 'Ambiente', /Aula 101/)
    await fillReason(user)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByText('No tiene exámenes En curso')).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent(message)
    expect(screen.queryByText('Estudiante excepcional registrado.')).not.toBeInTheDocument()
    expect(
      api.calls.filter((call) => call.url.includes('/docente/estudiantes-excepcionales/examenes'))
    ).toHaveLength(2)
  })

  it('Cancelar vuelve a la búsqueda sin agregar nada', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await fillReason(user)
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
    expect(posts(api.calls)).toHaveLength(0)
  })
})
