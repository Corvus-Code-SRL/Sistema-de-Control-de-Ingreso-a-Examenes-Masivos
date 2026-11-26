import { describe, expect, it } from 'vitest'
import { color, contrast, luminance, THEMES } from '@/test/designTokens'

/**
 * Contrastes WCAG 2.x AA de los pares de tokens que la interfaz combina (RNF-19), en el tema claro y en el
 * oscuro: 4.5:1 para texto y 3:1 para componentes de interfaz y foco. Si un cambio de paleta rompe un
 * par, esta prueba falla antes de que llegue a una pantalla.
 */

const TEXT = 4.5
const UI = 3

/** [texto o elemento, fondo, mínimo] — nombres sin el prefijo `--sciem-`. */
const PAIRS: [string, string, number][] = [
  ['text', 'bg-app', TEXT],
  ['text', 'surface', TEXT],
  ['text', 'raised', TEXT],
  ['muted', 'bg-app', TEXT],
  ['muted', 'surface', TEXT],
  ['muted', 'sunken', TEXT],
  ['muted', 'raised', TEXT],
  ['muted', 'muted-bg', TEXT],
  ['muted', 'brand-soft', TEXT],
  ['subtle', 'bg-app', TEXT],
  ['subtle', 'surface', TEXT],
  ['brand-fg', 'bg-app', TEXT],
  ['brand-fg', 'surface', TEXT],
  ['brand-fg', 'raised', TEXT],
  ['brand-fg', 'brand-soft', TEXT],
  ['brand-deep', 'brand-soft', TEXT],
  ['brand-deep', 'surface', TEXT],
  ['ok-fg', 'ok-soft', TEXT],
  ['warn-fg', 'warn-soft', TEXT],
  ['danger-fg', 'danger-soft', TEXT],
  ['info', 'info-soft', TEXT],
  ['neutral-fg', 'neutral-soft', TEXT],
  ['danger', 'surface', TEXT],
  ['danger-fg', 'surface', TEXT],
  ['ok-fg', 'surface', TEXT],
  ['warn-fg', 'surface', TEXT],
  ['on-solid', 'brand', TEXT],
  ['on-solid', 'brand-hover', TEXT],
  ['on-solid', 'brand-active', TEXT],
  ['on-solid', 'brand-strong', TEXT],
  ['on-solid', 'danger-solid', TEXT],
  ['on-solid', 'ok-solid', TEXT],
  ['on-solid', 'ok-solid-hover', TEXT],
  ['on-solid', 'neutral-solid', TEXT],
  ['on-accent', 'accent', TEXT],
  ['on-accent', 'accent-hover', TEXT],
  ['sb-text', 'sb-bg', TEXT],
  ['sb-text', 'sb-hover', TEXT],
  ['sb-text', 'sb-active', TEXT],
  ['sb-sub', 'sb-bg', TEXT],
  ['sb-group', 'sb-bg', TEXT],
  ['on-solid', 'sb-active', TEXT],
  ['sb-focus', 'sb-bg', UI],
  ['text', 'field-bg', TEXT],
  ['muted', 'field-bg', TEXT],
  ['input-border', 'field-bg', UI],
  ['focus', 'bg-app', UI],
  ['focus', 'surface', UI],
  ['focus', 'raised', UI],
  ['brand-fg', 'surface', UI],
  ['input-border', 'bg-app', UI],
  ['input-border', 'surface', UI],
  ['input-border', 'raised', UI],
  ['ok', 'surface', UI],
  ['brand', 'surface', UI],
]

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
  it.each(PAIRS)('%s sobre %s alcanza %s:1', (foreground, background, minimum) => {
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
