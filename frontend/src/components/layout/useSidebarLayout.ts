import { useCallback, useEffect, useState } from 'react'

const STORAGE_PREFIX = 'sciem.sidebar-layout.v1.'

/** Ancho inicial del menú en escritorio (17 rem). */
export const SIDEBAR_DEFAULT_WIDTH = 272
export const SIDEBAR_MIN_WIDTH = 224
export const SIDEBAR_MAX_WIDTH = 400
/** Cuánto cambia el ancho cada vez que se pulsa una flecha sobre el separador. */
export const SIDEBAR_WIDTH_STEP = 16

interface StoredLayout {
  collapsed: boolean
  width: number
}

/** Lo mostrado junto con el usuario al que pertenece. */
interface OwnedLayout {
  userId: string | undefined
  layout: StoredLayout
}

const DEFAULT_LAYOUT: StoredLayout = { collapsed: false, width: SIDEBAR_DEFAULT_WIDTH }

function storageKey(userId: string | undefined): string {
  return `${STORAGE_PREFIX}${userId ?? 'anonimo'}`
}

/** Deja el ancho dentro de los límites y en píxeles enteros. */
export function clampSidebarWidth(width: number): number {
  return Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, Math.round(width)))
}

function isValidWidth(value: unknown): value is number {
  return (
    typeof value === 'number' &&
    Number.isFinite(value) &&
    value >= SIDEBAR_MIN_WIDTH &&
    value <= SIDEBAR_MAX_WIDTH
  )
}

/** Lo guardado por el usuario; cada campo inválido o ausente cae a su valor por defecto. */
function readStored(userId: string | undefined): StoredLayout {
  try {
    const raw = window.localStorage.getItem(storageKey(userId))
    if (raw === null) return DEFAULT_LAYOUT

    const parsed: unknown = JSON.parse(raw)
    if (typeof parsed !== 'object' || parsed === null) return DEFAULT_LAYOUT

    const candidate = parsed as Partial<Record<keyof StoredLayout, unknown>>

    return {
      collapsed: candidate.collapsed === true,
      width: isValidWidth(candidate.width) ? candidate.width : SIDEBAR_DEFAULT_WIDTH
    }
  } catch {
    // Almacenamiento bloqueado o contenido corrupto: el menú funciona igual, sin recordar.
    return DEFAULT_LAYOUT
  }
}

function writeStored(userId: string | undefined, layout: StoredLayout): void {
  try {
    window.localStorage.setItem(storageKey(userId), JSON.stringify(layout))
  } catch {
    // Modo privado o cuota llena: se pierde solo el recuerdo, no el menú.
  }
}

/** El atajo no debe robarle la tecla a quien está escribiendo. */
function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false

  if (target.matches('input, textarea, select')) return true

  return target.closest('[contenteditable=""], [contenteditable="true"]') !== null
}

export interface SidebarLayoutState {
  collapsed: boolean
  width: number
  toggle: () => void
  setWidth: (width: number) => void
  resetWidth: () => void
}

/**
 * Cómo se ve el menú lateral en escritorio: oculto o visible y de qué ancho. Se recuerda por usuario
 * en `localStorage` y se alterna también con Ctrl+B (Cmd+B en Mac), salvo mientras se escribe.
 */
export function useSidebarLayout(userId: string | undefined): SidebarLayoutState {
  // El estado recuerda de qué usuario es: si `userId` cambia sin desmontar el menú, se vuelve a leer
  // el de ese usuario antes de cualquier escritura, para no pisar su clave con el del anterior.
  const [state, setState] = useState<OwnedLayout>(() => ({
    userId,
    layout: readStored(userId)
  }))

  let current = state

  if (state.userId !== userId) {
    current = { userId, layout: readStored(userId) }
    setState(current)
  }

  useEffect(() => {
    writeStored(state.userId, state.layout)
  }, [state])

  const toggle = useCallback(() => {
    setState((owned) => ({
      ...owned,
      layout: { ...owned.layout, collapsed: !owned.layout.collapsed }
    }))
  }, [])

  const setWidth = useCallback((width: number) => {
    setState((owned) => ({
      ...owned,
      layout: { ...owned.layout, width: clampSidebarWidth(width) }
    }))
  }, [])

  const resetWidth = useCallback(() => setWidth(SIDEBAR_DEFAULT_WIDTH), [setWidth])

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) return
      if (event.key.toLowerCase() !== 'b') return
      if (isTypingTarget(event.target)) return

      // Firefox usa Ctrl+B para abrir sus marcadores.
      event.preventDefault()
      toggle()
    }

    window.addEventListener('keydown', handleKeyDown)

    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [toggle])

  return {
    collapsed: current.layout.collapsed,
    width: current.layout.width,
    toggle,
    setWidth,
    resetWidth
  }
}
