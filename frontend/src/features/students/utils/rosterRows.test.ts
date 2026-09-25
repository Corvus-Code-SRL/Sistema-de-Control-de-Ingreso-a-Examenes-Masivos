import { describe, expect, it } from 'vitest'
import {
  countRosterRows,
  duplicateRowsBySis,
  formatRowList,
  isIncorporable,
  rowIssues,
  rowStateText,
} from './rosterRows'
import type { RosterPreviewRow } from '../types/roster.types'
import { makeRosterRow, makeUnsupportedRosterRow, rosterPreviewResponse } from '@/test/fixtures'

/** Observaciones de una fila resolviendo por su cuenta el mapa de duplicados. */
function issuesOf(row: RosterPreviewRow, rows: RosterPreviewRow[] = [row]) {
  const duplicates = duplicateRowsBySis(rows)

  return rowIssues(row, duplicates.get(row.codigo_sis ?? '') ?? [])
}

describe('rowIssues', () => {
  it('clasifica los datos faltantes como incompletos', () => {
    const row = makeRosterRow({
      estado: 'inconsistent',
      apellidos: null,
      nombres: null,
      codigo_sis: null,
      errores: ['missing_sis_code', 'missing_last_names', 'missing_first_names'],
    })

    expect(issuesOf(row)).toEqual([
      { category: 'Incompleto', message: 'Falta el código SIS' },
      { category: 'Incompleto', message: 'Faltan los apellidos' },
      { category: 'Incompleto', message: 'Faltan los nombres' },
    ])
  })

  it('clasifica los excesos de longitud como formato y cita el límite real', () => {
    const row = makeRosterRow({
      estado: 'inconsistent',
      errores: ['last_names_too_long', 'first_names_too_long'],
    })

    expect(issuesOf(row)).toEqual([
      { category: 'Formato', message: 'Los apellidos superan los 30 caracteres' },
      { category: 'Formato', message: 'Los nombres superan los 50 caracteres' },
    ])
  })

  it('explica las reglas del código SIS de estudiante: solo dígitos, de 8 a 12', () => {
    const row = makeRosterRow({
      estado: 'inconsistent',
      errores: ['sis_code_not_numeric', 'sis_code_invalid_length'],
    })

    expect(issuesOf(row)).toEqual([
      { category: 'Formato', message: 'El código SIS debe tener solo dígitos' },
      { category: 'Formato', message: 'El código SIS debe tener entre 8 y 12 dígitos' },
    ])
  })

  it('una fila repetida idéntica dice cuál es la fila que sí se importa', () => {
    const rows = [
      makeRosterRow({ numero_fila: 3, codigo_sis: '20260001' }),
      makeRosterRow({ numero_fila: 5, codigo_sis: '20260001', estado: 'inconsistent', errores: ['duplicate_row_in_file'] }),
      makeRosterRow({ numero_fila: 7, codigo_sis: '20260001', estado: 'inconsistent', errores: ['duplicate_row_in_file'] }),
    ]

    expect(issuesOf(rows[1], rows)).toEqual([
      { category: 'Duplicado', message: 'Fila repetida: idéntica a la fila 3, que sí se importa' },
    ])
    expect(issuesOf(rows[2], rows)).toEqual(issuesOf(rows[1], rows))
  })

  it('un duplicado con datos distintos nombra todas las filas y explica por qué no se importa ninguna', () => {
    const rows = [3, 5, 7].map((numero_fila) =>
      makeRosterRow({
        numero_fila,
        codigo_sis: '20260001',
        estado: 'inconsistent',
        errores: ['conflicting_duplicate_in_file'],
      })
    )

    const [issue] = issuesOf(rows[0], rows)

    expect(issue.category).toBe('Duplicado')
    expect(issue.message).toBe(
      'Código SIS repetido con datos distintos en las filas 3, 5 y 7. ' +
        'No se importa ninguna porque no hay forma de saber cuál es la correcta'
    )
    // La misma frase en cada aparición: el backend marca todas.
    expect(issuesOf(rows[2], rows)).toEqual(issuesOf(rows[0], rows))
  })

  it('no pierde ocurrencias del duplicado al recibir solo parte de las filas', () => {
    const rows = [
      makeRosterRow({ numero_fila: 3, codigo_sis: '20260001', errores: ['conflicting_duplicate_in_file'] }),
      makeRosterRow({ numero_fila: 5, codigo_sis: '20260001', errores: ['conflicting_duplicate_in_file'] }),
    ]

    const duplicates = duplicateRowsBySis(rows)

    expect(rowIssues(rows[0], duplicates.get('20260001') ?? [])[0].message).toContain(
      'en las filas 3 y 5'
    )
  })

  it('describe un código de error desconocido sin romper la fila', () => {
    // El backend podría añadir un código antes de que el frontend lo conozca.
    const row = makeUnsupportedRosterRow({ estado: 'inconsistent', errores: ['codigo_futuro'] })

    expect(issuesOf(row)).toEqual([
      { category: 'Otro', message: 'Inconsistencia informada por el servidor (codigo_futuro)' },
    ])
  })
})

