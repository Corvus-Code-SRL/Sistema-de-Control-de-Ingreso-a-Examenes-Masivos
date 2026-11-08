import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { TooltipProvider } from '@/components/ui/tooltip'
import { AuthProvider } from '@/features/auth'
import { TOKEN_STORAGE_KEY, accounts, meBody, type AccountKey } from '@/test/authFixtures'
import { mockApiWith } from '@/test/http'
import { renderApp } from '@/test/renderApp'
import { openAllSections } from '@/test/sidebar'
import { AppShell } from './AppShell'

const MAIN_NAV = 'Navegación principal'
const LAYOUT_KEY_PREFIX = 'sciem.sidebar-layout.v1.'

function stubBackend(session: AccountKey | null) {
  if (session) window.localStorage.setItem(TOKEN_STORAGE_KEY, 'tok')

  return mockApiWith(({ url }) => {
    if (url.endsWith('/auth/yo')) {
      return session
        ? { body: meBody(session) }
        : { status: 401, body: { message: 'Unauthenticated.' } }
    }

    if (url.includes('/historial/examenes')) {
      return { body: { data: { examenes: [], materias: [], pagina: 1, paginas: 1, total: 0 } } }
    }

    return { body: { data: [], meta: { id_periodo_activo: 1, total: 0, total_mios: 0 } } }
  })
}

function layoutKey(account: AccountKey) {
  return `${LAYOUT_KEY_PREFIX}${accounts[account].user.id_usuario}`
}

function scrollSpy() {
  return vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined)
}

beforeEach(() => {
  window.localStorage.clear()
})

afterEach(() => {
  window.localStorage.clear()
})

describe('AppLayout — marco persistente', () => {
  it('no vuelve a montar el menú al navegar y conserva su desplazamiento y sus secciones', async () => {
    stubBackend('docente')
    const user = userEvent.setup()
    const app = renderApp('/historial')

    const nav = await screen.findByRole('navigation', { name: MAIN_NAV })
    await openAllSections(user, nav)
    nav.scrollTop = 120

    const banner = screen.getByRole('banner')
    expect(within(banner).getAllByText('Historial').length).toBeGreaterThan(0)

    await user.click(within(nav).getByRole('link', { name: 'Revisar reportes de incidencias' }))
    await waitFor(() => expect(app.pathname).toBe('/incidencias'))

    // Es el mismo nodo del DOM: el menú no se desmontó y su desplazamiento sigue donde estaba.
    const afterNav = screen.getByRole('navigation', { name: MAIN_NAV })
    expect(afterNav).toBe(nav)
    expect(nav.scrollTop).toBe(120)
    expect(within(nav).getByRole('link', { name: 'Revisar reportes de incidencias' })).toHaveAttribute(
      'aria-current',
      'page'
    )

    // Solo cambió el contenido y la cabecera que proyecta la pantalla.
    expect(await screen.findByRole('heading', { name: 'Incidencias' })).toBeInTheDocument()
    expect(within(screen.getByRole('banner')).getAllByText('Incidencias').length).toBeGreaterThan(0)
    expect(within(screen.getByRole('banner')).queryByText('Historial')).not.toBeInTheDocument()

    // Las secciones abiertas siguen abiertas.
    const toggles = within(nav)
      .getAllByRole('heading', { level: 3 })
      .map((heading) => within(heading).getByRole('button'))
    expect(toggles.every((toggle) => toggle.getAttribute('aria-expanded') === 'true')).toBe(true)
  })

  it('hay un solo marco, el del layout, y ninguno aislado', async () => {
    stubBackend('docente')
    renderApp('/historial')

    await screen.findByRole('navigation', { name: MAIN_NAV })

    expect(document.querySelectorAll('[data-shell="layout"]')).toHaveLength(1)
    expect(document.querySelectorAll('[data-shell="standalone"]')).toHaveLength(0)
  })

  it('al navegar lleva el contenido arriba y el foco a <main>, pero no en el primer montaje', async () => {
    const scrollTo = scrollSpy()
    stubBackend('docente')
    const user = userEvent.setup()
    const app = renderApp('/historial')

    const nav = await screen.findByRole('navigation', { name: MAIN_NAV })
    await openAllSections(user, nav)

    expect(scrollTo).not.toHaveBeenCalled()
    expect(screen.getByRole('main')).not.toHaveFocus()

    await user.click(within(nav).getByRole('link', { name: 'Revisar reportes de incidencias' }))
    await waitFor(() => expect(app.pathname).toBe('/incidencias'))

    expect(scrollTo).toHaveBeenCalledTimes(1)
    expect(scrollTo).toHaveBeenCalledWith({ top: 0 })
    expect(screen.getByRole('main')).toHaveFocus()
    expect(screen.getByRole('main')).toHaveClass('outline-none')
  })

  it('el aviso de falta de permiso se dibuja dentro del mismo marco', async () => {
    stubBackend('auxiliar')
    renderApp('/reportes/intentos')

    // Aparece en la cabecera móvil y en el aviso: ambos viven en el mismo marco.
    expect((await screen.findAllByText('Sin permiso')).length).toBeGreaterThan(0)
    expect(screen.getAllByRole('navigation', { name: MAIN_NAV })).toHaveLength(1)
    expect(document.querySelectorAll('[data-shell="layout"]')).toHaveLength(1)
    expect(document.querySelectorAll('[data-shell="standalone"]')).toHaveLength(0)
  })

  it('sin sesión no pinta el marco: redirige al login sin mostrar el menú', async () => {
    stubBackend(null)
    const app = renderApp('/materias')

    await waitFor(() => expect(app.pathname).toBe('/login'))

    expect(screen.queryByRole('navigation', { name: MAIN_NAV })).not.toBeInTheDocument()
    expect(document.querySelectorAll('[data-shell]')).toHaveLength(0)
  })
})

