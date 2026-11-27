import { describe, expect, it } from 'vitest'
import { color, CONTRAST_PAIRS, contrast, luminance, THEMES } from '@/test/designTokens'

/**
 * Contrastes WCAG 2.x AA de los pares de tokens que la interfaz combina (RNF-19), en el tema claro y en el
 * oscuro: 4.5:1 para texto y 3:1 para componentes de interfaz y foco. Si un cambio de paleta rompe un
 * par, esta prueba falla antes de que llegue a una pantalla.
 */

/** Tono (grados) y saturación HSL de un color hex. */
function hsl(hex: string): { hue: number; saturation: number } {
  const [r, g, b] = [1, 3, 5].map((start) => parseInt(hex.slice(start, start + 2), 16) / 255)
  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  const delta = max - min
  const lightness = (max + min) / 2

  if (delta === 0) return { hue: 0, saturation: 0 }

  const saturation = lightness > 0.5 ? delta / (2 - max - min) : delta / (max + min)
  const sector = max === r ? (g - b) / delta + (g < b ? 6 : 0) : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4

  return { hue: sector * 60, saturation }
}

const token = (theme: (typeof THEMES)[number], name: string) => color(theme, `--sciem-${name}`)

describe.each(THEMES)('contrastes del tema %s', (theme) => {
  it.each(CONTRAST_PAIRS)('%s sobre %s alcanza %s:1', (foreground, background, minimum) => {
    expect(contrast(token(theme, foreground), token(theme, background))).toBeGreaterThanOrEqual(minimum)
  })

  it('la elevación se lee por luminosidad: bg-app < surface ≤ raised', () => {
    const [app, surface, raised] = ['bg-app', 'surface', 'raised'].map((name) => luminance(token(theme, name)))

    expect(app).toBeLessThan(surface)
    expect(surface).toBeLessThanOrEqual(raised)
  })
})

describe('tema oscuro', () => {
  it('el sidebar es la superficie más oscura', () => {
    expect(luminance(token('dark', 'sb-bg'))).toBeLessThan(luminance(token('dark', 'bg-app')))
  })

  it('el sidebar pertenece a la misma familia neutra que bg-app, sin tinte cian', () => {
    const [sidebar, app] = [token('dark', 'sb-bg'), token('dark', 'bg-app')].map(hsl)

    expect(Math.abs(sidebar.hue - app.hue)).toBeLessThanOrEqual(10)
    expect(sidebar.saturation).toBeLessThanOrEqual(app.saturation + 0.05)
  })

  it('el hover sutil (muted-bg) es más claro que la superficie y que las capas flotantes', () => {
    const hover = luminance(token('dark', 'muted-bg'))

    expect(hover).toBeGreaterThan(luminance(token('dark', 'surface')))
    expect(hover).toBeGreaterThan(luminance(token('dark', 'raised')))
  })
})
