/**
 * Token de sesión en `localStorage`, para que la sesión sobreviva a una recarga.
 *
 * Contrapartida de seguridad: cualquier script que corra en la página puede leerlo, así que
 * una inyección de código (XSS) le robaría la sesión. La defensa es que la aplicación no
 * renderice nunca HTML sin sanitizar (hoy no hay `dangerouslySetInnerHTML` ni `innerHTML`).
 *
 * Sin almacenamiento disponible (modo privado, política del navegador) la sesión simplemente
 * no sobrevive a la recarga.
 */
const STORAGE_KEY = 'sciem.auth.token'

export function readStoredToken(): string | null {
  try {
    return window.localStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

export function storeToken(token: string): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, token)
  } catch {
    // Sin almacenamiento la sesión vive solo en memoria.
  }
}

export function clearStoredToken(): void {
  try {
    window.localStorage.removeItem(STORAGE_KEY)
  } catch {
    // Nada que limpiar.
  }
}
