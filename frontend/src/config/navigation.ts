import {
  Home,
  BookOpen,
  Users,
  GraduationCap,
  UserCheck,
  FileText,
  Clock,
  History,
  ShieldCheck,
  AlertTriangle,
  LogOut,
  LucideIcon,
} from 'lucide-react';

export interface MenuItem {
  id: string;
  label: string;
  path: string;
  icon: LucideIcon;
  badge?: number | string;
  breadcrumbLabel?: string;
}

export interface MenuSection {
  id: string;
  title?: string;
  items: MenuItem[];
}

export const navigationConfig: MenuSection[] = [
  {
    id: 'main',
    items: [
      {
        id: 'home',
        label: 'Inicio',
        path: '/dashboard',
        icon: Home,
        breadcrumbLabel: 'Inicio',
      },
    ],
  },
  {
    id: 'academic',
    title: 'Gestión académica',
    items: [
      {
        id: 'subjects',
        label: 'Mis Materias',
        path: '/subjects',
        icon: BookOpen,
        breadcrumbLabel: 'Mis Materias',
      },
      {
        id: 'groups',
        label: 'Grupos Académicos',
        path: '/groups',
        icon: Users,
        breadcrumbLabel: 'Grupos Académicos',
      },
      {
        id: 'students',
        label: 'Nóminas de Estudiantes',
        path: '/students',
        icon: GraduationCap,
        breadcrumbLabel: 'Nómina de Estudiantes',
      },
      {
        id: 'assistants',
        label: 'Usuarios Auxiliares',
        path: '/assistants',
        icon: UserCheck,
        breadcrumbLabel: 'Usuarios Auxiliares',
      },
    ],
  },
  {
    id: 'exams',
    title: 'Exámenes',
    items: [
      {
        id: 'scheduled-exams',
        label: 'Exámenes programados',
        path: '/exams',
        icon: FileText,
        breadcrumbLabel: 'Exámenes Programados',
      },
      {
        id: 'in-progress-exams',
        label: 'Exámenes en Curso',
        path: '/exams/in-progress',
        icon: Clock,
        breadcrumbLabel: 'Exámenes en Curso',
      },
      {
        id: 'history-exams',
        label: 'Historial y Reportes',
        path: '/exams/history',
        icon: History,
        breadcrumbLabel: 'Historial y Reportes',
      },
    ],
  },
  {
    id: 'risk',
    title: 'Central de Riesgo',
    items: [
      {
        id: 'verify-risk',
        label: 'Verificar Antecedentes',
        path: '/risk-center/verify',
        icon: ShieldCheck,
        breadcrumbLabel: 'Verificar Antecedentes',
      },
      {
        id: 'incidents',
        label: 'Revisar Incidencias',
        path: '/incidents',
        icon: AlertTriangle,
        breadcrumbLabel: 'Registro de Incidentes',
      },
    ],
  },
];

// Special dynamic sub-path route mappings for breadcrumb generation
export const routeBreadcrumbMap: Record<string, string> = {
  '/': 'SCIEM',
  '/dashboard': 'Inicio',
  '/exams': 'Exámenes',
  '/exams/new': 'Configurar Nuevo Examen',
  '/subjects': 'Mis Materias',
  '/groups': 'Grupos Académicos',
  '/students': 'Nómina de Estudiantes',
  '/assistants': 'Usuarios Auxiliares',
  '/exams/in-progress': 'Exámenes en Curso',
  '/exams/history': 'Historial y Reportes',
  '/risk-center/verify': 'Verificar Antecedentes',
  '/incidents': 'Registro de Incidentes',
};
