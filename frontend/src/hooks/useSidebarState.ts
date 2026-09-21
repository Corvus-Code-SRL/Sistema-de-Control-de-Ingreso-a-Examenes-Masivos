import { useCallback, useState, useSyncExternalStore } from 'react'

export type Breakpoint = 'mobile' | 'tablet' | 'desktop'

const STORAGE_KEY = 'sciem.sidebar.collapsed'
const MOBILE_QUERY = '(max-width: 767px)'
const DESKTOP_QUERY = '(min-width: 1024px)'

function subscribe(callback: () => void) {
  const queries = [window.matchMedia(MOBILE_QUERY), window.matchMedia(DESKTOP_QUERY)]
  queries.forEach((q) => q.addEventListener('change', callback))
  return () => queries.forEach((q) => q.removeEventListener('change', callback))
}

function getSnapshot(): Breakpoint {
  if (window.matchMedia(MOBILE_QUERY).matches) return 'mobile'
  return window.matchMedia(DESKTOP_QUERY).matches ? 'desktop' : 'tablet'
}

export function useBreakpoint(): Breakpoint {
  return useSyncExternalStore(subscribe, getSnapshot, () => 'desktop')
}

function readStored(): boolean {
  try {
    return localStorage.getItem(STORAGE_KEY) === 'true'
  } catch {
    return false
  }
}

function writeStored(value: boolean) {
  try {
    localStorage.setItem(STORAGE_KEY, String(value))
  } catch {
    // localStorage no disponible: el estado solo vive en memoria.
  }
}

// Desktop recuerda el estado en localStorage; tablet arranca colapsado y no lo persiste.
export function useSidebarState() {
  const breakpoint = useBreakpoint()
  const [desktopCollapsed, setDesktopCollapsed] = useState(readStored)
  const [tabletCollapsed, setTabletCollapsed] = useState(true)

  const collapsed =
    breakpoint === 'mobile' ? false : breakpoint === 'tablet' ? tabletCollapsed : desktopCollapsed

  const toggle = useCallback(() => {
    if (breakpoint === 'tablet') {
      setTabletCollapsed((value) => !value)
      return
    }
    setDesktopCollapsed((value) => {
      writeStored(!value)
      return !value
    })
  }, [breakpoint])

  return { breakpoint, collapsed, toggle }
}
