import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * Lectura de los tokens de diseño de `src/index.css` para las pruebas: separa la paleta cruda del tema
 * claro y del oscuro, resuelve las referencias `var(--x)` y mide el contraste WCAG 2.x entre dos colores.
 */

export type Theme = 'light' | 'dark'

export const THEMES: Theme[] = ['light', 'dark']

export const frontendPath = (...parts: string[]): string => resolve(process.cwd(), ...parts)

export const indexCss = readFileSync(frontendPath('src/index.css'), 'utf8')

const LIGHT_PALETTE = '/* ---------- 1. Paleta cruda — tema claro'
const DARK_PALETTE = '/* ---------- 1b. Paleta cruda — tema oscuro'
const SEMANTIC_LAYER = '/* ---------- 2. Mapeo'
const TAILWIND_LAYER = '/* ---------- 3. Exposición a Tailwind'
const TYPOGRAPHY = '/* ---------- 4. Tipografía'

function section(start: string, end: string): string {
  const from = indexCss.indexOf(start)
  const to = indexCss.indexOf(end)

  if (from < 0 || to < from) throw new Error(`No se encontró la sección «${start}» de index.css`)

  return indexCss.slice(from, to)
}

/** Capa 1 completa (ambos temas): el único lugar donde se permiten literales de color. */
export const paletteCss = section(LIGHT_PALETTE, SEMANTIC_LAYER)

function declarations(css: string): Map<string, string> {
  const found = new Map<string, string>()

  for (const [, name, value] of css.matchAll(/(--[a-z0-9-]+):\s*([^;]+);/g)) found.set(name, value.trim())

  return found
}

const lightPalette = declarations(section(LIGHT_PALETTE, DARK_PALETTE))
const darkOverrides = declarations(section(DARK_PALETTE, SEMANTIC_LAYER))

const palettes: Record<Theme, Map<string, string>> = {
  light: lightPalette,
  dark: new Map([...lightPalette, ...darkOverrides]),
}

/** Declaraciones tal como están escritas en cada capa de `index.css` (sin resolver alias). */
export const layers = {
  /** Capa 1, tema claro. */
  light: lightPalette,
  /** Capa 1, solo lo que el tema oscuro redefine. */
  dark: darkOverrides,
  /** Capa 2: variables semánticas de shadcn. */
  semantic: declarations(section(SEMANTIC_LAYER, TAILWIND_LAYER)),
  /** Capa 3: `@theme inline` de Tailwind. */
  tailwind: declarations(section(TAILWIND_LAYER, TYPOGRAPHY)),
}

/** Nombres de todas las propiedades personalizadas que declara `index.css`, en cualquier capa. */
export const declaredProperties = new Set(declarations(indexCss).keys())

/** Color hex de un token `--sciem-*` en un tema, siguiendo los alias `var(--x)`. */
export function color(theme: Theme, name: string): string {
  const value = palettes[theme].get(name)

  if (value === undefined) throw new Error(`El token ${name} no existe en el tema ${theme}`)

  const alias = value.match(/^var\((--[a-z0-9-]+)\)$/)

  if (alias) return color(theme, alias[1])
  if (!/^#[0-9A-Fa-f]{6}$/.test(value)) throw new Error(`El token ${name} (${theme}) no es un color hex: ${value}`)

  return value
}

export function luminance(hex: string): number {
  const [r, g, b] = [1, 3, 5]
    .map((start) => parseInt(hex.slice(start, start + 2), 16) / 255)
    .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4))

  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

export function contrast(first: string, second: string): number {
  const [light, dark] = [luminance(first), luminance(second)].sort((a, b) => b - a)

  return (light + 0.05) / (dark + 0.05)
}

/**
 * Pares de tokens que la interfaz combina, con su mínimo WCAG 2.x AA: 4.5:1 para texto y 3:1 para
 * componentes de interfaz y foco. Los usan la prueba de contraste y la de documentación
 * (`docs/design/sistema-de-color.md` publica la misma tabla). Nombres sin el prefijo `--sciem-`.
 */
export const TEXT_MIN = 4.5
export const UI_MIN = 3

export const CONTRAST_PAIRS: [foreground: string, background: string, minimum: number][] = [
  ['text', 'bg-app', TEXT_MIN],
  ['text', 'surface', TEXT_MIN],
  ['text', 'raised', TEXT_MIN],
  ['text', 'field-bg', TEXT_MIN],
  ['muted', 'bg-app', TEXT_MIN],
  ['muted', 'surface', TEXT_MIN],
  ['muted', 'sunken', TEXT_MIN],
  ['muted', 'raised', TEXT_MIN],
  ['muted', 'muted-bg', TEXT_MIN],
  ['muted', 'brand-soft', TEXT_MIN],
  ['muted', 'field-bg', TEXT_MIN],
  ['subtle', 'bg-app', TEXT_MIN],
  ['subtle', 'surface', TEXT_MIN],
  ['brand-fg', 'bg-app', TEXT_MIN],
  ['brand-fg', 'surface', TEXT_MIN],
  ['brand-fg', 'raised', TEXT_MIN],
  ['brand-fg', 'brand-soft', TEXT_MIN],
  ['brand-deep', 'brand-soft', TEXT_MIN],
  ['brand-deep', 'surface', TEXT_MIN],
  ['ok-fg', 'ok-soft', TEXT_MIN],
  ['warn-fg', 'warn-soft', TEXT_MIN],
  ['danger-fg', 'danger-soft', TEXT_MIN],
  ['info', 'info-soft', TEXT_MIN],
  ['neutral-fg', 'neutral-soft', TEXT_MIN],
  ['danger', 'surface', TEXT_MIN],
  ['danger-fg', 'surface', TEXT_MIN],
  ['ok-fg', 'surface', TEXT_MIN],
  ['warn-fg', 'surface', TEXT_MIN],
  ['on-solid', 'brand', TEXT_MIN],
  ['on-solid', 'brand-hover', TEXT_MIN],
  ['on-solid', 'brand-active', TEXT_MIN],
  ['on-solid', 'brand-strong', TEXT_MIN],
  ['on-solid', 'danger-solid', TEXT_MIN],
  ['on-solid', 'ok-solid', TEXT_MIN],
  ['on-solid', 'ok-solid-hover', TEXT_MIN],
  ['on-solid', 'neutral-solid', TEXT_MIN],
  ['on-accent', 'accent', TEXT_MIN],
  ['on-accent', 'accent-hover', TEXT_MIN],
  ['sb-text', 'sb-bg', TEXT_MIN],
  ['sb-text', 'sb-hover', TEXT_MIN],
  ['sb-text', 'sb-active', TEXT_MIN],
  ['sb-sub', 'sb-bg', TEXT_MIN],
  ['sb-group', 'sb-bg', TEXT_MIN],
  ['on-solid', 'sb-active', TEXT_MIN],
  ['sb-focus', 'sb-bg', UI_MIN],
  ['focus', 'bg-app', UI_MIN],
  ['focus', 'surface', UI_MIN],
  ['focus', 'raised', UI_MIN],
  ['input-border', 'bg-app', UI_MIN],
  ['input-border', 'surface', UI_MIN],
  ['input-border', 'raised', UI_MIN],
  ['input-border', 'field-bg', UI_MIN],
  ['ok', 'surface', UI_MIN],
  ['brand', 'surface', UI_MIN],
]