describe('AppLayout — ocultar y mostrar el menú', () => {
  async function renderDocente() {
    stubBackend('docente')
    const view = renderApp('/historial')
    await screen.findByRole('navigation', { name: MAIN_NAV })

    return view
  }

  function aside() {
    const element = document.querySelector('aside')
    if (element === null) throw new Error('No hay aside')

    return element
  }

  it('el botón del menú lo oculta y el de la barra superior lo vuelve a mostrar', async () => {
    const user = userEvent.setup()
    await renderDocente()

    expect(aside()).toHaveAttribute('data-collapsed', 'false')
    expect(screen.queryByRole('button', { name: 'Mostrar menú' })).not.toBeInTheDocument()

    const hide = screen.getByRole('button', { name: 'Ocultar menú' })
    expect(hide).toHaveAttribute('aria-expanded', 'true')
    expect(hide).toHaveAttribute('aria-controls', aside().id)

    await user.click(hide)

    expect(aside()).toHaveAttribute('data-collapsed', 'true')
    // El menú sigue montado (conserva su estado), solo oculto.
    expect(screen.getByRole('navigation', { name: MAIN_NAV })).toBeInTheDocument()
    expect(screen.queryByRole('separator', { name: 'Cambiar ancho del menú' })).not.toBeInTheDocument()

    const show = screen.getByRole('button', { name: 'Mostrar menú' })
    expect(show).toHaveAttribute('aria-expanded', 'false')
    expect(show).toHaveAttribute('aria-controls', aside().id)
    expect(within(screen.getByRole('banner')).getByRole('button', { name: 'Mostrar menú' })).toBe(show)

    await user.click(show)

    expect(aside()).toHaveAttribute('data-collapsed', 'false')
    expect(screen.getByRole('separator', { name: 'Cambiar ancho del menú' })).toBeInTheDocument()
  })

  it('Ctrl+B y Cmd+B alternan el menú', async () => {
    const user = userEvent.setup()
    await renderDocente()

    await user.keyboard('{Control>}b{/Control}')
    expect(aside()).toHaveAttribute('data-collapsed', 'true')

    await user.keyboard('{Meta>}b{/Meta}')
    expect(aside()).toHaveAttribute('data-collapsed', 'false')
  })

  it('Ctrl+B se ignora mientras se escribe en un campo', async () => {
    const user = userEvent.setup()
    await renderDocente()

    const field = document.body.appendChild(document.createElement('input'))
    field.focus()

    await user.keyboard('{Control>}b{/Control}')

    expect(aside()).toHaveAttribute('data-collapsed', 'false')
    field.remove()
  })

  it('recuerda el estado por usuario entre montajes', async () => {
    const user = userEvent.setup()
    const first = await renderDocente()

    await user.click(screen.getByRole('button', { name: 'Ocultar menú' }))
    expect(JSON.parse(window.localStorage.getItem(layoutKey('docente')) ?? 'null')).toEqual({
      collapsed: true,
      width: 272
    })

    first.unmount()
    stubBackend('docente')
    renderApp('/historial')
    await screen.findByRole('navigation', { name: MAIN_NAV })

    expect(aside()).toHaveAttribute('data-collapsed', 'true')
    expect(screen.getByRole('button', { name: 'Mostrar menú' })).toBeInTheDocument()
  })

  it('funciona aunque localStorage no deje guardar ni leer el estado del menú', async () => {
    const user = userEvent.setup()
    const original = Storage.prototype
    const getItem = original.getItem
    const setItem = original.setItem

    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(function (this: Storage, key: string) {
      if (key.startsWith(LAYOUT_KEY_PREFIX)) throw new Error('bloqueado')

      return getItem.call(this, key)
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(function (
      this: Storage,
      key: string,
      value: string
    ) {
      if (key.startsWith(LAYOUT_KEY_PREFIX)) throw new Error('cuota llena')

      setItem.call(this, key, value)
    })

    await renderDocente()
    await user.click(screen.getByRole('button', { name: 'Ocultar menú' }))

    expect(aside()).toHaveAttribute('data-collapsed', 'true')
  })

  it('el panel móvil no ofrece ocultar el menú', async () => {
    const user = userEvent.setup()
    await renderDocente()

    await user.click(screen.getByRole('button', { name: 'Abrir menú' }))

    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).queryByRole('button', { name: 'Ocultar menú' })).not.toBeInTheDocument()
    expect(within(dialog).getByRole('navigation', { name: MAIN_NAV })).toBeInTheDocument()
  })
})

