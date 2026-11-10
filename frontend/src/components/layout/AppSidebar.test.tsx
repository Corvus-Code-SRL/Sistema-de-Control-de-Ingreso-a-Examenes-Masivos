import { fireEvent, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { TooltipProvider } from '@/components/ui/tooltip'
import { roleLabel } from '@/lib/roleLabel'
import { openAllSections } from '@/test/sidebar'
import { AppSidebar } from './AppSidebar'

const session = vi.hoisted(() => ({
  role: 'Docente' as string | null,
  userId: 'user-1' as string | undefined,
  hasSession: true,
  pending: 0,
  logout: vi.fn()
}))

vi.mock('@/features/auth', async (importOriginal) => {
  const original = await importOriginal<typeof import('@/features/auth')>()

  return {
    ...original,
    useCurrentUser: () => ({
      user: {
        nombre: 'Prueba Usuario',
        iniciales: 'PU',
        area: areaForRoleOf(),
        rolEtiqueta: session.role ? roleLabel(session.role) : undefined
      },
      area: areaForRoleOf(),
      capabilities: session.hasSession ? original.capabilitiesOf(session.role) : []
    }),
    useAuth: () => ({
      estado: session.hasSession ? 'autenticado' : 'anonimo',
      usuario: session.userId ? { id_usuario: session.userId } : null,
      cerrarSesion: session.logout
    })
  }

  function areaForRoleOf() {
    return original.areaForRole(session.role)
  }
})

// El contador pide la petición solo si alguna entrada visible lo muestra: aquí se observa ese `enabled`.
const pendingIncidentsSpy = vi.hoisted(() => vi.fn())

vi.mock('@/features/incidents', () => ({
  usePendingIncidentsCount: (enabled: boolean) => {
    pendingIncidentsSpy(enabled)

    return enabled ? session.pending : 0
  }
}))

type RoleName = 'Administrador' | 'Docente' | 'Auxiliar' | 'Invitado'

function renderSidebar(
  role: RoleName,
  route: string,
  props: {
    onNavigate?: () => void
    onNavScroll?: (top: number) => void
    initialNavScroll?: number
  } = {}
) {
  session.role = role

  return render(
    <MemoryRouter initialEntries={[route]}>
      <TooltipProvider>
        <AppSidebar {...props} />
      </TooltipProvider>
    </MemoryRouter>
  )
}

const MAIN_NAV = 'Navegación principal'

function sectionButton(name: string) {
  return screen.getByRole('button', { name })
}

beforeEach(() => {
  window.localStorage.clear()
  session.role = 'Docente'
  session.userId = 'user-1'
  session.hasSession = true
  session.pending = 0
  session.logout.mockReset()
  pendingIncidentsSpy.mockClear()
  vi.restoreAllMocks()
})

describe('AppSidebar — estructura', () => {
  it('docente: dibuja las 5 secciones, solo con su etiqueta', () => {
    renderSidebar('Docente', '/mis-cursos')

    const nav = screen.getByRole('navigation', { name: MAIN_NAV })
    const headers = within(nav).getAllByRole('heading', { level: 3 })

    expect(headers.map((header) => header.textContent)).toEqual([
      'Grupos académicos y nóminas',
      'Preparación de exámenes',
      'Control de ingreso y examen en curso',
      'Central de riesgo',
      'Historial de exámenes y reportes'
    ])
  })

  it.each([
    ['Administrador', '/cuentas'],
    ['Docente', '/mis-cursos'],
    ['Auxiliar', '/mis-examenes']
  ] as const)(
    '%s: no hay rótulo «Secciones», botones de expandir o contraer, índices ni conteos',
    (role, route) => {
      renderSidebar(role, route)

      const nav = screen.getByRole('navigation', { name: MAIN_NAV })
      const headers = within(nav).getAllByRole('heading', { level: 3 })

      expect(headers.length).toBeGreaterThan(0)
      for (const header of headers) {
        expect(header.textContent).not.toMatch(/\d/)
      }

      expect(within(nav).queryByText('Secciones')).not.toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Expandir' })).not.toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Contraer' })).not.toBeInTheDocument()
    }
  )

  it('no muestra Inicio, códigos de historia ni avisos de estado', () => {
    renderSidebar('Docente', '/mis-cursos')

    const nav = screen.getByRole('navigation', { name: MAIN_NAV })

    expect(within(nav).queryByText('Inicio')).not.toBeInTheDocument()
    expect(within(nav).queryByText(/HU-?\d+/i)).not.toBeInTheDocument()
    expect(within(nav).queryByText(/sin ruta|actual$/i)).not.toBeInTheDocument()
  })

  it('administrador y auxiliar ven solo sus secciones', () => {
    const admin = renderSidebar('Administrador', '/cuentas')

    expect(within(admin.container).getAllByRole('heading', { level: 3 })).toHaveLength(4)
    admin.unmount()

    renderSidebar('Auxiliar', '/mis-examenes')

    const headings = screen.getAllByRole('heading', { level: 3 }).map((heading) => heading.textContent)

    expect(headings).toEqual(['Exámenes y grupos', 'Control de ingreso y reportes'])
    expect(screen.queryByText(/central de riesgo/i)).not.toBeInTheDocument()
  })

  it('sin sesión o con un rol desconocido el menú queda vacío, pero la cuenta se muestra', () => {
    session.hasSession = false
    renderSidebar('Docente', '/login')

    expect(screen.queryAllByRole('heading', { level: 3 })).toHaveLength(0)
    expect(screen.getByRole('link', { name: 'Iniciar sesión' })).toBeInTheDocument()
  })
})

