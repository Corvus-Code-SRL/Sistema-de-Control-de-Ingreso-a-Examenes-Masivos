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

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})
