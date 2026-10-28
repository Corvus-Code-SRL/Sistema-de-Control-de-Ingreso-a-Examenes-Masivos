import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useSidebarSections } from './useSidebarSections'

const IDS = ['a', 'b', 'c']
const KEY = 'sciem.sidebar.v1.user-1'

function stored(key = KEY): string[] | null {
  const raw = window.localStorage.getItem(key)

  return raw === null ? null : (JSON.parse(raw) as string[])
}

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('useSidebarSections', () => {
  it('sin nada guardado abre solo la sección activa', () => {
    const { result } = renderHook(() => useSidebarSections('user-1', IDS, 'b'))

    expect(IDS.filter(result.current.isOpen)).toEqual(['b'])
  })

  it('sin nada guardado ni sección activa abre la primera', () => {
    const { result } = renderHook(() => useSidebarSections('user-1', IDS, undefined))

    expect(IDS.filter(result.current.isOpen)).toEqual(['a'])
  })

  it('recupera lo guardado y abre además la sección activa', () => {
    window.localStorage.setItem(KEY, JSON.stringify(['c']))

    const { result } = renderHook(() => useSidebarSections('user-1', IDS, 'a'))

    expect(IDS.filter(result.current.isOpen)).toEqual(['a', 'c'])
  })

  it('lo guardado de un usuario no afecta a otro', () => {
    window.localStorage.setItem(KEY, JSON.stringify(['c']))

    const { result } = renderHook(() => useSidebarSections('user-2', IDS, undefined))

    expect(IDS.filter(result.current.isOpen)).toEqual(['a'])
  })

  it('recuerda cada cambio en el almacenamiento', () => {
    const { result } = renderHook(() => useSidebarSections('user-1', IDS, 'a'))

    act(() => result.current.toggle('b'))
    expect(stored()).toEqual(['a', 'b'])

    act(() => result.current.toggle('a'))
    expect(stored()).toEqual(['b'])
  })

  it('un menú montado después recuerda lo que dejó el anterior', () => {
    const first = renderHook(() => useSidebarSections('user-1', IDS, 'a'))
    act(() => first.result.current.toggle('c'))
    first.unmount()

    const second = renderHook(() => useSidebarSections('user-1', IDS, 'a'))

    expect(IDS.filter(second.result.current.isOpen)).toEqual(['a', 'c'])
  })

  it('expande y contrae todas las secciones', () => {
    const { result } = renderHook(() => useSidebarSections('user-1', IDS, 'a'))

    expect(result.current.allOpen).toBe(false)
    expect(result.current.noneOpen).toBe(false)

    act(() => result.current.expandAll())
    expect(IDS.every(result.current.isOpen)).toBe(true)
    expect(result.current.allOpen).toBe(true)

    act(() => result.current.collapseAll())
    expect(IDS.some(result.current.isOpen)).toBe(false)
    expect(result.current.noneOpen).toBe(true)
    expect(stored()).toEqual([])
  })

  it('al navegar a otra sección con el menú montado, la abre', () => {
    const { result, rerender } = renderHook(
      ({ active }) => useSidebarSections('user-1', IDS, active),
      { initialProps: { active: 'a' as string | undefined } }
    )

    rerender({ active: 'c' })

    expect(IDS.filter(result.current.isOpen)).toEqual(['a', 'c'])
  })

  it('ignora un contenido guardado que no es una lista de textos', () => {
    window.localStorage.setItem(KEY, '{"a":true}')

    const { result } = renderHook(() => useSidebarSections('user-1', IDS, 'b'))

    expect(IDS.filter(result.current.isOpen)).toEqual(['b'])
  })

  it('ignora un contenido guardado que no es JSON', () => {
    window.localStorage.setItem(KEY, 'no-es-json')

    const { result } = renderHook(() => useSidebarSections('user-1', IDS, 'b'))

    expect(IDS.filter(result.current.isOpen)).toEqual(['b'])
  })

  it('funciona sin almacenamiento: no lee ni guarda, pero el menú responde', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('bloqueado')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('bloqueado')
    })

    const { result } = renderHook(() => useSidebarSections('user-1', IDS, 'a'))

    expect(IDS.filter(result.current.isOpen)).toEqual(['a'])

    act(() => result.current.toggle('b'))

    expect(IDS.filter(result.current.isOpen)).toEqual(['a', 'b'])
  })
})