describe('AppSidebar — desplazamiento', () => {
  const SCROLL_CLASSES = ['overflow-y-auto', 'overflow-auto', 'overflow-y-scroll', 'overflow-scroll']

  it.each([
    ['Administrador', '/cuentas'],
    ['Docente', '/mis-cursos'],
    ['Auxiliar', '/mis-examenes']
  ] as const)('%s: solo el nav se desplaza y no muestra la barra', (role, route) => {
    const { container } = renderSidebar(role, route)
    const nav = screen.getByRole('navigation', { name: MAIN_NAV })

    expect(nav).toHaveClass('scrollbar-hidden', 'overflow-y-auto')
    expect(nav).not.toHaveClass('overflow-hidden')

    const scrollers = [...container.querySelectorAll('*')].filter((element) =>
      SCROLL_CLASSES.some((name) => element.classList.contains(name))
    )

    expect(scrollers).toEqual([nav])
  })
})

describe('AppSidebar — desplazamiento recordado (panel móvil)', () => {
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

  /** Área del `<nav>` entre 0 y 500 px; la entrada activa, en la posición indicada. */
  function layoutWithActiveAt(top: number, bottom: number) {
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
      this: HTMLElement
    ) {
      if (this.tagName === 'NAV') return box(0, 500)
      if (this.getAttribute('aria-current') === 'page') return box(top, bottom)

      return box(0, 0)
    })
  }

  /** Fotogramas a mano: la prueba decide cuándo corre cada `requestAnimationFrame`. */
  function manualFrames() {
    const callbacks = new Map<number, FrameRequestCallback>()
    let next = 1

    vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
      callbacks.set(next, callback)

      return next++
    })
    const cancel = vi.spyOn(window, 'cancelAnimationFrame').mockImplementation((id) => {
      callbacks.delete(id)
    })

    return {
      cancel,
      get pending() {
        return callbacks.size
      },
      flush() {
        const queued = [...callbacks.values()]
        callbacks.clear()
        queued.forEach((callback) => callback(0))
      }
    }
  }

  function getNav() {
    return screen.getByRole('navigation', { name: MAIN_NAV })
  }

  it('restaura el desplazamiento inicial al montar', () => {
    renderSidebar('Docente', '/mis-cursos', { initialNavScroll: 90 })

    expect(getNav().scrollTop).toBe(90)
  })

  it('si la entrada activa ya se ve, conserva la posición y no desplaza nada', () => {
    const scrollIntoView = vi.spyOn(Element.prototype, 'scrollIntoView')
    layoutWithActiveAt(100, 140)

    renderSidebar('Docente', '/mis-cursos', { initialNavScroll: 90 })

    expect(getNav().scrollTop).toBe(90)
    expect(scrollIntoView).not.toHaveBeenCalled()
  })

  it('si la entrada activa queda fuera, la acerca con block «nearest» (también sin posición previa)', () => {
    const scrollIntoView = vi.spyOn(Element.prototype, 'scrollIntoView')
    layoutWithActiveAt(800, 840)

    renderSidebar('Docente', '/mis-cursos', { initialNavScroll: 0 })

    expect(scrollIntoView).toHaveBeenCalledTimes(1)
    expect(scrollIntoView).toHaveBeenCalledWith({ block: 'nearest' })
    expect(scrollIntoView.mock.contexts[0]).toBe(
      within(getNav()).getByRole('link', { current: 'page' })
    )
  })

  it('sin las props nuevas (escritorio) no restaura, no acerca nada ni avisa', () => {
    const scrollIntoView = vi.spyOn(Element.prototype, 'scrollIntoView')
    const frames = manualFrames()
    layoutWithActiveAt(800, 840)

    renderSidebar('Docente', '/mis-cursos')
    fireEvent.scroll(getNav())

    expect(getNav().scrollTop).toBe(0)
    expect(scrollIntoView).not.toHaveBeenCalled()
    expect(frames.pending).toBe(0)
  })

  it('avisa como mucho una vez por fotograma y con el último valor', () => {
    const frames = manualFrames()
    const onNavScroll = vi.fn()
    renderSidebar('Docente', '/mis-cursos', { onNavScroll, initialNavScroll: 0 })
    const nav = getNav()

    nav.scrollTop = 30
    fireEvent.scroll(nav)
    nav.scrollTop = 75
    fireEvent.scroll(nav)

    expect(onNavScroll).not.toHaveBeenCalled()
    expect(frames.pending).toBe(1)

    frames.flush()

    expect(onNavScroll).toHaveBeenCalledTimes(1)
    expect(onNavScroll).toHaveBeenCalledWith(75)

    nav.scrollTop = 120
    fireEvent.scroll(nav)
    frames.flush()

    expect(onNavScroll).toHaveBeenLastCalledWith(120)
  })

  it('al desmontar entrega el último valor pendiente y cancela el fotograma', () => {
    const frames = manualFrames()
    const onNavScroll = vi.fn()
    const view = renderSidebar('Docente', '/mis-cursos', { onNavScroll, initialNavScroll: 0 })
    const nav = getNav()

    nav.scrollTop = 64
    fireEvent.scroll(nav)
    view.unmount()

    expect(frames.cancel).toHaveBeenCalledTimes(1)
    expect(onNavScroll).toHaveBeenCalledTimes(1)
    expect(onNavScroll).toHaveBeenCalledWith(64)
  })

  it('al desmontar sin desplazamiento pendiente no avisa', () => {
    manualFrames()
    const onNavScroll = vi.fn()
    const view = renderSidebar('Docente', '/mis-cursos', { onNavScroll, initialNavScroll: 0 })

    view.unmount()

    expect(onNavScroll).not.toHaveBeenCalled()
  })
})

