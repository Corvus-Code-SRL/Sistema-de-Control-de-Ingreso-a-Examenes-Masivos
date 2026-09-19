import React, { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { ChevronLeft, ChevronRight, LogOut } from 'lucide-react';
import { cn } from '@/lib/utils';
import { navigationConfig, MenuSection } from '@/config/navigation';

interface SidebarProps {
  sections?: MenuSection[];
  onToggleCollapse?: (collapsed: boolean) => void;
  onItemClick?: () => void;
}

export const Sidebar: React.FC<SidebarProps> = ({
  sections = navigationConfig,
  onToggleCollapse,
  onItemClick,
}) => {
  const [isCollapsed, setIsCollapsed] = useState(false);
  const location = useLocation();

  const handleToggle = () => {
    const nextCollapsed = !isCollapsed;
    setIsCollapsed(nextCollapsed);
    if (onToggleCollapse) {
      onToggleCollapse(nextCollapsed);
    }
  };

  return (
    <aside
      className={cn(
        "bg-[#05383E] text-[#D8ECEE] flex flex-col justify-between shrink-0 min-h-screen border-r border-[#0F4A51] transition-all duration-300 relative select-none",
        isCollapsed ? "w-[72px]" : "w-[272px]"
      )}
    >
      {/* Top Brand Block */}
      <div className="flex flex-col">
        <div
          className={cn(
            "h-[76px] flex items-center gap-3 border-b border-[#0F4A51] transition-all duration-300",
            isCollapsed ? "justify-center px-4" : "px-5"
          )}
        >
          <div className="w-10 h-10 rounded-xl bg-[#005E68] text-white font-bold text-lg flex items-center justify-center shrink-0 shadow-xs border border-[#007A87]/30">
            S
          </div>

          {!isCollapsed && (
            <div className="flex flex-col min-w-0">
              <span className="font-bold text-[17px] leading-tight text-white tracking-wide">
                SCIEM
              </span>
              <span className="text-[12px] text-[#8FC7CC] leading-tight whitespace-nowrap truncate font-normal">
                Control de Ingreso a Exámenes
              </span>
            </div>
          )}
        </div>

        {/* Dynamic Navigation Menu Sections */}
        <nav aria-label="Main Navigation" className="p-3 space-y-4 overflow-y-auto">
          {sections.map((section) => (
            <div key={section.id} className="space-y-1">
              {section.title && !isCollapsed && (
                <p className="px-3 pt-4 pb-1.5 text-[11px] font-semibold text-[#7FB0B5] uppercase tracking-wider leading-none">
                  {section.title}
                </p>
              )}

              {section.title && isCollapsed && (
                <div className="my-2 mx-auto w-6 h-[1px] bg-[#24626A]" />
              )}

              <div className="space-y-1">
                {section.items.map((item) => {
                  const Icon = item.icon;
                  const isActive =
                    location.pathname === item.path ||
                    (item.path !== '/' &&
                      item.path !== '/dashboard' &&
                      location.pathname.startsWith(item.path));

                  return (
                    <NavLink
                      key={item.id}
                      to={item.path}
                      onClick={onItemClick}
                      title={isCollapsed ? item.label : undefined}
                      className={cn(
                        "relative flex items-center gap-3 h-[40px] px-3 rounded-lg text-sm transition-colors duration-150 group",
                        isCollapsed ? "justify-center px-0" : "px-3",
                        isActive
                          ? "bg-[#005E68] text-white font-semibold shadow-xs"
                          : "text-[#D8ECEE] font-medium hover:bg-[#084850] hover:text-white"
                      )}
                    >
                      {/* Gold Accent Bar on Active Item */}
                      {isActive && (
                        <span className="absolute -left-3 top-2 w-[3px] h-6 rounded-r-sm bg-[#FFCB32] shadow-xs" />
                      )}

                      <Icon
                        className={cn(
                          "h-5 w-5 shrink-0 transition-opacity duration-150",
                          isActive ? "opacity-100 text-white" : "opacity-80 group-hover:opacity-100"
                        )}
                      />

                      {!isCollapsed && (
                        <span className="flex-1 truncate leading-none">
                          {item.label}
                        </span>
                      )}

                      {/* Optional Counter/Badge */}
                      {item.badge !== undefined && (
                        <span
                          className={cn(
                            "min-w-5 h-5 px-1.5 rounded-full bg-[#FFCB32] text-[#05383E] text-[11px] font-bold flex items-center justify-center leading-none shrink-0 shadow-xs",
                            isCollapsed && "absolute top-1 right-1.5 min-w-[16px] h-4 text-[10px] px-1"
                          )}
                        >
                          {item.badge}
                        </span>
                      )}
                    </NavLink>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>
      </div>

      {/* Footer & Sidebar Collapse Toggle */}
      <div className="p-3 border-t border-[#0F4A51] space-y-1">
        <button
          type="button"
          onClick={handleToggle}
          className={cn(
            "w-full flex items-center gap-3 h-[38px] px-3 rounded-lg text-xs font-medium text-[#8FC7CC] hover:bg-[#084850] hover:text-white transition-colors duration-150",
            isCollapsed ? "justify-center px-0" : "px-3"
          )}
          title={isCollapsed ? "Expandir menú" : "Contraer menú"}
        >
          {isCollapsed ? (
            <ChevronRight className="h-4 w-4 shrink-0" />
          ) : (
            <>
              <ChevronLeft className="h-4 w-4 shrink-0" />
              <span>Contraer menú</span>
            </>
          )}
        </button>

        <NavLink
          to="/logout"
          title={isCollapsed ? "Cerrar Sesión" : undefined}
          className={cn(
            "flex items-center gap-3 h-[38px] px-3 rounded-lg text-xs font-medium text-[#8FC7CC] hover:bg-red-900/40 hover:text-red-200 transition-colors duration-150",
            isCollapsed ? "justify-center px-0" : "px-3"
          )}
        >
          <LogOut className="h-4 w-4 shrink-0" />
          {!isCollapsed && <span>Cerrar Sesión</span>}
        </NavLink>
      </div>
    </aside>
  );
};
