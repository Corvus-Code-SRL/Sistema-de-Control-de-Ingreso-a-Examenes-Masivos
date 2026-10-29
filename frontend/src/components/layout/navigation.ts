import {
  AlertTriangle,
  BookOpen,
  BookPlus,
  Building2,
  CalendarClock,
  ClipboardList,
  Clock,
  DoorOpen,
  FilePenLine,
  FileText,
  FileUp,
  GraduationCap,
  History,
  Landmark,
  Library,
  Link2,
  MailOpen,
  PlaySquare,
  ScanFace,
  ScanSearch,
  ScrollText,
  ShieldAlert,
  SquarePen,
  Unlink,
  UserCheck,
  UserCog,
  UserPlus,
  UserRoundCheck,
  UserRoundPlus,
  Users
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { Capability } from '@/features/auth'

/** Aviso de las entradas cuya pantalla aún no tiene un acceso directo. */
export const DISABLED_REASON = 'Disponible próximamente'

/** Contadores que el menú puede mostrar junto a una entrada; el valor lo aporta quien dibuja. */
export type NavBadge = 'pendingIncidents'

export interface NavItem {
  /** Estable: lo usan el estado activo, las pruebas y los contadores; nunca el texto visible. */
  id: string
  label: string
  icon: LucideIcon
  /** La misma capacidad que exige la ruta de destino (o la que exigirá, si aún no hay destino). */
  capability: Capability
  /** Sin `to` el destino aún no existe: la entrada se dibuja deshabilitada. */
  to?: string
  /**
   * Patrones de ruta (sintaxis de react-router) que también pertenecen a esta entrada, además de
   * `to` y sus subrutas (que ya cuentan por prefijo): pantallas que cuelgan de otra raíz, como el
   * detalle de un grupo o de un examen.
   */
  matches?: string[]
  badge?: NavBadge
}

export interface NavSection {
  id: string
  label: string
  icon: LucideIcon
  /**
   * Capacidad que da entrada a la sección. Hace falta porque el Docente hereda capacidades del
   * Auxiliar y la Central de riesgo la comparten dos roles: sin ella, las entradas de un perfil
   * dejarían secciones sueltas en el menú de otro.
   */
  capability: Capability
  items: NavItem[]
}

/**
 * Navegación lateral de todos los roles, como datos.
 *
 * El menú de cada usuario sale de aquí filtrando por sus capacidades (ver `visibleSections`), no
 * por el nombre de su rol: un rol nuevo solo necesita un conjunto de capacidades. Las entradas sin
 * `to` se muestran deshabilitadas hasta que se conecte su pantalla.
 */
export const NAVIGATION: readonly NavSection[] = [
  // ---- Administrador ----
  {
    id: 'admin-accounts',
    label: 'Cuentas y roles',
    icon: Users,
    capability: 'administracion.gestionar',
    items: [
      {
        id: 'admin-register-account',
        label: 'Registrar cuenta nueva',
        icon: UserPlus,
        capability: 'administracion.gestionar',
        to: '/cuentas'
      },
      {
        id: 'admin-change-role',
        label: 'Modificar rol',
        icon: UserCog,
        capability: 'administracion.gestionar'
      }
    ]
  },
  {
    id: 'admin-catalog',
    label: 'Catálogo académico y ambientes',
    icon: Landmark,
    capability: 'administracion.gestionar',
    items: [
      {
        id: 'admin-academic-catalog',
        label: 'Catálogo académico',
        icon: Library,
        capability: 'administracion.gestionar',
        to: '/materias/catalogo'
      },
      {
        id: 'admin-register-faculty',
        label: 'Registrar facultad',
        icon: Building2,
        capability: 'administracion.gestionar'
      },
      {
        id: 'admin-register-career',
        label: 'Registrar carrera',
        icon: GraduationCap,
        capability: 'administracion.gestionar'
      },
      {
        id: 'admin-register-subject',
        label: 'Registrar materia',
        icon: BookPlus,
        capability: 'administracion.gestionar'
      },
      {
        id: 'admin-assign-subject',
        label: 'Asignar materia a carrera',
        icon: Link2,
        capability: 'administracion.gestionar',
        to: '/materias/asignaciones'
      },
      {
        id: 'admin-disable-subject',
        label: 'Deshabilitar materia de carrera',
        icon: Unlink,
        capability: 'administracion.gestionar'
      },
      {
        id: 'admin-register-classroom',
        label: 'Registrar ambiente',
        icon: DoorOpen,
        capability: 'administracion.gestionar',
        to: '/ambientes'
      }
    ]
  },
  {
    id: 'admin-risk-center',
    label: 'Central de riesgo',
    icon: ShieldAlert,
    capability: 'administracion.gestionar',
    items: [
      {
        id: 'admin-risk-query',
        label: 'Consultar central',
        icon: ScanSearch,
        capability: 'riesgo.consultar',
        to: '/central-riesgo'
      },
      {
        id: 'admin-risk-rectify',
        label: 'Rectificar registro de la central',
        icon: FilePenLine,
        capability: 'administracion.gestionar'
      }
    ]
  },
  {
    id: 'admin-audit',
    label: 'Historial, auditoría y reportes',
    icon: History,
    capability: 'administracion.gestionar',
    items: [
      {
        id: 'admin-audit-log',
        label: 'Bitácora de operaciones',
        icon: ScrollText,
        capability: 'administracion.gestionar'
      },
      {
        id: 'admin-audit-report',
        label: 'Generar reporte de bitácora',
        icon: FileText,
        capability: 'administracion.gestionar'
      }
    ]
  },

  // ---- Docente ----
  {
    id: 'teacher-groups',
    label: 'Grupos académicos y nóminas',
    icon: GraduationCap,
    capability: 'grupos.gestionar',
    items: [
      {
        id: 'teacher-subjects',
        label: 'Materias',
        icon: BookOpen,
        capability: 'grupos.gestionar',
        to: '/materias',
        matches: ['/carreras/:idCarrera/materias/:idMateria/grupos']
      },
      {
        id: 'teacher-my-groups',
        label: 'Mis grupos',
        icon: Users,
        capability: 'grupos.gestionar',
        to: '/mis-cursos',
        matches: ['/cursos/:idGrupo', '/grupos/:idGrupo/*']
      },
      {
        id: 'teacher-upload-roster',
        label: 'Cargar nómina de estudiantes',
        icon: FileUp,
        capability: 'grupos.gestionar'
      },
      {
        id: 'teacher-my-assistants',
        label: 'Mis auxiliares',
        icon: UserCheck,
        capability: 'auxiliares.gestionar',
        to: '/mis-auxiliares'
      }
    ]
  },
  {
    id: 'teacher-exam-prep',
    label: 'Preparación de exámenes',
    icon: ClipboardList,
    capability: 'examenes.gestionar',
    items: [
      {
        id: 'teacher-scheduled-exams',
        label: 'Programados',
        icon: CalendarClock,
        capability: 'examenes.gestionar',
        to: '/examenes/programados',
        matches: ['/examenes/:examId/*']
      },
      {
        id: 'teacher-create-exam',
        label: 'Registrar examen',
        icon: SquarePen,
        capability: 'examenes.gestionar',
        to: '/examenes/nuevo'
      },
      {
        id: 'teacher-edit-exam',
        label: 'Editar examen',
        icon: FilePenLine,
        capability: 'examenes.gestionar'
      },
      {
        id: 'teacher-enable-assistant',
        label: 'Habilitar auxiliar para el examen',
        icon: UserPlus,
        capability: 'examenes.gestionar'
      },
      {
        id: 'teacher-upload-eligible',
        label: 'Cargar nómina de habilitados',
        icon: FileUp,
        capability: 'examenes.gestionar'
      },
      {
        id: 'teacher-invite-collaborators',
        label: 'Invitar docentes colaboradores',
        icon: UserRoundPlus,
        capability: 'examenes.gestionar'
      },
      {
        id: 'teacher-exam-invitations',
        label: 'Invitaciones a otros exámenes',
        icon: MailOpen,
        capability: 'examenes.gestionar'
      }
    ]
  },
  {
    id: 'teacher-entry-and-progress',
    label: 'Control de ingreso y examen en curso',
    icon: DoorOpen,
    capability: 'examenes.gestionar',
    items: [
      {
        id: 'teacher-entry-control',
        label: 'Control de ingreso',
        icon: ScanFace,
        capability: 'ingreso.operar',
        to: '/control-ingreso',
        matches: ['/examenes/:examId/control-ingreso']
      },
      {
        id: 'teacher-exam-in-progress',
        label: 'En curso',
        icon: PlaySquare,
        to: '/examenes/en-curso',
        capability: 'ingreso.operar'
      },
      {
        id: 'teacher-late-student',
        label: 'Registrar estudiante con retraso',
        icon: Clock,
        capability: 'ingreso.operar'
      },
      {
        id: 'teacher-exceptional-student',
        label: 'Registrar estudiante excepcional',
        icon: UserRoundCheck,
        capability: 'examenes.gestionar'
      }
    ]
  },
  {
    id: 'teacher-risk-center',
    label: 'Central de riesgo',
    icon: ShieldAlert,
    capability: 'examenes.gestionar',
    items: [
      {
        id: 'teacher-incident-reports',
        label: 'Revisar reportes de incidencias',
        icon: AlertTriangle,
        capability: 'examenes.gestionar',
        to: '/incidencias',
        badge: 'pendingIncidents'
      },
      {
        id: 'teacher-risk-query',
        label: 'Central de riesgo',
        icon: ShieldAlert,
        capability: 'riesgo.consultar',
        to: '/central-riesgo'
      }
    ]
  },
  {
    id: 'teacher-history',
    label: 'Historial de exámenes y reportes',
    icon: History,
    capability: 'examenes.gestionar',
    items: [
      {
        id: 'teacher-exam-history',
        label: 'Historial de mis exámenes',
        icon: History,
        capability: 'examenes.gestionar'
      },
      {
        id: 'teacher-exam-report',
        label: 'Generar reporte de examen',
        icon: FileText,
        capability: 'examenes.gestionar'
      }
    ]
  },

  // ---- Auxiliar ----
  {
    id: 'assistant-exams',
    label: 'Exámenes y grupos',
    icon: GraduationCap,
    capability: 'asignaciones.consultar',
    items: [
      {
        id: 'assistant-my-groups',
        label: 'Mis grupos',
        icon: Users,
        capability: 'asignaciones.consultar'
      },
      {
        id: 'assistant-my-exams',
        label: 'Mis exámenes',
        icon: CalendarClock,
        capability: 'ingreso.operar',
        to: '/mis-examenes'
      }
    ]
  },
  {
    id: 'assistant-entry-and-reports',
    label: 'Control de ingreso y reportes',
    icon: DoorOpen,
    capability: 'asignaciones.consultar',
    items: [
      {
        id: 'assistant-entry-control',
        label: 'Control de ingreso',
        icon: ScanFace,
        capability: 'ingreso.operar',
        to: '/control-ingreso',
        matches: ['/examenes/:examId/control-ingreso']
      },
      {
        id: 'assistant-exam-in-progress',
        label: 'En curso',
        icon: PlaySquare,
        to: '/examenes/en-curso',
        capability: 'ingreso.operar'
      },
      {
        id: 'assistant-late-student',
        label: 'Registrar estudiante con retraso',
        icon: Clock,
        capability: 'ingreso.operar'
      }
    ]
  }
]
