import React, { createContext, useContext, useState } from 'react';
import { Sidebar } from './Sidebar';
import { Breadcrumbs } from './Breadcrumbs';
import { Menu, X } from 'lucide-react';

interface CustomBreadcrumb {
  label: string;
  path?: string;
}

interface LayoutContextType {
  customBreadcrumbs: CustomBreadcrumb[];
  setCustomBreadcrumbs: (crumbs: CustomBreadcrumb[]) => void;
}

const LayoutContext = createContext<LayoutContextType>({
  customBreadcrumbs: [],
  setCustomBreadcrumbs: () => {},
});

export const useLayoutContext = () => useContext(LayoutContext);

interface MainLayoutProps {
  children: React.ReactNode;
}

export const MainLayout: React.FC<MainLayoutProps> = ({ children }) => {
  const [customBreadcrumbs, setCustomBreadcrumbs] = useState<CustomBreadcrumb[]>([]);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  return (
    <LayoutContext.Provider value={{ customBreadcrumbs, setCustomBreadcrumbs }}>
      <div className="flex flex-col md:flex-row min-h-screen bg-[#F3F8F8] text-[#2C2C2C] antialiased relative">
        {/* Desktop Sidebar (hidden on mobile) */}
        <div className="hidden md:flex shrink-0">
          <Sidebar />
        </div>

        {/* Mobile Header Bar (.mb in design mockup) */}
        <header className="h-14 bg-[#05383E] text-white px-4 flex items-center justify-between border-b border-[#0F4A51] md:hidden shrink-0 z-20 shadow-xs">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="p-1.5 rounded-lg text-[#8FC7CC] hover:text-white hover:bg-[#084850] transition-colors"
              aria-label="Abrir menú"
            >
              {mobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
            </button>
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-lg bg-[#005E68] text-white font-bold text-sm flex items-center justify-center border border-[#007A87]/30">
                S
              </div>
              <span className="font-bold text-base tracking-wide text-white">SCIEM</span>
            </div>
          </div>

          <div className="h-8 w-8 rounded-full bg-[#D8ECEE] text-[#005E68] font-bold text-xs flex items-center justify-center border border-[#BDE0E4]">
            JL
          </div>
        </header>

        {/* Mobile Sidebar Overlay Drawer */}
        {mobileMenuOpen && (
          <div className="fixed inset-0 z-40 md:hidden flex">
            {/* Backdrop */}
            <div
              className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
              onClick={() => setMobileMenuOpen(false)}
            />

            {/* Slide-over Drawer */}
            <div className="relative z-50 w-[272px] max-w-[80vw] h-full flex flex-col bg-[#05383E] shadow-2xl animate-in slide-in-from-left duration-200">
              <Sidebar onItemClick={() => setMobileMenuOpen(false)} />
            </div>
          </div>
        )}

        {/* Main Work Area */}
        <div className="flex-1 flex flex-col min-w-0">
          {/* Top Breadcrumbs Header Bar */}
          <header className="h-14 md:h-16 border-b border-[#DDDDDD] bg-white px-4 md:px-8 flex items-center justify-between shrink-0 shadow-2xs z-10">
            {/* Dynamic Breadcrumb Navigation */}
            <Breadcrumbs customItems={customBreadcrumbs} />

            {/* Desktop User Profile Block */}
            <div className="hidden sm:flex items-center gap-3">
              <div className="h-9 w-9 rounded-full bg-[#D8ECEE] text-[#005E68] font-bold text-xs flex items-center justify-center border border-[#BDE0E4] shadow-2xs">
                JL
              </div>
              <div className="leading-tight">
                <p className="text-xs font-bold text-[#2C2C2C]">Josué Lizarazu</p>
                <p className="text-[11px] text-[#6C757D] font-medium">Docente</p>
              </div>
            </div>
          </header>

          {/* Main Workspace Workspace */}
          <main className="flex-1 p-4 md:p-8 overflow-y-auto max-w-7xl w-full mx-auto">
            {children}
          </main>
        </div>
      </div>
    </LayoutContext.Provider>
  );
};
