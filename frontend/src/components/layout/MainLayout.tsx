import React from 'react';
import { Sidebar } from './Sidebar';
import { useLocation } from 'react-router-dom';

interface MainLayoutProps {
  children: React.ReactNode;
}

export const MainLayout: React.FC<MainLayoutProps> = ({ children }) => {
  const location = useLocation();

  // Generación dinámica de breadcrumb basada en la ruta actual
  const getBreadcrumbs = () => {
    if (location.pathname === '/exams/new') {
      return 'SCIEM / Exámenes / Configurar Nuevo Examen / Cálculo I';
    }
    if (location.pathname.includes('/exams/groups')) {
      return 'SCIEM / Exámenes / Configurar Nuevo Examen / Cálculo I / Asignar grupos';
    }
    return 'SCIEM / Exámenes / Exámenes programados';
  };

  return (
    <div className="flex min-h-screen bg-[#F3F8F8] text-[#2C2C2C] antialiased">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top Header Bar */}
        <header className="h-16 border-b border-[#DDDDDD] bg-white px-8 flex items-center justify-between shrink-0 shadow-xs">
          {/* Breadcrumb Navigation */}
          <div className="text-xs font-semibold text-[#2C2C2C] tracking-tight">
            {getBreadcrumbs()}
          </div>

          {/* User Profile Block */}
          <div className="flex items-center gap-3">
            <div className="h-9 w-9 rounded-full bg-[#D8ECEE] text-[#005E68] font-bold text-xs flex items-center justify-center border border-[#BDE0E4]">
              JL
            </div>
            <div className="leading-tight">
              <p className="text-xs font-bold text-[#2C2C2C]">Josué Lizarazu</p>
              <p className="text-[11px] text-[#6C757D] font-medium">Docente</p>
            </div>
          </div>
        </header>

        {/* Main Workspace Area */}
        <main className="flex-1 p-8 overflow-y-auto max-w-7xl w-full mx-auto">
          {children}
        </main>
      </div>
    </div>
  );
};
