/**
 * Reconocimiento del código SIS en el formulario de acceso.
 *
 * Conviven tres formatos (docs/design/rnf-02/17-login.html, L.2): 5 dígitos (docentes), 9 dígitos
 * (auxiliares y estudiantes) y alfanumérico (administrativos, como ADM0001). El campo no normaliza lo
 * que se escribe, salvo recortar los espacios de los extremos: el backend hace el resto.
 *
 * Esta función solo dice si el código «parece» uno de los tres. NUNCA se usa para deducir el rol:
 * el rol y la navegación salen siempre de GET /api/auth/yo.
 */
const RECOGNISED_SIS = /^(\d{5}|\d{9}|[A-Za-z]{2,}\d{2,})$/

export const SIS_FORMAT_ERROR =
  'El código no tiene un formato válido. Use 5 o 9 dígitos, o un código como ADM0001.'

/** Lo que se envía al servidor: el texto tal cual, sin espacios en los extremos. */
export function cleanSis(value: string): string {
  return value.trim()
}

export function isRecognisedSis(value: string): boolean {
  return RECOGNISED_SIS.test(cleanSis(value))
}