describe('AppLayout — desplazamiento del menú móvil', () => {
  const INCIDENTS_LINK = 'Revisar reportes de incidencias'

  afterEach(() => {
    vi.restoreAllMocks()
  })

  async function renderDocente() {
    stubBackend('docente')
    const view = renderApp('/historial')
    const desktopNav = await screen.findByRole('navigation', { name: MAIN_NAV })

    return { view, desktopNav }
  }

  /** Abre el panel y devuelve el `<nav>` que contiene (distinto del de escritorio). */
  async function openMenu(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('button', { name: 'Abrir menú' }))
    const dialog = await screen.findByRole('dialog')

    return within(dialog).getByRole('navigation', { name: MAIN_NAV })
  }

  async function closeMenu(user: ReturnType<typeof userEvent.setup>) {
    await user.keyboard('{Escape}')
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  }

  /** Deja pasar un fotograma: el menú avisa de su desplazamiento como mucho una vez por fotograma. */
  function nextFrame() {
    return new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
  }

  async function scrollTo(nav: HTMLElement, top: number) {
    nav.scrollTop = top
    fireEvent.scroll(nav)
    await nextFrame()
  }

  function box(top: number, bottom: number): DOMRect {
    return {
      top,
      bottom,
      left: 0,
      right: 0,
      width: 0,
      height: bottom - top,
      x: 0,
      y: top,
      toJSON: () => ({})
    }
  }

  it('al cerrar y volver a abrir el panel, el menú queda donde estaba', async () => {
    const scrollIntoView = vi.spyOn(Element.prototype, 'scrollIntoView')
    const user = userEvent.setup()
    await renderDocente()

    const first = await openMenu(user)
    await scrollTo(first, 150)
    await closeMenu(user)

    const second = await openMenu(user)

    // Es un nodo nuevo (el panel se desmonta al cerrar), pero nace en la misma posición.
    expect(second).not.toBe(first)
    expect(second.scrollTop).toBe(150)
    expect(scrollIntoView).not.toHaveBeenCalled()
  })

  it('tras elegir una entrada (navega y cierra), al reabrir sigue en la misma posición', async () => {
    const user = userEvent.setup()
    const { view } = await renderDocente()

    const first = await openMenu(user)
    await openAllSections(user, first)
    await scrollTo(first, 150)

    await user.click(within(first).getByRole('link', { name: INCIDENTS_LINK }))
    await waitFor(() => expect(view.pathname).toBe('/incidencias'))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())

    const second = await openMenu(user)

    expect(second.scrollTop).toBe(150)
    expect(within(second).getByRole('link', { name: INCIDENTS_LINK })).toHaveAttribute(
      'aria-current',
      'page'
    )
  })

  it('un desplazamiento hecho justo antes de cerrar tampoco se pierde', async () => {
    const user = userEvent.setup()
    await renderDocente()

    const first = await openMenu(user)
    // Sin esperar el fotograma: el aviso queda pendiente cuando el panel se desmonta.
    first.scrollTop = 200
    fireEvent.scroll(first)
    await closeMenu(user)

    expect((await openMenu(user)).scrollTop).toBe(200)
  })

  it('en la primera apertura trae la entrada activa a la vista si queda fuera', async () => {
    const scrollIntoView = vi.spyOn(Element.prototype, 'scrollIntoView')
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
      this: HTMLElement
    ) {
      if (this.tagName === 'NAV') return box(0, 500)
      if (this.getAttribute('aria-current') === 'page') return box(900, 940)

      return box(0, 0)
    })
    const user = userEvent.setup()
    await renderDocente()

    // El menú de escritorio (montado desde el inicio) no se ajusta: no recibe la restauración.
    expect(scrollIntoView).not.toHaveBeenCalled()

    const nav = await openMenu(user)

    expect(scrollIntoView).toHaveBeenCalledTimes(1)
    expect(scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' })
    expect(scrollIntoView.mock.contexts[0]).toBe(within(nav).getByRole('link', { current: 'page' }))
  })

  it('el menú de escritorio y el del panel llevan desplazamientos independientes', async () => {
    const user = userEvent.setup()
    const { desktopNav } = await renderDocente()
    await openAllSections(user, desktopNav)
    desktopNav.scrollTop = 80

    const mobile = await openMenu(user)

    // El panel no hereda el desplazamiento de escritorio.
    expect(mobile.scrollTop).toBe(0)

    await scrollTo(mobile, 150)
    await closeMenu(user)

    expect(desktopNav.scrollTop).toBe(80)
    expect((await openMenu(user)).scrollTop).toBe(150)
  })
})

