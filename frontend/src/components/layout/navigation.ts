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
 * Materias, Mis cursos, Nuevo examen y Programados (HU-24/HU-25) existen. El
 * resto se lista para dar la forma completa del producto, pero sin destino: un
 * enlace que no lleva a ninguna parte confunde más que uno visiblemente pendiente.
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
      { label: 'Programados', icon: CalendarClock, to: '/examenes/programados' },
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
 * Cuentas y Materias tienen pantallas disponibles. Facultades, carreras y
 * bitácora permanecen deshabilitadas hasta sus respectivas historias.
 */
const navegacionAdministrador: NavGroup[] = [
  { items: [{ label: 'Inicio', icon: Home }] },
  {
    label: 'Administración',
    items: [
      { label: 'Cuentas', icon: Users, to: '/cuentas' },
      { label: 'Materias', icon: BookOpen, to: '/materias' },
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
