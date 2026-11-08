import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

/*
 * jsdom no implementa estas APIs de layout: Radix (Select, Popover) y cmdk (SearchableSelect) las
 * invocan al abrirse, así que sin ellas cualquier selector desplegable falla en las pruebas.
 */
if (typeof globalThis.ResizeObserver === 'undefined') {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}

if (typeof Element !== 'undefined') {
  Element.prototype.scrollIntoView ??= () => undefined
  Element.prototype.hasPointerCapture ??= () => false
  Element.prototype.setPointerCapture ??= () => undefined
  Element.prototype.releasePointerCapture ??= () => undefined
}

// jsdom no trae PointerEvent: sin él los eventos de puntero pierden `button`, `clientX` y `pointerId`.
if (typeof window !== 'undefined' && typeof window.PointerEvent === 'undefined') {
  class PointerEventPolyfill extends MouseEvent {
    readonly pointerId: number

    constructor(type: string, init: PointerEventInit = {}) {
      super(type, init)
      this.pointerId = init.pointerId ?? 0
    }
  }

  window.PointerEvent = PointerEventPolyfill as unknown as typeof PointerEvent
}

// jsdom solo avisa «Not implemented»: el marco vuelve arriba al navegar y las pruebas lo observan con un espía.
if (typeof window !== 'undefined') {
  window.scrollTo = () => undefined
}

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})
