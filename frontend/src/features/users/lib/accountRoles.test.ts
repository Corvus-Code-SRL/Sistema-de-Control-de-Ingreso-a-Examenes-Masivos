import { describe, expect, it } from 'vitest'
import { guestTeacher, makeAccount, roles, teacher } from '@/test/userFixtures'
import { isGuestAccount, rolesAllowedFor } from './accountRoles'

const names = (list: { nombre_rol: string }[]) => list.map((role) => role.nombre_rol)

describe('rolesAllowedFor', () => {
  it('una cuenta de docente invitado solo admite el rol Invitado', () => {
    expect(names(rolesAllowedFor(roles, guestTeacher))).toEqual(['Invitado'])
  })

  it('una cuenta con código SIS nunca admite el rol Invitado', () => {
    expect(names(rolesAllowedFor(roles, teacher))).toEqual(['Administrador', 'Docente', 'Auxiliar'])
    expect(names(rolesAllowedFor(roles, makeAccount()))).not.toContain('Invitado')
  })
})

describe('isGuestAccount', () => {
  it('prefiere lo que informa el servidor y, sin eso, el prefijo INV', () => {
    expect(isGuestAccount({ cod_sis: '10452', es_invitado: true })).toBe(true)
    expect(isGuestAccount({ cod_sis: 'INV4821', es_invitado: false })).toBe(false)
    expect(isGuestAccount({ cod_sis: 'INV4821' })).toBe(true)
    expect(isGuestAccount({ cod_sis: '10452' })).toBe(false)
  })
})
