import type { NewUserAccount, Role } from '../types/users.types'
import { GUEST_ROLE } from '../types/users.types'
import { normalizeSisCode } from './sisCode'

export const MAX_NAME_LENGTH = 50
export const MAX_SURNAME_LENGTH = 60
export const MAX_EMAIL_LENGTH = 100
export const MIN_PASSWORD_LENGTH = 8
export const MAX_PASSWORD_LENGTH = 72

/** Lo que el administrador ha escrito en el asistente de registro; se conserva al volver de un paso. */
export interface RegistrationDraft {
  /** Docente invitado: no se escribe un código SIS, se usa el INV que entrega el servidor. */
  isGuest: boolean
  codSis: string
  /** Código INV libre que el servidor entregó para este registro; vacío mientras no se obtiene. */
  guestCode: string
  nombre: string
  apellidoPaterno: string
  apellidoMaterno: string
  correo: string
  contrasenia: string
}

export type DraftField =
  | 'cod_sis'
  | 'nombre'
  | 'apellido_paterno'
  | 'apellido_materno'
  | 'correo'
  | 'contrasenia_temporal'
  | 'id_rol'

export type DraftErrors = Partial<Record<DraftField, string>>

export const EMPTY_DRAFT: RegistrationDraft = {
  isGuest: false,
  codSis: '',
  guestCode: '',
  nombre: '',
  apellidoPaterno: '',
  apellidoMaterno: '',
  correo: '',
  contrasenia: '',
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/** Errores de los datos personales (paso 2); vacío cuando los obligatorios están completos y válidos. */
export function validatePersonal(draft: RegistrationDraft): DraftErrors {
  const errors: DraftErrors = {}

  if (draft.nombre.trim() === '') errors.nombre = 'Ingrese los nombres.'
  if (draft.apellidoPaterno.trim() === '') errors.apellido_paterno = 'Ingrese el apellido paterno.'

  if (draft.correo.trim() === '') {
    errors.correo = 'Ingrese el correo institucional.'
  } else if (!EMAIL_PATTERN.test(draft.correo.trim())) {
    errors.correo = 'Ingrese un correo válido, por ejemplo nombre@umss.edu.'
  }

  if (draft.contrasenia.length < MIN_PASSWORD_LENGTH) {
    errors.contrasenia_temporal = `La contraseña temporal debe tener al menos ${MIN_PASSWORD_LENGTH} caracteres.`
  }

  return errors
}

export function isPersonalValid(draft: RegistrationDraft): boolean {
  return Object.keys(validatePersonal(draft)).length === 0
}

/**
 * Cuerpo de `POST /usuarios`. El invitado envía su código INV y el rol Invitado; una cuenta con código
 * SIS envía el rol elegido, o `null` si «Continuar sin rol».
 */
export function toPayload(draft: RegistrationDraft, roles: readonly Role[], roleId: number | null): NewUserAccount {
  const guestRole = roles.find((role) => role.nombre_rol === GUEST_ROLE)

  return {
    ...(draft.isGuest
      ? { sin_codigo_sis: true, cod_invitado: draft.guestCode }
      : { cod_sis: normalizeSisCode(draft.codSis) }),
    id_rol: draft.isGuest ? (guestRole?.id_rol ?? null) : roleId,
    nombre: draft.nombre.trim(),
    apellido_paterno: draft.apellidoPaterno.trim(),
    apellido_materno: draft.apellidoMaterno.trim() === '' ? null : draft.apellidoMaterno.trim(),
    correo: draft.correo.trim(),
    contrasenia_temporal: draft.contrasenia,
  }
}
