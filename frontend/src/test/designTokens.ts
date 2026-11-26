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

const palettes: Record<Theme, Map<string, string>> = {
  light: lightPalette,
  dark: new Map([...lightPalette, ...declarations(section(DARK_PALETTE, SEMANTIC_LAYER))]),
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
