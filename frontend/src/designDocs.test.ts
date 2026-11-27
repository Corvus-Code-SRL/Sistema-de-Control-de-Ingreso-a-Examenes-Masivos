import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import { color, CONTRAST_PAIRS, contrast, frontendPath, indexCss, layers, type Theme } from '@/test/designTokens'

/**
 * Mantiene vigente `docs/design/sistema-de-color.md` (RNF-19): sus tablas de tokens y de contraste deben
 * coincidir con `src/index.css`. Si alguien cambia un valor de la paleta sin actualizar el documento (o al
 * revés), esta prueba dice qué fila quedó desactualizada.
 */

const doc = readFileSync(frontendPath('../docs/design/sistema-de-color.md'), 'utf8')

const SAME_AS_LIGHT = 'Igual que el claro'

function sectionOf(start: string, end: string): string {
  const from = doc.indexOf(start)
  const to = doc.indexOf(end, from)

  if (from < 0 || to < 0) throw new Error(`El documento no tiene la sección «${start}»`)

  return doc.slice(from, to)
}

/** Celdas de las filas de tabla cuya primera columna es una propiedad personalizada en código. */
function rows(section: string): string[][] {
  return section
    .split(/\r?\n/)
    .filter((line) => /^\| `--[a-z0-9-]+` \|/.test(line))
    .map((line) => line.slice(1, -1).split(' | ').map((cell) => cell.trim()))
}

const code = (cell: string) => cell.match(/^`(.+)`$/)?.[1] ?? cell

/** Tabla `variable → valor` del documento, sin duplicados. */
function table(section: string, valueColumn = 1): Map<string, string> {
  const found = new Map<string, string>()

  for (const cells of rows(section)) {
    const name = code(cells[0])

    if (found.has(name)) throw new Error(`El documento repite la fila de ${name}`)
    found.set(name, code(cells[valueColumn]))
  }

  return found
}

const sorted = (names: Iterable<string>) => [...names].sort()

describe('docs/design/sistema-de-color.md', () => {
  const palette = sectionOf('### 4.1.', '### 4.2.')

  it('documenta todos los tokens de la capa 1 y ninguno que no exista', () => {
    expect(sorted(table(palette).keys())).toEqual(sorted(layers.light.keys()))
  })

  it('los valores del tema claro coinciden con index.css', () => {
    const documented = table(palette, 1)

    for (const [name, value] of layers.light) expect(documented.get(name), name).toBe(value)
  })

  it('los valores del tema oscuro coinciden con index.css («Igual que el claro» si no se redefine)', () => {
    const documented = table(palette, 2)

    for (const name of layers.light.keys()) {
      expect(documented.get(name), name).toBe(layers.dark.get(name) ?? SAME_AS_LIGHT)
    }
  })

  it('la capa 2 (shadcn) coincide con index.css', () => {
    expect(Object.fromEntries(table(sectionOf('### 4.2.', '### 4.3.')))).toEqual(Object.fromEntries(layers.semantic))
  })

  it('la capa 3 (@theme de Tailwind) coincide con index.css', () => {
    expect(Object.fromEntries(table(sectionOf('### 4.3.', '## 5.')))).toEqual(Object.fromEntries(layers.tailwind))
  })

  it('la tabla de contrastes publica exactamente los pares verificados, con sus relaciones', () => {
    const published = sectionOf('## 5.', '## 6.')
      .split(/\r?\n/)
      .filter((line) => /^\| `[a-z0-9-]+` \| `[a-z0-9-]+` \|/.test(line))
      .map((line) => line.slice(1, -1).split(' | ').map((cell) => code(cell.trim())))

    const ratio = (theme: Theme, foreground: string, background: string) =>
      contrast(color(theme, `--sciem-${foreground}`), color(theme, `--sciem-${background}`)).toFixed(2)

    const expected = CONTRAST_PAIRS.map(([foreground, background, minimum]) => [
      foreground,
      background,
      ratio('light', foreground, background),
      ratio('dark', foreground, background),
      String(minimum),
    ])

    expect(published).toEqual(expected)
  })

  it('todo valor hex citado en el documento existe en index.css', () => {
    const known = new Set((indexCss.match(/#[0-9A-Fa-f]{6}\b/g) ?? []).map((hex) => hex.toUpperCase()))
    const cited = (doc.match(/#[0-9A-Fa-f]{6}\b/g) ?? []).map((hex) => hex.toUpperCase())

    expect(cited.filter((hex) => !known.has(hex))).toEqual([])
  })

  it('está escrito para convertirse a LaTeX: sin HTML fuera de código ni emojis', () => {
    const prose = doc.replace(/```[\s\S]*?```/g, '').replace(/`[^`]*`/g, '')

    expect(prose).not.toMatch(/<\/?[a-z][^>]*>/i)
    expect(prose).not.toMatch(/\p{Extended_Pictographic}/u)
  })
})
