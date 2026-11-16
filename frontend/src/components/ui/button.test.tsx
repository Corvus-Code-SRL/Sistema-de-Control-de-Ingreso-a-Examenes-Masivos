import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Button, buttonVariants } from './button'

/**
 * Variante `success` (HU-062: botón «Rectificar»). Sus colores salen de los tokens `--sciem-ok*`; la prueba
 * lee `index.css` y mide la relación de contraste WCAG de cada estado para que un cambio de token no la
 * deje por debajo de 4.5:1 (texto) o 3:1 (anillo de foco).
 */

const css = readFileSync(resolve(process.cwd(), 'src/index.css'), 'utf8')

function token(name: string): string {
  const match = css.match(new RegExp(`${name}:\\s*(#[0-9A-Fa-f]{6})`))

  if (!match) throw new Error(`No se encontró el token ${name}`)

  return match[1]
}

function luminance(hex: string): number {
  const [r, g, b] = [1, 3, 5]
    .map((start) => parseInt(hex.slice(start, start + 2), 16) / 255)
    .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4))

  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contrast(first: string, second: string): number {
  const [light, dark] = [luminance(first), luminance(second)].sort((a, b) => b - a)

  return (light + 0.05) / (dark + 0.05)
}

const white = token('--primary-foreground')
const ok = token('--sciem-ok')
const okDark = token('--sciem-ok-fg')
const surface = token('--sciem-surface')
const appBackground = token('--sciem-bg-app')

describe('Button variant="success"', () => {
  it('usa fondo ok, texto blanco y oscurece a ok-fg al pasar el cursor y al presionar', () => {
    const classes = buttonVariants({ variant: 'success' })

    expect(classes).toContain('bg-ok')
    expect(classes).toContain('text-primary-foreground')
    expect(classes).toContain('hover:bg-ok-fg')
    expect(classes).toContain('active:bg-ok-fg')
  })

  it('no usa transparencia sobre el verde: bg-ok/90 bajaría de 4.5:1', () => {
    expect(buttonVariants({ variant: 'success' })).not.toMatch(/bg-ok\/\d+/)
  })

  it('el anillo de foco es el verde sólido, no el 50 % del anillo por defecto', () => {
    const classes = buttonVariants({ variant: 'success' })

    expect(classes).toContain('focus-visible:ring-ok')
    expect(classes).toContain('focus-visible:border-ok')
  })

  it('el tamaño xl es grande (h-11) y deja espacio al icono', () => {
    render(
      <Button variant="success" size="xl">
        <svg aria-hidden="true" />
        Rectificar
      </Button>
    )

    expect(screen.getByRole('button', { name: 'Rectificar' })).toHaveClass('h-11', 'gap-2', 'px-5')
  })

  it('reposo: texto blanco sobre ok alcanza al menos 4.5:1', () => {
    expect(contrast(white, ok)).toBeGreaterThanOrEqual(4.5)
  })

  it('hover y presionado: texto blanco sobre ok-fg alcanza al menos 4.5:1', () => {
    expect(contrast(white, okDark)).toBeGreaterThanOrEqual(4.5)
  })

  it('anillo de foco: el verde ok contra la tarjeta y el fondo de la app alcanza al menos 3:1', () => {
    expect(contrast(ok, surface)).toBeGreaterThanOrEqual(3)
    expect(contrast(ok, appBackground)).toBeGreaterThanOrEqual(3)
  })

  it('documenta las relaciones medidas', () => {
    const measured = {
      reposo: contrast(white, ok),
      hover: contrast(white, okDark),
      anilloSobreTarjeta: contrast(ok, surface),
      anilloSobreFondo: contrast(ok, appBackground),
    }

    expect(measured.reposo).toBeCloseTo(5.02, 1)
    expect(measured.hover).toBeCloseTo(8.12, 1)
    expect(measured.anilloSobreTarjeta).toBeCloseTo(5.02, 1)
  })
})
