import { describe, expect, it, vi } from 'vitest'
import { mockApiOnce } from '@/test/http'
import { examsService } from './examsService'
import type { CreateExamPayload, Exam } from '../types/exams.types'

const exam: Exam = {
  id_examen: 7,
  nombre_examen: 'Primer parcial',
  fecha: '2026-10-20',
  hora_inicio: '08:00:00',
  hora_fin: '10:00:00',
  duracion: 120,
  normas: null,
  estado: 'PROGRAMADO',
  id_tipo_examen: 1,
  id_carrera: 2,
  id_materia: 3,
  id_usuario_docente: 'DOC-1',
}

describe('examsService', () => {
  it('crea el examen con el par académico y los grupos seleccionados', async () => {
    const payload: CreateExamPayload = {
      nombre_examen: 'Primer parcial',
      id_carrera: 2,
      id_materia: 3,
      categoria: 'REGULAR',
      fecha: '2026-10-20',
      hora_inicio: '08:00',
      duracion: 120,
      ambientes: [5],
      grupos: [11, 12],
    }
    mockApiOnce({ status: 201, body: { data: exam } })

    await expect(examsService.createExam(payload)).resolves.toEqual(exam)

    const [url, options] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(String(url)).toContain('/examenes')
    expect(options).toMatchObject({ method: 'POST' })
    expect(JSON.parse(String(options.body))).toMatchObject({
      id_carrera: 2,
      id_materia: 3,
      grupos: [11, 12],
    })
  })

  it('reemplaza los grupos de un examen existente', async () => {
    mockApiOnce({ body: { data: exam } })

    await expect(examsService.assignGroups(7, { grupos: [12] })).resolves.toEqual(exam)

    const [url, options] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(String(url)).toContain('/examenes/7/grupos')
    expect(options).toMatchObject({ method: 'POST' })
    expect(JSON.parse(String(options.body))).toEqual({ grupos: [12] })
  })

  it('lista los examenes del docente actual', async () => {
    mockApiOnce({ body: { data: [exam] } })

    await expect(examsService.listExams()).resolves.toEqual([exam])

    const [url, options] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(String(url)).toContain('/examenes')
    expect(options).toMatchObject({ method: 'GET' })
  })

  it('obtiene el detalle de un examen', async () => {
    mockApiOnce({ body: { data: exam } })

    await expect(examsService.getExam(7)).resolves.toEqual(exam)

    const [url] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(String(url)).toContain('/examenes/7')
  })

  it('actualiza la informacion general de un examen', async () => {
    mockApiOnce({ body: { data: exam } })

    await expect(
      examsService.updateExam(7, {
        nombre_examen: 'Primer parcial',
        id_carrera: 2,
        id_materia: 3,
        categoria: 'REGULAR',
        fecha: '2026-10-20',
        hora_inicio: '08:00',
        duracion: 120,
        ambientes: [5],
      })
    ).resolves.toEqual(exam)

    const [url, options] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(String(url)).toContain('/examenes/7')
    expect(options).toMatchObject({ method: 'PUT' })
  })

  it('cancela un examen', async () => {
    mockApiOnce({ body: { data: { ...exam, estado: 'CANCELADO' } } })

    await expect(examsService.cancelExam(7)).resolves.toMatchObject({ estado: 'CANCELADO' })

    const [url, options] = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0]
    expect(String(url)).toContain('/examenes/7/cancelar')
    expect(options).toMatchObject({ method: 'POST' })
  })
})
