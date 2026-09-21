import {
  AlertTriangle,
  BookOpen,
  Building2,
  CalendarClock,
  ClipboardList,
  DoorOpen,
  FileSearch,
  GraduationCap,
  History,
  Home,
  PlaySquare,
  ScrollText,
  ShieldCheck,
  SquarePen,
  Users,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { Area } from '@/features/auth'

export interface NavItem {
  label: string
  icon: LucideIcon
  /** Sin `to` el destino aún no existe: el enlace se dibuja deshabilitado. */
  to?: string
  badge?: number
}

export interface NavGroup {
  label?: string
  items: NavItem[]
}

/**
 * Navegación lateral del docente.
 *
 * Materias, Mis cursos y Nuevo examen (HU-24) existen. El resto se lista para
 * dar la forma completa del producto, pero sin destino: un enlace que no lleva
 * a ninguna parte confunde más que uno visiblemente pendiente.
 */
const navegacionDocente: NavGroup[] = [
  { items: [{ label: 'Inicio', icon: Home }] },
  {
    label: 'Gestión académica',
    items: [
      { label: 'Materias', icon: BookOpen, to: '/materias' },
      { label: 'Mis cursos', icon: GraduationCap, to: '/mis-cursos' },
      { label: 'Mis auxiliares', icon: Users },
    ],
  },
  {
    label: 'Exámenes',
    items: [
      { label: 'Nuevo examen', icon: SquarePen, to: '/examenes/nuevo' },
      { label: 'Programados', icon: CalendarClock },
      { label: 'Control de ingreso', icon: DoorOpen },
      { label: 'En curso', icon: PlaySquare },
      { label: 'Incidencias', icon: AlertTriangle, badge: 3 },
      { label: 'Historial', icon: History },
    ],
  },
  {
    label: 'Central de riesgo',
    items: [
      { label: 'Verificar antecedentes', icon: FileSearch },
      { label: 'Registros', icon: ClipboardList },
    ],
  },
]

/**
 * Navegación lateral del administrador.
 *
 * De momento solo Cuentas tiene pantalla (HU-01). Materias, facultades y
 * carreras son el catálogo institucional que administran HU-06 y HU-07, aún sin
 * construir, así que se listan deshabilitadas igual que las del docente.
 */
const navegacionAdministrador: NavGroup[] = [
  { items: [{ label: 'Inicio', icon: Home }] },
  {
    label: 'Administración',
    items: [
      { label: 'Cuentas', icon: Users, to: '/cuentas' },
      { label: 'Materias', icon: BookOpen },
      { label: 'Facultades', icon: Building2 },
      { label: 'Carreras', icon: GraduationCap },
      { label: 'Bitácora', icon: ScrollText },
    ],
  },
]

export const navigationByArea: Record<Area, NavGroup[]> = {
  docente: navegacionDocente,
  administrador: navegacionAdministrador,
}

export const shieldIcon = ShieldCheck