describe('AppSidebar — secciones colapsables', () => {
  it('abre solo la sección de la ruta actual y expone aria-expanded y aria-controls', () => {
    renderSidebar('Docente', '/examenes/programados')

    const open = sectionButton('Preparación de exámenes')
    const closed = sectionButton('Grupos académicos y nóminas')

    expect(open).toHaveAttribute('aria-expanded', 'true')
    expect(closed).toHaveAttribute('aria-expanded', 'false')

    const panelId = open.getAttribute('aria-controls') as string

    expect(document.getElementById(panelId)).toBeVisible()
    expect(document.getElementById(closed.getAttribute('aria-controls') as string)).not.toBeVisible()
    expect(screen.getByRole('link', { name: 'Programados' })).toBeVisible()
    expect(screen.queryByRole('link', { name: 'Materias' })).not.toBeInTheDocument()
  })

  it('se abre y cierra con clic y con el teclado', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/examenes/programados')

    const toggle = sectionButton('Grupos académicos y nóminas')

    await user.click(toggle)
    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByRole('link', { name: 'Materias' })).toBeInTheDocument()

    toggle.focus()
    await user.keyboard('{Enter}')
    expect(toggle).toHaveAttribute('aria-expanded', 'false')

    await user.keyboard(' ')
    expect(toggle).toHaveAttribute('aria-expanded', 'true')
  })

  it('cada sección se abre y se cierra por separado, sin afectar a las demás', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/mis-cursos')

    const other = sectionButton('Central de riesgo')
    const active = sectionButton('Grupos académicos y nóminas')

    await user.click(other)
    expect(other).toHaveAttribute('aria-expanded', 'true')
    expect(active).toHaveAttribute('aria-expanded', 'true')

    await user.click(other)
    expect(other).toHaveAttribute('aria-expanded', 'false')
    expect(active).toHaveAttribute('aria-expanded', 'true')
  })

  it('recuerda por usuario qué secciones dejó abiertas, aunque se vuelva a montar el menú', async () => {
    const user = userEvent.setup()
    const first = renderSidebar('Docente', '/mis-cursos')

    await user.click(sectionButton('Central de riesgo'))
    first.unmount()

    renderSidebar('Docente', '/mis-cursos')

    expect(sectionButton('Central de riesgo')).toHaveAttribute('aria-expanded', 'true')
    expect(sectionButton('Preparación de exámenes')).toHaveAttribute('aria-expanded', 'false')
  })

  it('otro usuario no hereda lo que dejó abierto el anterior', async () => {
    const user = userEvent.setup()
    const first = renderSidebar('Docente', '/mis-cursos')

    await user.click(sectionButton('Central de riesgo'))
    first.unmount()

    session.userId = 'user-2'
    renderSidebar('Docente', '/mis-cursos')

    expect(sectionButton('Central de riesgo')).toHaveAttribute('aria-expanded', 'false')
  })

  it('funciona aunque el navegador no deje usar localStorage', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('bloqueado')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('bloqueado')
    })
    const user = userEvent.setup()
    renderSidebar('Docente', '/mis-cursos')

    await user.click(sectionButton('Central de riesgo'))

    expect(sectionButton('Central de riesgo')).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByRole('link', { name: 'Revisar reportes de incidencias' })).toBeInTheDocument()
  })

  it('dos menús montados a la vez (escritorio y panel) no repiten ids', () => {
    session.role = 'Docente'

    render(
      <MemoryRouter initialEntries={['/mis-cursos']}>
        <TooltipProvider>
          <AppSidebar />
          <AppSidebar />
        </TooltipProvider>
      </MemoryRouter>
    )

    const ids = screen
      .getAllByRole('button', { name: 'Grupos académicos y nóminas' })
      .map((button) => button.getAttribute('aria-controls'))

    expect(new Set(ids).size).toBe(2)
  })
})

