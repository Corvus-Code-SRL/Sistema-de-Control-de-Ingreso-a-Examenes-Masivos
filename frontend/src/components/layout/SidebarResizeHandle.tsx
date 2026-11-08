import { useEffect, useRef, type KeyboardEvent, type PointerEvent, type RefObject } from 'react'
import {
  SIDEBAR_MAX_WIDTH,
  SIDEBAR_MIN_WIDTH,
  SIDEBAR_WIDTH_STEP,
  clampSidebarWidth
} from './useSidebarLayout'

interface SidebarResizeHandleProps {
  /** Ancho confirmado del menú, en píxeles. */
  width: number
  /** Contenedor cuyo `--sidebar-width` se mueve mientras se arrastra, sin pasar por React. */
  targetRef: RefObject<HTMLElement>
  /** Se llama una sola vez al soltar (o en cada paso de teclado) con el ancho final. */
  onResize: (width: number) => void
  onReset: () => void
}

interface DragState {
  startX: number
  startWidth: number
  current: number
}

/**
 * Asa en el borde derecho del menú de escritorio para cambiar su ancho.
 *
 * Mientras se arrastra solo se modifica la variable CSS del contenedor: la aplicación no se vuelve a
 * renderizar en cada movimiento del puntero, y el ancho se confirma al soltar. Con teclado, las
 * flechas lo cambian de 16 en 16 px; con doble clic vuelve al ancho inicial.
 */
export function SidebarResizeHandle({
  width,
  targetRef,
  onResize,
  onReset
}: SidebarResizeHandleProps) {
  const drag = useRef<DragState | null>(null)
  const handleRef = useRef<HTMLDivElement>(null)

  function restoreDocument() {
    document.body.style.removeProperty('user-select')
    document.body.style.removeProperty('cursor')
    delete targetRef.current?.dataset.resizing
  }

  // Si el menú se desmonta a mitad del arrastre no debe quedar el texto sin poder seleccionarse.
  useEffect(() => restoreDocument, [])

  function preview(next: number) {
    targetRef.current?.style.setProperty('--sidebar-width', `${next}px`)
    handleRef.current?.setAttribute('aria-valuenow', String(next))
  }

  function handlePointerDown(event: PointerEvent<HTMLDivElement>) {
    if (event.button !== 0) return

    event.preventDefault()
    event.currentTarget.setPointerCapture(event.pointerId)
    drag.current = { startX: event.clientX, startWidth: width, current: width }

    // Sin transición mientras se arrastra: el ancho debe seguir al puntero al instante.
    if (targetRef.current) targetRef.current.dataset.resizing = 'true'
    document.body.style.setProperty('user-select', 'none')
    document.body.style.setProperty('cursor', 'col-resize')
  }

  function handlePointerMove(event: PointerEvent<HTMLDivElement>) {
    if (drag.current === null) return

    const next = clampSidebarWidth(drag.current.startWidth + event.clientX - drag.current.startX)

    drag.current.current = next
    preview(next)
  }

  function finishDrag(event: PointerEvent<HTMLDivElement>, commit: boolean) {
    const state = drag.current
    if (state === null) return

    drag.current = null

    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId)
    }

    restoreDocument()

    if (commit) {
      onResize(state.current)
    } else {
      preview(width)
    }
  }

  function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key === 'ArrowLeft') {
      event.preventDefault()
      onResize(width - SIDEBAR_WIDTH_STEP)
    } else if (event.key === 'ArrowRight') {
      event.preventDefault()
      onResize(width + SIDEBAR_WIDTH_STEP)
    }
  }

  return (
    <div
      ref={handleRef}
      role="separator"
      aria-orientation="vertical"
      aria-label="Cambiar ancho del menú"
      aria-valuemin={SIDEBAR_MIN_WIDTH}
      aria-valuemax={SIDEBAR_MAX_WIDTH}
      aria-valuenow={width}
      tabIndex={0}
      onPointerDown={handlePointerDown}
      onPointerMove={handlePointerMove}
      onPointerUp={(event) => finishDrag(event, true)}
      onPointerCancel={(event) => finishDrag(event, false)}
      onDoubleClick={onReset}
      onKeyDown={handleKeyDown}
      className="absolute inset-y-0 right-0 z-30 w-1.5 cursor-col-resize touch-none bg-transparent outline-none transition-colors hover:bg-sidebar-ring/50 focus-visible:bg-sidebar-ring motion-reduce:transition-none"
    />
  )
}
