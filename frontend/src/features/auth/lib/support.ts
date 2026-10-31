/**
 * Salida de soporte de la pantalla de acceso.
 *
 * El diseño solo define una dirección de contacto. El Administrador de cada facultad no tiene una
 * propia en ningún documento, así que los avisos «escribir al Administrador» abren un borrador hacia
 * soporte, con el asunto y el código SIS prellenados.
 */
export const SUPPORT_EMAIL = 'soporte.sciem@umss.edu'

function mailto(subject: string, body?: string): string {
  const params = [`subject=${encodeURIComponent(subject)}`]

  if (body) {
    params.push(`body=${encodeURIComponent(body)}`)
  }

  return `mailto:${SUPPORT_EMAIL}?${params.join('&')}`
}

/** Cuenta inactiva: pedir al Administrador que la reactive. */
export function inactiveAccountMailto(sis: string): string {
  return mailto(
    `Cuenta inactiva en SCIEM · SIS ${sis}`,
    `Mi cuenta ${sis} figura como inactiva. Solicito que se revise su reactivación.`
  )
}

/** Cuenta sin rol: pedir la asignación, con el SIS y la fecha de la solicitud. */
export function roleRequestMailto(sis: string, date: Date): string {
  const day = date.toLocaleDateString('es-BO')

  return mailto(
    `Solicitud de rol en SCIEM · SIS ${sis}`,
    `Solicito la asignación de un rol para la cuenta ${sis}.\nFecha de la solicitud: ${day}`
  )
}

/**
 * Contraseña olvidada y «¿Problemas para ingresar?» comparten la misma salida: el flujo de recuperación
 * no existe todavía (RNF-11), así que no hay una ruta propia a la que llevar.
 */
export function supportMailto(sis?: string): string {
  return mailto(sis ? `Ayuda para ingresar a SCIEM · SIS ${sis}` : 'Ayuda para ingresar a SCIEM')
}
