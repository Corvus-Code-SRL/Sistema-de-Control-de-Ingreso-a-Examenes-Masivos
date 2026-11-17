import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { EXCEPTIONAL_MESSAGES } from '../components/exceptionalStudentMessages'
import type { ExceptionalCandidate, ExceptionalExam } from '../types/exceptional-student.types'
import { ExceptionalStudentPage } from './ExceptionalStudentPage'

const exam: ExceptionalExam = {
  id_examen: 7,
  nombre_examen: 'Parcial de Cálculo II',
  fecha: '2026-11-20',
  hora_inicio: '08:00',
  estado: 'PROGRAMADO',
  permitido: true
}

const candidate: ExceptionalCandidate = {
  id_estudiante: 55,
  cod_sis: '202600777',
  ci: '9876543',
  nombre_completo: 'Ana Quispe Mamani',
  carrera: { id_carrera: 1, nombre: 'Ingeniería de Sistemas' },
  ya_en_examen: false
}

const REASON = 'Convalidación aprobada por la Dirección de Carrera'

interface Options {
  exams?: ExceptionalExam[]
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

function setup(options: Options = {}) {
  const api = mockApiWith((request: RecordedRequest) => {
    const url = new URL(request.url)

    if (url.pathname.endsWith('/docente/estudiantes-excepcionales/examenes')) {
      return { body: { data: options.exams ?? [exam] } }
    }

    if (url.pathname.endsWith('/examenes/7/estudiantes-excepcionales/opciones')) {
      return {
        body: {
          data: {
            grupos: [{ id_grupo: 11, nombre: 'Grupo A', carrera_principal: 1 }],
            carreras: [{ id_grupo: 11, id_carrera: 1, nombre: 'Ingeniería de Sistemas' }],
            ambientes: [{ id_ambiente: 21, nombre: 'Aula 101', capacidad: 40, ocupados: 12 }]
          }
        }
      }
    }

    if (url.pathname.endsWith('/estudiantes-excepcionales/buscar')) {
      if (options.candidate === null) {
        return { status: 404, body: { message: EXCEPTIONAL_MESSAGES.notFound } }
      }

      return { body: { data: options.candidate ?? candidate } }
    }

    if (url.pathname.endsWith('/examenes/7/estudiantes-excepcionales') && request.method === 'POST') {
      return (
        options.register ?? {
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

async function openExams(user: User) {
  await user.click(await screen.findByRole('combobox', { name: 'Examen' }))
}

async function chooseExam(user: User, name: RegExp = /Parcial de Cálculo II/) {
  await openExams(user)
  await user.click(await screen.findByRole('option', { name }))
}

async function search(user: User, code = '202600777') {
  await user.type(screen.getByLabelText('Código SIS'), code)
  await user.click(screen.getByRole('button', { name: 'Buscar' }))
}

describe('ExceptionalStudentPage (HU-023)', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('ofrece los exámenes del docente con su fecha y su estado', async () => {
    const user = userEvent.setup()

    setup({
      exams: [
        exam,
        { ...exam, id_examen: 8, nombre_examen: 'Final de Física I', estado: 'EN_CURSO', permitido: false }
      ]
    })

    await openExams(user)

    expect(await screen.findByRole('option', { name: /Parcial de Cálculo II/ })).toHaveTextContent(
      '20/11/2026 08:00 · Programado'
    )
    expect(screen.getByRole('option', { name: /Final de Física I/ })).toHaveTextContent('En curso')
  })

  it('sin exámenes muestra el estado vacío con su propio texto', async () => {
    setup({ exams: [] })

    expect(await screen.findByText(EXCEPTIONAL_MESSAGES.emptyTitle)).toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Examen' })).not.toBeInTheDocument()
  })

  it('busca por código SIS y muestra nombre completo, carrera y CI', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)

    expect(await screen.findByText('Ana Quispe Mamani')).toBeInTheDocument()
    expect(screen.getByText('Ingeniería de Sistemas', { selector: 'dd' })).toBeInTheDocument()
    expect(screen.getByText('CI')).toBeInTheDocument()
    expect(screen.getByText('9876543')).toBeInTheDocument()

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

  it('el motivo es obligatorio y no se registra nada sin él', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await user.click(await screen.findByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.reasonRequired)
    expect(posts(api.calls)).toHaveLength(0)
  })

  it('un motivo demasiado corto se rechaza sin llamar al servidor', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), 'abc')
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.reasonTooShort)
    expect(posts(api.calls)).toHaveLength(0)
  })

  it('registra al estudiante en el examen elegido con su motivo', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), REASON)
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

  it('un estudiante que ya figura en la lista del examen lo avisa y no ofrece registrarlo', async () => {
    const user = userEvent.setup()
    setup({ candidate: { ...candidate, ya_en_examen: true } })

    await chooseExam(user)
    await search(user)

    expect(await screen.findByRole('alert')).toHaveTextContent(EXCEPTIONAL_MESSAGES.alreadyInExam)
    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
  })

  it('un examen que ya no admite excepcionales lo avisa y no deja buscar', async () => {
    const user = userEvent.setup()

    setup({
      exams: [{ ...exam, id_examen: 8, nombre_examen: 'Final de Física I', estado: 'EN_CURSO', permitido: false }]
    })

    await chooseExam(user, /Final de Física I/)

    expect(await screen.findByRole('status')).toHaveTextContent(EXCEPTIONAL_MESSAGES.notAllowed)
    expect(screen.getByLabelText('Código SIS')).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Buscar' })).toBeDisabled()
  })

  it('si el servidor rechaza el registro con 409 muestra su mensaje', async () => {
    const user = userEvent.setup()
    const message = 'El examen ya no admite estudiantes excepcionales.'
    setup({ register: { status: 409, body: { message } } })

    await chooseExam(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), REASON)
    await user.click(screen.getByRole('button', { name: 'Registrar estudiante' }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.queryByText('Estudiante excepcional registrado.')).not.toBeInTheDocument()
  })

  it('Cancelar vuelve a la búsqueda sin agregar nada', async () => {
    const user = userEvent.setup()
    const api = setup()

    await chooseExam(user)
    await search(user)
    await user.type(await screen.findByLabelText('Motivo del registro excepcional'), REASON)
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('button', { name: 'Registrar estudiante' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Código SIS')).toHaveValue('')
    expect(posts(api.calls)).toHaveLength(0)
  })
})