describe('formatRowList', () => {
  it('enumera las filas en castellano', () => {
    expect(formatRowList([3])).toBe('3')
    expect(formatRowList([3, 5])).toBe('3 y 5')
    expect(formatRowList([3, 5, 7])).toBe('3, 5 y 7')
  })
})

describe('rowStateText', () => {
  it('da una etiqueta propia a cada estado del backend', () => {
    expect(rowStateText('new_student').label).toBe('Nuevo en SCIEM')
    expect(rowStateText('existing_student').label).toBe('Se inscribirá')
    expect(rowStateText('already_enrolled').label).toBe('Ya en el grupo')
    expect(rowStateText('inconsistent').label).toBe('Inconsistente')
  })

  it('un estado desconocido se anuncia y nunca se incorpora', () => {
    const { estado } = makeUnsupportedRosterRow({ estado: 'estado_futuro' })

    expect(rowStateText(estado).label).toBe('Estado no reconocido')
    expect(isIncorporable(estado)).toBe(false)
  })

  it('solo el alta y la inscripción cuentan como incorporables', () => {
    expect(isIncorporable('new_student')).toBe(true)
    expect(isIncorporable('existing_student')).toBe(true)
    expect(isIncorporable('already_enrolled')).toBe(false)
    expect(isIncorporable('inconsistent')).toBe(false)
  })
})

describe('countRosterRows', () => {
  const preview = rosterPreviewResponse([
    makeRosterRow({ numero_fila: 2, estado: 'new_student' }),
    makeRosterRow({ numero_fila: 3, estado: 'new_student' }),
    makeRosterRow({ numero_fila: 4, estado: 'existing_student' }),
    makeRosterRow({ numero_fila: 5, estado: 'already_enrolled' }),
    makeRosterRow({ numero_fila: 6, estado: 'inconsistent', errores: ['missing_sis_code'] }),
  ]).data

  it('separa lo que se incorpora de lo que ya está en el grupo', () => {
    expect(countRosterRows(preview)).toEqual({
      leidas: 5,
      incorporables: 3,
      nuevos: 2,
      yaEnElGrupo: 1,
      yaInscritos: 1,
      inconsistentes: 1,
    })
  })

  it('no tiene ningún otro estado de estudiante dentro de la nómina', () => {
    expect(Object.keys(countRosterRows(preview)).sort()).toEqual([
      'inconsistentes',
      'incorporables',
      'leidas',
      'nuevos',
      'yaEnElGrupo',
      'yaInscritos',
    ])
  })

  it('no confunde las incorporables con las filas válidas del backend', () => {
    // `filas_validas` incluye a quienes ya están en el grupo: 4 frente a 3.
    expect(preview.filas_validas).toBe(4)
    expect(countRosterRows(preview).incorporables).toBe(3)
  })
})
