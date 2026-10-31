import { describe, expect, it } from 'vitest'
import { roleLabel } from './roleLabel'

describe('roleLabel', () => {
  it('presenta el rol interno Invitado como «Docente invitado»', () => {
    expect(roleLabel('Invitado')).toBe('Docente invitado')
  })

  it.each(['Administrador', 'Docente', 'Auxiliar'])('deja %s como viene', (name) => {
    expect(roleLabel(name)).toBe(name)
  })
})
