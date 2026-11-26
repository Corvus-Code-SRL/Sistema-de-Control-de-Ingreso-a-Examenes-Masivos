import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { color, contrast, THEMES } from '@/test/designTokens'
import { Button, buttonVariants } from './button'

/**
 * Variante `success` (HU-062: botón «Rectificar»). Sus colores salen de los tokens `--sciem-ok-solid*`; la
 * prueba lee `index.css` y mide la relación de contraste WCAG de cada estado, en el tema claro y en el
 * oscuro, para que un cambio de token no la deje por debajo de 4.5:1 (texto) o 3:1 (anillo de foco).
 */

describe('Button variant="success"', () => {
  it('usa el relleno ok sólido, texto blanco y oscurece al pasar el cursor y al presionar', () => {
    const classes = buttonVariants({ variant: 'success' })

    expect(classes).toContain('bg-ok-solid')
    expect(classes).toContain('text-on-solid')
    expect(classes).toContain('hover:bg-ok-solid-hover')
    expect(classes).toContain('active:bg-ok-solid-hover')
  })

  it('no usa transparencia sobre el verde: bg-ok-solid/90 bajaría de 4.5:1', () => {
    expect(buttonVariants({ variant: 'success' })).not.toMatch(/bg-ok(-solid)?\/\d+/)
  })

  it('el anillo de foco es el verde sólido, no el halo de foco por defecto', () => {
    const classes = buttonVariants({ variant: 'success' })

    expect(classes).toContain('focus-visible:ring-ok-solid')
    expect(classes).toContain('focus-visible:border-ok-solid')
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

  describe.each(THEMES)('tema %s', (theme) => {
    const white = color(theme, '--sciem-on-solid')
    const ok = color(theme, '--sciem-ok-solid')
    const okHover = color(theme, '--sciem-ok-solid-hover')

    it('reposo: texto blanco sobre ok-solid alcanza al menos 4.5:1', () => {
      expect(contrast(white, ok)).toBeGreaterThanOrEqual(4.5)
    })

    it('hover y presionado: texto blanco sobre ok-solid-hover alcanza al menos 4.5:1', () => {
      expect(contrast(white, okHover)).toBeGreaterThanOrEqual(4.5)
    })

    it('anillo de foco: ok-solid contra la tarjeta y el fondo de la app alcanza al menos 3:1', () => {
      expect(contrast(ok, color(theme, '--sciem-surface'))).toBeGreaterThanOrEqual(3)
      expect(contrast(ok, color(theme, '--sciem-bg-app'))).toBeGreaterThanOrEqual(3)
    })
  })

  it('documenta las relaciones medidas', () => {
    const measured = (theme: 'light' | 'dark') => ({
      reposo: contrast(color(theme, '--sciem-on-solid'), color(theme, '--sciem-ok-solid')),
      hover: contrast(color(theme, '--sciem-on-solid'), color(theme, '--sciem-ok-solid-hover')),
      anilloSobreTarjeta: contrast(color(theme, '--sciem-ok-solid'), color(theme, '--sciem-surface')),
    })

    expect(measured('light').reposo).toBeCloseTo(5.02, 1)
    expect(measured('light').hover).toBeCloseTo(8.12, 1)
    expect(measured('light').anilloSobreTarjeta).toBeCloseTo(5.02, 1)
    expect(measured('dark').reposo).toBeCloseTo(5.01, 1)
    expect(measured('dark').hover).toBeCloseTo(6.3, 1)
    expect(measured('dark').anilloSobreTarjeta).toBeCloseTo(3.21, 1)
  })
})