describe('AppLayout — ancho del menú', () => {
  async function renderDocente() {
    stubBackend('docente')
    renderApp('/historial')

    return screen.findByRole('separator', { name: 'Cambiar ancho del menú' })
  }

  function storedWidth() {
    const raw = window.localStorage.getItem(layoutKey('docente'))

    return raw === null ? null : (JSON.parse(raw) as { width: number }).width
  }

  it('las flechas cambian el ancho de 16 en 16 px y se recuerda', async () => {
    const user = userEvent.setup()
    const handle = await renderDocente()

    expect(handle).toHaveAttribute('aria-valuenow', '272')

    handle.focus()
    await user.keyboard('{ArrowRight}{ArrowRight}')
    expect(handle).toHaveAttribute('aria-valuenow', '304')
    expect(document.querySelector('aside')?.style.getPropertyValue('--sidebar-width')).toBe('304px')

    await user.keyboard('{ArrowLeft}')
    expect(handle).toHaveAttribute('aria-valuenow', '288')
    await waitFor(() => expect(storedWidth()).toBe(288))
  })

  it('no pasa de 400 px ni baja de 224 px', async () => {
    const user = userEvent.setup()
    const handle = await renderDocente()

    handle.focus()
    await user.keyboard('{ArrowRight>12/}')
    expect(handle).toHaveAttribute('aria-valuenow', '400')

    await user.keyboard('{ArrowLeft>20/}')
    expect(handle).toHaveAttribute('aria-valuenow', '224')
  })

  it('el doble clic restablece el ancho por defecto y lo recuerda', async () => {
    const user = userEvent.setup()
    const handle = await renderDocente()

    handle.focus()
    await user.keyboard('{ArrowRight}{ArrowRight}{ArrowRight}')
    expect(handle).toHaveAttribute('aria-valuenow', '320')

    // user-event no emite `dblclick` si el puntero cancela el mousedown; el navegador sí lo emite.
    fireEvent.doubleClick(handle)

    expect(handle).toHaveAttribute('aria-valuenow', '272')
    await waitFor(() => expect(storedWidth()).toBe(272))
  })

  it('el ancho guardado se aplica al volver a abrir la aplicación', async () => {
    window.localStorage.setItem(
      layoutKey('docente'),
      JSON.stringify({ collapsed: false, width: 360 })
    )

    const handle = await renderDocente()

    expect(handle).toHaveAttribute('aria-valuenow', '360')
    expect(document.querySelector('aside')?.style.getPropertyValue('--sidebar-width')).toBe('360px')
  })
})

describe('AppShell — modo aislado', () => {
  it('sin AppLayout pinta su propio marco con menú y cabecera', async () => {
    stubBackend('docente')

    render(
      <MemoryRouter initialEntries={['/x']}>
        <AuthProvider>
          <TooltipProvider>
          <Routes>
            <Route
              path="/x"
              element={
                <AppShell mobileTitle="Prueba" breadcrumbs={[{ label: 'Prueba' }]}>
                  <p>Contenido aislado</p>
                </AppShell>
              }
            />
          </Routes>
          </TooltipProvider>
        </AuthProvider>
      </MemoryRouter>
    )

    expect(await screen.findByText('Contenido aislado')).toBeInTheDocument()
    expect(document.querySelectorAll('[data-shell="standalone"]')).toHaveLength(1)
  })
})
