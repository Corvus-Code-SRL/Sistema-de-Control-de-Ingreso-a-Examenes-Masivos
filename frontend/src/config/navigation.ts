// Fuente única de verdad de la navegación: el sidebar y las rutas se generan desde este archivo.
//
// Cómo conectar una página:
//   1. Expórtala desde el index.ts de tu feature (ej. `export { default as XPage } from './pages/XPage'`).
//   2. Impórtala aquí desde '@/features/<feature>' y asígnala en el campo `page` de su ítem.
// Los ítems sin `page` muestran una página provisional con su título.
//
// Para agregar otro rol (ej. Administrador): crea su lista de ítems, agrégala a `navByRole`
// y agrega su clave a `NavRole`. No hace falta tocar los componentes de layout.
import type { ComponentType } from 'react'
import {
  Activity,
  BookOpen,
  CalendarClock,
  CalendarPlus,
  ClipboardCheck,
  History,
  Home,
  Shield,
  TriangleAlert,
  UserCheck,
  UserSearch,
  Users,
  type LucideIcon,
} from 'lucide-react'
import MateriasPage from '@/app/router/pages/MateriasPage'

export type NavRole = 'docente'

export type NavBadge =
  | { type: 'count'; source: 'incidentCount' }
  | {
      type: 'phase'
      source: 'entryControlOpen' | 'examLive'
      label: string
      tone: 'open' | 'live'
    }

export interface NavItem {
  key: string
  label: string
  icon: LucideIcon
  group: string | null
  path: string
  badge?: NavBadge
  page?: ComponentType
}

export interface NavGroup {
  label: string | null
  items: NavItem[]
}

const docenteNav: NavItem[] = [
  { key: 'inicio', label: 'Inicio', icon: Home, group: null, path: '/' },

  { key: 'materias', label: 'Materias', icon: BookOpen, group: 'Gestión académica', path: '/materias', page: MateriasPage },
  { key: 'mis-cursos', label: 'Mis cursos', icon: Users, group: 'Gestión académica', path: '/mis-cursos' },
  { key: 'mis-auxiliares', label: 'Mis auxiliares', icon: UserCheck, group: 'Gestión académica', path: '/mis-auxiliares' },

  { key: 'nuevo-examen', label: 'Nuevo examen', icon: CalendarPlus, group: 'Exámenes', path: '/examenes/nuevo' },
  { key: 'programados', label: 'Programados', icon: CalendarClock, group: 'Exámenes', path: '/examenes/programados' },
  {
    key: 'control-de-ingreso',
    label: 'Control de ingreso',
    icon: ClipboardCheck,
    group: 'Exámenes',
    path: '/examenes/control-de-ingreso',
    badge: { type: 'phase', source: 'entryControlOpen', label: 'Abierto', tone: 'open' },
  },
  {
    key: 'en-curso',
    label: 'En curso',
    icon: Activity,
    group: 'Exámenes',
    path: '/examenes/en-curso',
    badge: { type: 'phase', source: 'examLive', label: 'En vivo', tone: 'live' },
  },
  {
    key: 'incidencias',
    label: 'Incidencias',
    icon: TriangleAlert,
    group: 'Exámenes',
    path: '/examenes/incidencias',
    badge: { type: 'count', source: 'incidentCount' },
  },
  { key: 'historial', label: 'Historial', icon: History, group: 'Exámenes', path: '/examenes/historial' },

  { key: 'verificar-antecedentes', label: 'Verificar antecedentes', icon: UserSearch, group: 'Central de riesgo', path: '/central-de-riesgo/verificar' },
  { key: 'registros', label: 'Registros', icon: Shield, group: 'Central de riesgo', path: '/central-de-riesgo/registros' },
]

export const navByRole: Record<NavRole, NavItem[]> = {
  docente: docenteNav,
}

// Agrupa los ítems consecutivos por `group`, conservando el orden de la lista.
export function groupNav(items: NavItem[]): NavGroup[] {
  const groups: NavGroup[] = []
  for (const item of items) {
    const last = groups[groups.length - 1]
    if (last && last.label === item.group) {
      last.items.push(item)
    } else {
      groups.push({ label: item.group, items: [item] })
    }
  }
  return groups
}
