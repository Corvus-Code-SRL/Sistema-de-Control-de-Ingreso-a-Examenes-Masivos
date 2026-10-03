import { useEffect, useState } from 'react'

/** Lo que el teclado virtual le quita a la ventana para considerarlo abierto. */
const KEYBOARD_MIN_HEIGHT_PX = 150

/**
 * ¿Está abierto el teclado virtual? Sin `visualViewport` (escritorio, navegadores viejos) nunca.
 *
 * El diseño (L.9) encoge el título y oculta las ayudas con el teclado abierto: ya leyó el formato y
 * la pantalla se queda sin altura.
 */
export function useVirtualKeyboard(): boolean {
  const [isOpen, setIsOpen] = useState(false)

  useEffect(() => {
    const viewport = window.visualViewport

    if (!viewport) return

    const update = () => setIsOpen(window.innerHeight - viewport.height > KEYBOARD_MIN_HEIGHT_PX)

    update()
    viewport.addEventListener('resize', update)

    return () => viewport.removeEventListener('resize', update)
  }, [])

  return isOpen
}
