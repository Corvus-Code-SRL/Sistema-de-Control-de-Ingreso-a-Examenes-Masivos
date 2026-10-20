/** Aviso que una vista deja a la siguiente al navegar, como «Grupo actualizado» tras editar. */
export interface FlashMessage {
  title: string
  description: string
}

/** Estado de navegación que transporta el aviso (`navigate(to, { state })`). */
export interface FlashState {
  flash: FlashMessage
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

/** Lee el aviso del estado de navegación; null si no hay o no tiene la forma esperada. */
export function flashMessageFrom(state: unknown): FlashMessage | null {
  if (!isRecord(state) || !isRecord(state.flash)) return null

  const { title, description } = state.flash

  return typeof title === 'string' && typeof description === 'string' ? { title, description } : null
}
