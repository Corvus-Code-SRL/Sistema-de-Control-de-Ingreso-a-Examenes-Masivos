import { createRef } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { SidebarResizeHandle } from './SidebarResizeHandle'

function setup(width = 272) {
  const targetRef = createRef<HTMLElement>()
  const onResize = vi.fn()
  const onReset = vi.fn()

  const { unmount } = render(
    <>
      <aside ref={targetRef} data-testid="aside" style={{ ['--sidebar-width' as string]: `${width}px` }}>
        <SidebarResizeHandle
          width={width}
          targetRef={targetRef}
          onResize={onResize}
          onReset={onReset}
        />
      </aside>
    </>
  )

  return {
    handle: screen.getByRole('separator', { name: 'Cambiar ancho del menú' }),
    aside: screen.getByTestId('aside'),
    onResize,
    onReset,
    unmount
  }
}

const pointer = { pointerId: 1, button: 0 }

describe('SidebarResizeHandle', () => {
  it('es un separador vertical enfocable con sus límites y su valor actual', () => {
    const { handle } = setup(300)

    expect(handle).toHaveAttribute('aria-orientation', 'vertical')
    expect(handle).toHaveAttribute('aria-valuemin', '224')
    expect(handle).toHaveAttribute('aria-valuemax', '400')
    expect(handle).toHaveAttribute('aria-valuenow', '300')
    expect(handle).toHaveAttribute('tabindex', '0')
  })

  it('ArrowRight y ArrowLeft cambian el ancho de 16 en 16 px', () => {
    const { handle, onResize } = setup(272)

    fireEvent.keyDown(handle, { key: 'ArrowRight' })
    fireEvent.keyDown(handle, { key: 'ArrowLeft' })

    expect(onResize).toHaveBeenNthCalledWith(1, 288)
    expect(onResize).toHaveBeenNthCalledWith(2, 256)
  })

  it('otras teclas no hacen nada (sin Home ni End)', () => {
    const { handle, onResize } = setup()

    fireEvent.keyDown(handle, { key: 'Home' })
    fireEvent.keyDown(handle, { key: 'End' })
    fireEvent.keyDown(handle, { key: 'ArrowUp' })

    expect(onResize).not.toHaveBeenCalled()
  })

  it('el doble clic restablece el ancho', () => {
    const { handle, onReset } = setup(380)

    fireEvent.doubleClick(handle)

    expect(onReset).toHaveBeenCalledTimes(1)
  })

  it('al arrastrar mueve solo la variable CSS y confirma el ancho al soltar', () => {
    const { handle, aside, onResize } = setup(272)

    fireEvent.pointerDown(handle, { ...pointer, clientX: 272 })
    expect(aside.dataset.resizing).toBe('true')

    fireEvent.pointerMove(handle, { ...pointer, clientX: 312 })
    expect(aside.style.getPropertyValue('--sidebar-width')).toBe('312px')
    expect(handle).toHaveAttribute('aria-valuenow', '312')
    expect(onResize).not.toHaveBeenCalled()

    fireEvent.pointerUp(handle, { ...pointer, clientX: 312 })

    expect(onResize).toHaveBeenCalledTimes(1)
    expect(onResize).toHaveBeenCalledWith(312)
    expect(aside.dataset.resizing).toBeUndefined()
  })

  it('respeta los límites mínimo y máximo al arrastrar', () => {
    const { handle, aside, onResize } = setup(272)

    fireEvent.pointerDown(handle, { ...pointer, clientX: 272 })
    fireEvent.pointerMove(handle, { ...pointer, clientX: 0 })
    expect(aside.style.getPropertyValue('--sidebar-width')).toBe('224px')

    fireEvent.pointerMove(handle, { ...pointer, clientX: 5000 })
    expect(aside.style.getPropertyValue('--sidebar-width')).toBe('400px')

    fireEvent.pointerUp(handle, { ...pointer, clientX: 5000 })
    expect(onResize).toHaveBeenCalledWith(400)
  })

  it('bloquea la selección de texto solo mientras se arrastra', () => {
    const { handle } = setup()

    fireEvent.pointerDown(handle, { ...pointer, clientX: 272 })
    expect(document.body.style.userSelect).toBe('none')
    expect(document.body.style.cursor).toBe('col-resize')

    fireEvent.pointerUp(handle, { ...pointer, clientX: 272 })
    expect(document.body.style.userSelect).toBe('')
    expect(document.body.style.cursor).toBe('')
  })

  it('cancelar el gesto devuelve el ancho confirmado y no lo guarda', () => {
    const { handle, aside, onResize } = setup(272)

    fireEvent.pointerDown(handle, { ...pointer, clientX: 272 })
    fireEvent.pointerMove(handle, { ...pointer, clientX: 340 })
    fireEvent.pointerCancel(handle, pointer)

    expect(aside.style.getPropertyValue('--sidebar-width')).toBe('272px')
    expect(onResize).not.toHaveBeenCalled()
    expect(document.body.style.userSelect).toBe('')
  })

  it('ignora los botones distintos del principal y los movimientos sin pulsar', () => {
    const { handle, aside, onResize } = setup(272)

    fireEvent.pointerMove(handle, { ...pointer, clientX: 400 })
    fireEvent.pointerDown(handle, { ...pointer, button: 2, clientX: 272 })
    fireEvent.pointerMove(handle, { ...pointer, clientX: 400 })
    fireEvent.pointerUp(handle, { ...pointer, button: 2, clientX: 400 })

    expect(aside.style.getPropertyValue('--sidebar-width')).toBe('272px')
    expect(onResize).not.toHaveBeenCalled()
  })

  it('al desmontarse a mitad del arrastre devuelve la selección de texto', () => {
    const { handle, unmount } = setup()

    fireEvent.pointerDown(handle, { ...pointer, clientX: 272 })
    expect(document.body.style.userSelect).toBe('none')

    unmount()

    expect(document.body.style.userSelect).toBe('')
    expect(document.body.style.cursor).toBe('')
  })
})
