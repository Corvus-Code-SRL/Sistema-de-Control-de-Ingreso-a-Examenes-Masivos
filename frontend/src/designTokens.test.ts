import { readdirSync, readFileSync } from 'node:fs'
import { join, relative } from 'node:path'
import { describe, expect, it } from 'vitest'
import { color, declaredProperties, frontendPath, indexCss, paletteCss } from '@/test/designTokens'

/**
 * Guardián de la regla de tokens (RNF-19): ningún color se escribe a mano fuera de la capa 1 de
 * `index.css`, toda utilidad de color apunta a un token declarado y la copia de referencia de los tokens,
 * así como los `theme-color` de `index.html`, siguen al archivo fuente.
 */

const SRC = frontendPath('src')

function sourceFiles(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name)

    if (entry.isDirectory()) return path === join(SRC, 'test') ? [] : sourceFiles(path)

    return /\.tsx?$/.test(entry.name) && !/\.test\.tsx?$/.test(entry.name) ? [path] : []
  })
}

const sources = sourceFiles(SRC).map((path) => ({ file: relative(SRC, path), text: readFileSync(path, 'utf8') }))

/** Líneas `archivo:línea  fragmento` donde aparece el patrón, para que el fallo diga qué corregir. */
function findAll(pattern: RegExp, accept: (match: RegExpMatchArray) => boolean = () => true): string[] {
  return sources.flatMap(({ file, text }) =>
    text.split('\n').flatMap((line, index) =>
      [...line.matchAll(pattern)].filter(accept).map((match) => `${file}:${index + 1}  ${match[0]}`)
    )
  )
}

const withoutComments = (css: string) => css.replace(/\/\*[\s\S]*?\*\//g, '')

const COLOR_LITERAL = /#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch)\(/g

describe('regla de tokens en el código fuente', () => {
  it('no hay literales de color (hex, rgb, hsl, oklch…)', () => {
    expect(findAll(COLOR_LITERAL)).toEqual([])
  })

  it('no usa white ni black de Tailwind: el texto sobre rellenos es on-solid u on-accent', () => {
    expect(findAll(/(?<![\w-])(?:bg|text|border|ring|fill|stroke|outline|divide|shadow)-(?:white|black)\b/g)).toEqual([])
  })

  it('no usa la paleta por defecto de Tailwind (gray-500, slate-800…)', () => {
    const palette =
      'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose'

    expect(findAll(new RegExp(`(?<![\\w-])[a-z]+(?:-[xytblrse])?-(?:${palette})-(?:50|[1-9]00|950)\\b`, 'g'))).toEqual([])
  })

  it('toda utilidad de color apunta a un token declarado en @theme', () => {
    const tokens = new Set([...declaredProperties].filter((name) => name.startsWith('--color-')).map((name) => name.slice(8)))
    const keywords = new Set(['transparent', 'current', 'inherit'])
    // Sufijos de las mismas utilidades que no son colores (tamaño, alineación, estilo de borde…).
    const notColor =
      /^(?:xs|sm|base|md|lg|xl|\dxl|left|center|right|justify|start|end|balance|pretty|wrap|nowrap|ellipsis|clip|none|hidden|solid|dashed|dotted|double|collapse|separate|inset|offset-\d+|clip-[a-z]+|blend-[a-z-]+|origin-[a-z-]+|no-repeat|repeat(?:-[a-z]+)?|cover|contain|fixed|local|scroll|[xytblrse]|[xytblrse]-\d+|x-reverse|y-reverse|wavy|from-font|auto|linear-[a-z-]+)$/
    const utility =
      /(?<![\w-])(bg|text|border(?:-[xytblrse])?|ring|outline|divide|fill|stroke|decoration|placeholder|caret|accent)-([a-z][a-z0-9-]*?)(?:\/\d+)?(?![\w-])/g

    expect(findAll(utility, ([, , name]) => !notColor.test(name) && !tokens.has(name) && !keywords.has(name))).toEqual([])
  })

  it('no usa text-muted: en shadcn `muted` es la superficie; el gris de texto es text-muted-foreground', () => {
    expect(findAll(/(?<![\w-])text-muted(?![\w-])/g)).toEqual([])
  })

  it('no referencia propiedades personalizadas que no existen', () => {
    // Se fijan en tiempo de ejecución (estilo en línea) o las publica una librería, no index.css.
    const runtime = (name: string) => name === '--sidebar-width' || /^--(?:radix|tw|card-spacing)-?/.test(name)

    expect(findAll(/var\((--[a-z0-9-]+)|-\((--[a-z0-9-]+)\)/g, ([, viaVar, viaShorthand]) => {
      const name = viaVar ?? viaShorthand
      return !declaredProperties.has(name) && !runtime(name)
    })).toEqual([])
  })
})

describe('regla de tokens en index.css', () => {
  it('los literales de color existen solo en la capa 1 (paleta cruda clara y oscura)', () => {
    const outsidePalette = withoutComments(indexCss.replace(paletteCss, ''))

    expect(outsidePalette.match(COLOR_LITERAL) ?? []).toEqual([])
  })

  it('cada var(--x) de index.css apunta a una propiedad declarada', () => {
    const referenced = [...withoutComments(indexCss).matchAll(/var\((--[a-z0-9-]+)/g)].map(([, name]) => name)

    expect(referenced.filter((name) => !declaredProperties.has(name))).toEqual([])
  })

  it('docs/design/sciem-tokens.css reproduce todo lo que sigue a los @import', () => {
    const sourceLine = '   Copia de referencia: docs/design/sciem-tokens.css (mantener'
    const copyLine = '   Fuente: frontend/src/index.css (mantener'
    const body = indexCss.slice(indexCss.indexOf('/*', indexCss.lastIndexOf('@import'))).replace(sourceLine, copyLine)
    const copy = readFileSync(frontendPath('../docs/design/sciem-tokens.css'), 'utf8')

    expect(copy.slice(copy.indexOf(body.slice(0, 60)))).toBe(body)
  })
})

describe('index.html', () => {
  const html = readFileSync(frontendPath('index.html'), 'utf8')
  const themeColor = (scheme: string) =>
    html.match(new RegExp(`name="theme-color" content="(#[0-9A-Fa-f]{6})" media="\\(prefers-color-scheme: ${scheme}\\)"`))?.[1]

  it('theme-color replica --sciem-bg-app de cada tema', () => {
    expect(themeColor('light')?.toUpperCase()).toBe(color('light', '--sciem-bg-app').toUpperCase())
    expect(themeColor('dark')?.toUpperCase()).toBe(color('dark', '--sciem-bg-app').toUpperCase())
  })

  it('declara que la página admite los dos esquemas de color', () => {
    expect(html).toContain('<meta name="color-scheme" content="light dark" />')
  })
})
