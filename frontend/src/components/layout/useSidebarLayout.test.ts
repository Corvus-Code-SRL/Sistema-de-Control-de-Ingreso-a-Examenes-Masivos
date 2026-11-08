import { act, fireEvent, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  SIDEBAR_DEFAULT_WIDTH,
  SIDEBAR_MAX_WIDTH,
  SIDEBAR_MIN_WIDTH,
  clampSidebarWidth,
  useSidebarLayout
} from './useSidebarLayout'

const KEY = 'sciem.sidebar-layout.v1.user-1'

function stored(): unknown {
  const raw = window.localStorage.getItem(KEY)

  return raw === null ? null : JSON.parse(raw)
}

function pressShortcut(init: KeyboardEventInit, target: EventTarget = window) {
  const event = new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init })

  act(() => {
    target.dispatchEvent(event)
  })

  return event
}

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('useSidebarLayout — estado y persistencia', () => {
  it('arranca visible y con el ancho por defecto', () => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))

    expect(result.current.collapsed).toBe(false)
    expect(result.current.width).toBe(SIDEBAR_DEFAULT_WIDTH)
  })

  it('recuerda el colapso y el ancho en una sola clave por usuario', () => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))

    act(() => {
      result.current.toggle()
      result.current.setWidth(300)
    })

    expect(stored()).toEqual({ collapsed: true, width: 300 })

    const again = renderHook(() => useSidebarLayout('user-1'))

    expect(again.result.current.collapsed).toBe(true)
    expect(again.result.current.width).toBe(300)
  })

  it('no mezcla lo guardado de un usuario con el de otro ni con el anónimo', () => {
    window.localStorage.setItem(KEY, JSON.stringify({ collapsed: true, width: 300 }))

    expect(renderHook(() => useSidebarLayout('user-2')).result.current.collapsed).toBe(false)
    expect(renderHook(() => useSidebarLayout(undefined)).result.current.collapsed).toBe(false)
  })

  it('al cambiar de usuario sin desmontar lee el de ese usuario y no pisa la clave del anterior', () => {
    const keyA = 'sciem.sidebar-layout.v1.user-a'
    const keyB = 'sciem.sidebar-layout.v1.user-b'
    window.localStorage.setItem(keyA, JSON.stringify({ collapsed: false, width: 320 }))
    window.localStorage.setItem(keyB, JSON.stringify({ collapsed: true, width: 240 }))

    const { result, rerender } = renderHook(({ id }) => useSidebarLayout(id), {
      initialProps: { id: 'user-a' as string | undefined }
    })

    expect(result.current.width).toBe(320)

    rerender({ id: 'user-b' })

    expect(result.current.width).toBe(240)
    expect(result.current.collapsed).toBe(true)
    expect(window.localStorage.getItem(keyA)).toBe(JSON.stringify({ collapsed: false, width: 320 }))
    expect(window.localStorage.getItem(keyB)).toBe(JSON.stringify({ collapsed: true, width: 240 }))

    // Los cambios posteriores se guardan bajo el usuario nuevo y el anterior sigue intacto.
    act(() => result.current.setWidth(260))

    expect(window.localStorage.getItem(keyB)).toBe(JSON.stringify({ collapsed: true, width: 260 }))
    expect(window.localStorage.getItem(keyA)).toBe(JSON.stringify({ collapsed: false, width: 320 }))
  })

  it('limita el ancho a los extremos y lo redondea', () => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))

    act(() => result.current.setWidth(10))
    expect(result.current.width).toBe(SIDEBAR_MIN_WIDTH)

    act(() => result.current.setWidth(9999))
    expect(result.current.width).toBe(SIDEBAR_MAX_WIDTH)

    act(() => result.current.setWidth(300.6))
    expect(result.current.width).toBe(301)
    expect(clampSidebarWidth(SIDEBAR_MIN_WIDTH - 1)).toBe(SIDEBAR_MIN_WIDTH)
  })

  it('restablece el ancho por defecto', () => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))

    act(() => result.current.setWidth(380))
    act(() => result.current.resetWidth())

    expect(result.current.width).toBe(SIDEBAR_DEFAULT_WIDTH)
    expect(stored()).toEqual({ collapsed: false, width: SIDEBAR_DEFAULT_WIDTH })
  })

  it.each([
    ['texto que no es JSON', 'no-es-json'],
    ['un valor que no es objeto', '42'],
    ['un ancho fuera de rango', JSON.stringify({ collapsed: true, width: 9999 })],
    ['un ancho que no es número', JSON.stringify({ collapsed: true, width: '300' })]
  ])('ignora lo guardado con %s', (_name, raw) => {
    window.localStorage.setItem(KEY, raw)

    const { result } = renderHook(() => useSidebarLayout('user-1'))

    expect(result.current.width).toBe(SIDEBAR_DEFAULT_WIDTH)
  })

  it('conserva el colapso aunque el ancho guardado sea inválido', () => {
    window.localStorage.setItem(KEY, JSON.stringify({ collapsed: true, width: 1 }))

    const { result } = renderHook(() => useSidebarLayout('user-1'))

    expect(result.current.collapsed).toBe(true)
    expect(result.current.width).toBe(SIDEBAR_DEFAULT_WIDTH)
  })

  it('funciona aunque localStorage lance al leer y al escribir', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('bloqueado')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('cuota llena')
    })

    const { result } = renderHook(() => useSidebarLayout('user-1'))

    expect(result.current.collapsed).toBe(false)

    act(() => result.current.toggle())
    act(() => result.current.setWidth(320))

    expect(result.current.collapsed).toBe(true)
    expect(result.current.width).toBe(320)
  })
})

