/**
 * Reconocimiento del código de acceso en el formulario de acceso.
 *
 * Conviven varios formatos (docs/design/rnf-02/17-login.html, L.2): 9 dígitos (auxiliares), letras y
 * dígitos como ADM0001 (administrativos), códigos alfanuméricos de docentes como 10452 y los códigos
 * INV4821 de los docentes invitados. El de los docentes es el más amplio (3 a 15 letras o dígitos) e
 * incluye a todos los demás. El campo no normaliza lo que se escribe, salvo recortar los espacios de
 * los extremos: el backend hace el resto.
 *
 * Esta función solo dice si el código «parece» uno de ellos. NUNCA se usa para deducir el rol: el rol
 * y la navegación salen siempre de GET /api/auth/yo.
 */
const RECOGNISED_SIS = /^[A-Za-z0-9]{3,15}$/

export const SIS_FORMAT_ERROR =
  'El código no tiene un formato válido. Use de 3 a 15 letras o dígitos, sin espacios ni guiones.'

/** Lo que se envía al servidor: el texto tal cual, sin espacios en los extremos. */
export function cleanSis(value: string): string {
  return value.trim()
}

export function isRecognisedSis(value: string): boolean {
  return RECOGNISED_SIS.test(cleanSis(value))
}
