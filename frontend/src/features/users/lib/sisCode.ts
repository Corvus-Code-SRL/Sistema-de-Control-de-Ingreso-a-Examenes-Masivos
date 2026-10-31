import { GUEST_ROLE } from '../types/users.types'

/**
 * Formato del código SIS por rol: espejo de `App\Support\SisCodeFormat` del backend.
 *
 * El servidor es quien decide; esta copia solo adelanta el aviso al administrador. Si cambia una
 * regla, cámbiela en los dos lugares.
 */

const MAX_LENGTH = 15
const FIRST_SIS_YEAR = 1990

export const GUEST_PREFIX = 'INV'

/** Mayúsculas y sin espacios en los extremos ni repetidos, como lo guarda el servidor. */
export function normalizeSisCode(value: string): string {
  return value.trim().replace(/\s+/g, ' ').toUpperCase()
}

export function isGuestCode(code: string): boolean {
  return code.startsWith(GUEST_PREFIX)
}

function isAssistantCode(code: string, currentYear: number): boolean {
  if (/^9\d{7}$/.test(code)) return true

  const match = /^(\d{4})\d{5}$/.exec(code)

  if (!match) return false

  const year = Number(match[1])

  return year >= FIRST_SIS_YEAR && year <= currentYear
}

function isAdministratorCode(code: string): boolean {
  return /^[A-Z]+\d+$/.test(code)
}

function isTeacherCode(code: string): boolean {
  return /^[A-Z0-9]{3,15}$/.test(code)
}

const ROLE_MISMATCH: Record<string, string> = {
  Administrador: 'Este código no corresponde a una cuenta de administrador.',
  Docente: 'Este código no corresponde a una cuenta de docente.',
  Auxiliar: 'Este código no corresponde a una cuenta de auxiliar.',
}

/**
 * Aviso corto cuando un código, válido en general, no encaja con el rol: el paso «Rol» deshabilita esa
 * opción. `null` si el código encaja o el rol no se evalúa por código (el docente invitado no lo lleva).
 */
export function roleCodeMismatch(
  rawCode: string,
  roleName: string,
  currentYear: number = new Date().getFullYear()
): string | null {
  const message = ROLE_MISMATCH[roleName]

  if (message === undefined) return null

  return sisCodeError(rawCode, roleName, currentYear) === null ? null : message
}

/** Mensaje del formato que corresponde a cada rol, o `null` si el código lo cumple. */
export function sisCodeError(
  rawCode: string,
  roleName: string | null,
  currentYear: number = new Date().getFullYear()
): string | null {
  const code = normalizeSisCode(rawCode)

  if (code === '') return 'Ingrese el código SIS.'
  if (code.length > MAX_LENGTH) return `El código SIS no puede tener más de ${MAX_LENGTH} caracteres.`
  if (isGuestCode(code) || roleName === GUEST_ROLE) {
    return 'Los códigos que empiezan con INV los genera el sistema. Use la opción de docente invitado.'
  }

  switch (roleName) {
    case 'Auxiliar':
      return isAssistantCode(code, currentYear)
        ? null
        : 'El código de un auxiliar tiene 9 dígitos y empieza con el año de ingreso (por ejemplo, 202312345), o 8 dígitos y empieza con 9.'
    case 'Administrador':
      return isAdministratorCode(code)
        ? null
        : 'El código de un administrador son letras seguidas de dígitos (por ejemplo, ADM0001).'
    case 'Docente':
      return isTeacherCode(code)
        ? null
        : 'El código de un docente tiene de 3 a 15 letras o dígitos, sin espacios ni guiones.'
    default:
      return isAssistantCode(code, currentYear) || isAdministratorCode(code) || isTeacherCode(code)
        ? null
        : 'El código no tiene un formato válido. Use de 3 a 15 letras o dígitos, sin espacios ni guiones.'
  }
}
