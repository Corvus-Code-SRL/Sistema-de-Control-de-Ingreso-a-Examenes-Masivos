import {
  AlertTriangle,
  BookOpen,
  CalendarClock,
  ClipboardList,
  DoorOpen,
  FileSearch,
  GraduationCap,
  History,
  Home,
  PlaySquare,
  ShieldCheck,
  SquarePen,
  Users,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

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
 * Solo Materias y Mis cursos existen en esta historia. El resto se lista para
 * dar la forma completa del producto, pero sin destino: un enlace que no lleva
 * a ninguna parte confunde más que uno visiblemente pendiente.
 */
export const navigation: NavGroup[] = [
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
      { label: 'Nuevo examen', icon: SquarePen },
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

export const shieldIcon = ShieldCheck