describe('AppSidebar — rol del usuario', () => {
  it('un docente invitado figura como «Docente invitado»', () => {
    renderSidebar('Invitado', '/materias')

    expect(screen.getByText('Docente invitado')).toBeInTheDocument()
  })

  it.each(['Administrador', 'Docente', 'Auxiliar'] as const)('%s figura con su nombre', (role) => {
    renderSidebar(role, '/materias')

    expect(within(screen.getByRole('region', { name: 'Cuenta' })).getByText(role)).toBeInTheDocument()
  })
})

describe('AppSidebar — entradas y estado activo', () => {
  it('docente: las entradas conectadas llevan a su ruta', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/mis-cursos')
    await openAllSections(user)

    const hrefs = screen
      .getAllByRole('link')
      .filter((link) => link.closest('nav'))
      .map((link) => [link.textContent, link.getAttribute('href')])

    expect(hrefs).toEqual([
      ['Materias', '/materias'],
      ['Mis grupos', '/mis-cursos'],
      ['Mis auxiliares', '/mis-auxiliares'],
      ['Programados', '/examenes/programados'],
      ['Registrar examen', '/examenes/nuevo'],
      ['Control de ingreso', '/control-ingreso'],
      ['En curso', '/examenes/en-curso'],
      ['Registrar estudiante con retraso', '/retrasos'],
      ['Registrar situación excepcional', '/situacion-excepcional'],
      ['Reportar incidencia', '/reportar-incidencia'],
      ['Revisar reportes de incidencias', '/incidencias'],
      ['Central de riesgo', '/central-riesgo'],
      ['Historial de mis exámenes', '/historial'],
      ['Generar reporte de examen', '/examenes/reportes'],
    ])
  })

  it.each(['Docente', 'Invitado', 'Auxiliar'] as const)(
    '%s ve siempre «Registrar situación excepcional», sin depender de un examen activo',
    async (role) => {
      const user = userEvent.setup()
      renderSidebar(role, '/materias')
      await openAllSections(user)

      expect(screen.getByRole('link', { name: 'Registrar situación excepcional' }))
        .toHaveAttribute('href', '/situacion-excepcional')
      expect(screen.queryByText('Registrar estudiante excepcional')).not.toBeInTheDocument()
    }
  )

  it('el Administrador no ve «Registrar situación excepcional»', async () => {
    const user = userEvent.setup()
    renderSidebar('Administrador', '/cuentas/roles')
    await openAllSections(user)

    expect(screen.queryByText('Registrar situación excepcional')).not.toBeInTheDocument()
    expect(screen.queryByText('Registrar estudiante excepcional')).not.toBeInTheDocument()
  })

  it('una subruta resalta su entrada y abre su sección: /grupos/:id/editar → Mis grupos', () => {
    renderSidebar('Docente', '/grupos/5/editar')

    expect(screen.getByRole('link', { name: 'Mis grupos' })).toHaveAttribute('aria-current', 'page')
    expect(sectionButton('Grupos académicos y nóminas')).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getAllByRole('link').filter((link) => link.getAttribute('aria-current'))).toHaveLength(1)
  })

  it('/examenes/:id/control-ingreso resalta Control de ingreso y no Programados', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/examenes/12/control-ingreso')
    await openAllSections(user)

    expect(screen.getByRole('link', { name: 'Control de ingreso' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Programados' })).not.toHaveAttribute('aria-current')
  })

  it('/examenes/:id/retrasos resalta Registrar estudiante con retraso y no Programados', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/examenes/12/retrasos')
    await openAllSections(user)

    expect(screen.getByRole('link', { name: 'Registrar estudiante con retraso' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Programados' })).not.toHaveAttribute('aria-current')
  })

    it('/examenes/:id/reporte-final resalta Generar reporte de examen y no Programados', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/examenes/12/reporte-final')
    await openAllSections(user)

    expect(screen.getByRole('link', { name: 'Generar reporte de examen' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Programados' })).not.toHaveAttribute('aria-current')
  })

  it('/examenes/:id/reporte-final deja abierta la sección Historial de exámenes y reportes', () => {
    renderSidebar('Docente', '/examenes/12/reporte-final')

    expect(screen.getByRole('button', { name: /historial de exámenes y reportes/i })).toHaveAttribute('aria-expanded', 'true')
  })

  it('la Central de riesgo sigue resaltada con el detalle abierto y la query', () => {
    renderSidebar('Administrador', '/central-riesgo/9941?tipo=3')

    expect(screen.getByRole('link', { name: 'Consultar central' })).toHaveAttribute('aria-current', 'page')
  })

  it('administrador: /cuentas/:id resalta Modificar rol', () => {
    renderSidebar('Administrador', '/cuentas/00000000-0000-4000-8000-000000000011')

    expect(screen.getByRole('link', { name: 'Modificar rol' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Registrar cuenta nueva' })).not.toHaveAttribute('aria-current')
  })

  it('administrador: cada vista de cuentas resalta su propia entrada', () => {
    const nueva = renderSidebar('Administrador', '/cuentas/nueva')

    expect(screen.getByRole('link', { name: 'Registrar cuenta nueva' })).toHaveAttribute('aria-current', 'page')
    nueva.unmount()

    renderSidebar('Administrador', '/cuentas/roles')

    expect(screen.getByRole('link', { name: 'Modificar rol' })).toHaveAttribute('aria-current', 'page')
  })

  it('invitado: no ve «Mis auxiliares» ni la entrada de la Central de riesgo', async () => {
    const user = userEvent.setup()

    renderSidebar('Invitado', '/mis-cursos')
    await openAllSections(user)

    const nav = screen.getByRole('navigation', { name: MAIN_NAV })

    expect(within(nav).queryByText('Mis auxiliares')).not.toBeInTheDocument()
    expect(within(nav).queryByRole('link', { name: 'Central de riesgo' })).not.toBeInTheDocument()
    expect(within(nav).getByRole('link', { name: 'Revisar reportes de incidencias' })).toBeInTheDocument()
    expect(within(nav).getByRole('link', { name: 'Mis grupos' })).toBeInTheDocument()
  })

  it('docente: conserva «Mis auxiliares» y la entrada de la Central de riesgo', async () => {
    const user = userEvent.setup()

    renderSidebar('Docente', '/mis-cursos')
    await openAllSections(user)

    const nav = screen.getByRole('navigation', { name: MAIN_NAV })

    expect(within(nav).getByRole('link', { name: 'Mis auxiliares' })).toBeInTheDocument()
    expect(within(nav).getByRole('link', { name: 'Central de riesgo' })).toBeInTheDocument()
  })

  it('una entrada sin destino no es un enlace: es un botón deshabilitado con aviso', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/examenes/programados')

    expect(screen.queryByRole('link', { name: 'Editar examen' })).not.toBeInTheDocument()

    const pending = screen.getByRole('button', { name: 'Editar examen' })

    expect(pending).toHaveAttribute('aria-disabled', 'true')
    expect(pending).not.toHaveAttribute('href')

    await user.hover(pending)
    expect(await screen.findByRole('tooltip')).toHaveTextContent('Disponible próximamente')
  })

  it('el aviso también llega por teclado', async () => {
    const user = userEvent.setup()
    renderSidebar('Docente', '/examenes/programados')

    screen.getByRole('button', { name: 'Editar examen' }).focus()

    expect(await screen.findByRole('tooltip')).toHaveTextContent('Disponible próximamente')
    await user.keyboard('{Escape}')
  })

  it('al navegar con una entrada se avisa para cerrar el panel móvil', async () => {
    const user = userEvent.setup()
    const onNavigate = vi.fn()
    renderSidebar('Docente', '/examenes/programados', { onNavigate })

    await user.click(screen.getByRole('link', { name: 'Registrar examen' }))

    expect(onNavigate).toHaveBeenCalledTimes(1)
  })

  it('una entrada deshabilitada no cierra el panel ni navega', async () => {
    const user = userEvent.setup()
    const onNavigate = vi.fn()
    renderSidebar('Docente', '/examenes/programados', { onNavigate })

    await user.click(screen.getByRole('button', { name: 'Editar examen' }))

    expect(onNavigate).not.toHaveBeenCalled()
  })
})

describe('AppSidebar — contador de incidencias', () => {
  it('muestra los pendientes junto a «Revisar reportes de incidencias»', () => {
    session.pending = 7
    renderSidebar('Docente', '/incidencias')

    expect(screen.getByRole('link', { name: /Revisar reportes de incidencias/ })).toHaveTextContent('7')
    expect(pendingIncidentsSpy).toHaveBeenCalledWith(true)
  })

  it('sin pendientes (o sin meta en la respuesta) no dibuja contador', () => {
    session.pending = 0
    renderSidebar('Docente', '/incidencias')

    const link = screen.getByRole('link', { name: 'Revisar reportes de incidencias' })

    expect(link.textContent).toBe('Revisar reportes de incidencias')
  })

  it('no se pide a quien no ve esa entrada', () => {
    renderSidebar('Administrador', '/cuentas')

    expect(pendingIncidentsSpy).toHaveBeenCalledWith(false)
    expect(pendingIncidentsSpy).not.toHaveBeenCalledWith(true)
  })
})

describe('AppSidebar — cuenta', () => {
  it('muestra nombre, rol y cierra la sesión', async () => {
    const user = userEvent.setup()
    const onNavigate = vi.fn()
    renderSidebar('Docente', '/mis-cursos', { onNavigate })

    const account = screen.getByRole('region', { name: 'Cuenta' })

    expect(within(account).getByText('Prueba Usuario')).toBeInTheDocument()
    expect(within(account).getByText('Docente')).toBeInTheDocument()

    await user.click(within(account).getByRole('button', { name: 'Cerrar sesión' }))

    expect(session.logout).toHaveBeenCalledTimes(1)
    expect(onNavigate).toHaveBeenCalledTimes(1)
  })

  it('el rol del bloque de cuenta sale del área del usuario', () => {
    renderSidebar('Auxiliar', '/mis-examenes')

    expect(within(screen.getByRole('region', { name: 'Cuenta' })).getByText('Auxiliar')).toBeInTheDocument()
  })
})
describe('AppSidebar — Reportar incidencia (HU-042)', () => {
  it.each(['Docente', 'Invitado', 'Auxiliar'] as const)(
    '%s ve siempre «Reportar incidencia», sin depender de un examen activo',
    async (role) => {
      const user = userEvent.setup()
      renderSidebar(role, '/materias')
      await openAllSections(user)

      expect(screen.getByRole('link', { name: 'Reportar incidencia' }))
        .toHaveAttribute('href', '/reportar-incidencia')
    }
  )

  it('el Administrador no ve «Reportar incidencia»', async () => {
    const user = userEvent.setup()
    renderSidebar('Administrador', '/cuentas/roles')
    await openAllSections(user)

    expect(screen.queryByText('Reportar incidencia')).not.toBeInTheDocument()
  })
})
