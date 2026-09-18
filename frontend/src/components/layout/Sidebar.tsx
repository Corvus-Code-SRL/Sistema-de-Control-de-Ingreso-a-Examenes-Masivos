import React from 'react';
import { NavLink } from 'react-router-dom';
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
  LogOut
} from 'lucide-react';
import { cn } from 'cn';

interface MenuItem {
  label: string;
  path: string;
  icon: React.ComponentType<{ className?: string }>;
}

interface MenuSection {
  title?: string;
  items: MenuItem[];
}

const menuSections: MenuSection[] = [
  {
    items: [
      { label: 'Inicio', path: '/dashboard', icon: Home },
    ]
  },
  {
    title: 'Gestión Académica',
    items: [
      { label: 'Mis Materias', path: '/subjects', icon: BookOpen },
      { label: 'Grupos Académicos', path: '/groups', icon: Users },
      { label: 'Nóminas de Estudiantes', path: '/students', icon: GraduationCap },
      { label: 'Usuarios Auxiliares', path: '/assistants', icon: UserCheck },
    ]
  },
  {
    title: 'Exámenes',
    items: [
      { label: 'Exámenes programados', path: '/exams', icon: FileText },
      { label: 'Exámenes en Curso', path: '/exams/in-progress', icon: Clock },
      { label: 'Historial y Reportes', path: '/exams/history', icon: History },
    ]
  },
  {
    title: 'Central de Riesgo',
    items: [
      { label: 'Verificar Antecedentes', path: '/risk-center/verify', icon: ShieldCheck },
      { label: 'Revisar Incidencias', path: '/incidents', icon: AlertTriangle },
    ]
  },
  {
    title: 'Cuenta',
    items: [
      { label: 'Sesión', path: '/logout', icon: LogOut },
    ]
  }
];

export const Sidebar: React.FC = () => {
  return (
    <aside className="w-64 bg-[#05383E] text-white flex flex-col justify-between shrink-0 min-h-screen border-r border-[#002D33]">
      <div className="py-6 px-5 space-y-6">
        {/* SCIEM Brand Header */}
        <div className="space-y-1 pb-4 border-b border-[#0A4D54]">
          <h1 className="text-xl font-bold tracking-tight text-white">SCIEM</h1>
          <p className="text-[11px] text-[#A2C7CC] leading-tight font-normal">
            Sistema de Control de<br />Ingreso a Exámenes Masivos
          </p>
        </div>

        {/* Navigation Sections */}
        <div className="space-y-5">
          {menuSections.map((section, idx) => (
            <div key={idx} className="space-y-1.5">
              {section.title && (
                <p className="px-2 text-xs font-semibold text-[#84ACB1] tracking-wide">
                  {section.title}
                </p>
              )}
              <div className="space-y-0.5">
                {section.items.map((item) => {
                  const Icon = item.icon;
                  return (
                    <NavLink
                      key={item.path}
                      to={item.path}
                      className={({ isActive }) =>
                        cn(
                          "flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-150",
                          isActive
                            ? "bg-[#005E68] text-white font-semibold shadow-sm"
                            : "text-[#C7E0E3] hover:bg-[#084850] hover:text-white"
                        )
                      }
                    >
                      <Icon className="h-4 w-4 shrink-0 opacity-85" />
                      <span>{item.label}</span>
                    </NavLink>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      </div>
    </aside>
  );
};
