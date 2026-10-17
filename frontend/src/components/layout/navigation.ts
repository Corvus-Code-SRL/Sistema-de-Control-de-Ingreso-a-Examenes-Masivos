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
  Library,
  Link2,
  PlaySquare,
  ScrollText,
  ShieldCheck,
  SquarePen,
  Users
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { Area } from '@/features/auth'

export interface NavItem {
  label: string
  icon: LucideIcon
  /** Sin `to` el destino aún no existe: el enlace se dibuja deshabilitado. */
  to?: string
  /** Solo se marca activo con la ruta exacta; hace falta cuando otro ítem cuelga de la misma ruta. */
  end?: boolean
  /**
   * Sub-ítems. Un ítem con hijos es un encabezado sin enlace (no lleva `to`): solo sus hijos
   * navegan.
   */
  children?: NavItem[]
}

export interface NavGroup {
  label?: string
  items: NavItem[]
}

/**
 * Navegación lateral del docente.
 *
 * Materias, Mis cursos, Nuevo examen, Programados (HU-24/HU-25) y Registros de la Central de
 * Riesgo (HU-046) existen. El resto se lista para dar la forma completa del producto, pero sin destino: un
 * enlace que no lleva a ninguna parte confunde más que uno visiblemente pendiente.
 */
const navegacionDocente: NavGroup[] = [
  { items: [{ label: 'Inicio', icon: Home }] },
  {
    label: 'Gestión académica',
    items: [
      { label: 'Materias', icon: BookOpen, to: '/materias' },
      { label: 'Mis cursos', icon: GraduationCap, to: '/mis-cursos' },
      { label: 'Mis auxiliares', icon: Users, to: '/mis-auxiliares' },
    ],
  },
  {
    label: 'Exámenes',
    items: [
      { label: 'Nuevo examen', icon: SquarePen, to: '/examenes/nuevo' },
      { label: 'Programados', icon: CalendarClock, to: '/examenes/programados' },
      { label: 'Control de ingreso', icon: DoorOpen, to: '/control-ingreso' },
      { label: 'En curso', icon: PlaySquare },
      { label: 'Incidencias', icon: AlertTriangle },
      { label: 'Historial', icon: History }
    ]
  },
  {
    label: 'Central de riesgo',
    items: [
      { label: 'Verificar antecedentes', icon: FileSearch },
      { label: 'Registros', icon: ClipboardList, to: '/central-riesgo' }
    ]
  }
]

/**
 * Navegación lateral del auxiliar: consulta los exámenes que controla (HU-09) y
 * registra el ingreso de los estudiantes (HU-11).
 */
const navegacionAuxiliar: NavGroup[] = [
  {
    label: 'Exámenes',
    items: [
      { label: 'Mis exámenes', icon: CalendarClock, to: '/mis-examenes' },
      { label: 'Control de ingreso', icon: DoorOpen, to: '/control-ingreso' },
    ]
  }
]

/**
 * Navegación lateral del administrador.
 *
 * Cuentas, Materias (Asignaciones, HU-006, y Catálogo, en dos vistas bajo un encabezado) y
 * Ambientes tienen pantallas disponibles, y Supervisión agrupa la Central de riesgo (HU-046).
 * Facultades, carreras y bitácora permanecen deshabilitadas hasta sus respectivas historias.
 */
const navegacionAdministrador: NavGroup[] = [
  { items: [{ label: 'Inicio', icon: Home }] },
  {
    label: 'Administración',
    items: [
      { label: 'Cuentas', icon: Users, to: '/cuentas' },
      {
        label: 'Materias',
        icon: BookOpen,
        children: [
          { label: 'Asignaciones', icon: Link2, to: '/materias/asignaciones' },
          { label: 'Catálogo', icon: Library, to: '/materias/catalogo' },
        ],
      },
      { label: 'Facultades', icon: Building2 },
      { label: 'Ambientes', icon: DoorOpen, to: '/ambientes' },
      { label: 'Carreras', icon: GraduationCap }
    ]
  },
  {
    label: 'Supervisión',
    items: [
      { label: 'Central de riesgo', icon: ClipboardList, to: '/central-riesgo' },
      { label: 'Bitácora', icon: ScrollText }
    ]
  }
]

export const navigationByArea: Record<Area, NavGroup[]> = {
  docente: navegacionDocente,
  auxiliar: navegacionAuxiliar,
  administrador: navegacionAdministrador
}

export const shieldIcon = ShieldCheck
