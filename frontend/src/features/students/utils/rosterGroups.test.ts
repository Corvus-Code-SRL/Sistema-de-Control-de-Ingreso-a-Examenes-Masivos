import { describe, expect, it } from 'vitest'
import { courseOptionLabel, uploadableCourses } from './rosterGroups'
import { makeForeignGroup, makeGroup, makeSubject } from '@/test/fixtures'

describe('rosterGroups', () => {
  const subject = makeSubject()

  it('solo ofrece los grupos activos del docente', () => {
    const own = { group: makeGroup(), subject }
    const inactive = { group: makeGroup({ id_grupo: 101, activo: false }), subject }
    const foreign = { group: makeForeignGroup(), subject }

    expect(uploadableCourses([own, inactive, foreign])).toEqual([own])
  })

  it('nombra el grupo con la materia, el número y la carrera', () => {
    const course = { group: makeGroup({ num_grupo: '4' }), subject }

    expect(courseOptionLabel(course)).toBe(
      `${subject.nombre} · Grupo 4 · ${subject.carrera.nombre}`
    )
  })
})