describe('useSidebarLayout — atajo Ctrl+B / Cmd+B', () => {
  it('alterna con Ctrl+B y con Cmd+B, y evita la acción del navegador', () => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))

    const ctrl = pressShortcut({ key: 'b', ctrlKey: true })
    expect(result.current.collapsed).toBe(true)
    expect(ctrl.defaultPrevented).toBe(true)

    pressShortcut({ key: 'b', metaKey: true })
    expect(result.current.collapsed).toBe(false)

    pressShortcut({ key: 'B', ctrlKey: true })
    expect(result.current.collapsed).toBe(true)
  })

  it('ignora la tecla B sola y las combinaciones con Alt o Mayús', () => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))

    pressShortcut({ key: 'b' })
    pressShortcut({ key: 'b', ctrlKey: true, altKey: true })
    pressShortcut({ key: 'b', ctrlKey: true, shiftKey: true })
    pressShortcut({ key: 'k', ctrlKey: true })

    expect(result.current.collapsed).toBe(false)
  })

  it.each(['input', 'textarea', 'select'])('se ignora mientras se escribe en un %s', (tag) => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))
    const field = document.body.appendChild(document.createElement(tag))

    const event = pressShortcut({ key: 'b', ctrlKey: true }, field)

    expect(result.current.collapsed).toBe(false)
    // Deja a la página la acción habitual de la tecla (p. ej. negrita en un editor).
    expect(event.defaultPrevented).toBe(false)
  })

  it('se ignora dentro de un elemento contenteditable', () => {
    const { result } = renderHook(() => useSidebarLayout('user-1'))
    const editor = document.body.appendChild(document.createElement('div'))
    editor.setAttribute('contenteditable', 'true')
    const inner = editor.appendChild(document.createElement('span'))

    pressShortcut({ key: 'b', ctrlKey: true }, inner)

    expect(result.current.collapsed).toBe(false)
  })

  it('deja de escuchar al desmontarse', () => {
    const { result, unmount } = renderHook(() => useSidebarLayout('user-1'))

    unmount()
    fireEvent.keyDown(window, { key: 'b', ctrlKey: true })

    expect(result.current.collapsed).toBe(false)
  })
})
