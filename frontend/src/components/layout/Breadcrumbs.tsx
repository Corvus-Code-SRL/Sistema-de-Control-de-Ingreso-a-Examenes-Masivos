import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { routeBreadcrumbMap } from '@/config/navigation';

interface BreadcrumbItem {
  label: string;
  path: string;
  isCurrent: boolean;
}

interface BreadcrumbsProps {
  /** Optional custom extra breadcrumb items or override label for current route */
  customItems?: { label: string; path?: string }[];
  /** Root app label, default "SCIEM" */
  rootLabel?: string;
}

export const Breadcrumbs: React.FC<BreadcrumbsProps> = ({
  customItems,
  rootLabel = 'SCIEM',
}) => {
  const location = useLocation();

  // Generate dynamic breadcrumb items from current location pathname
  const getBreadcrumbItems = (): BreadcrumbItem[] => {
    const items: BreadcrumbItem[] = [
      {
        label: rootLabel,
        path: '/exams',
        isCurrent: false,
      },
    ];

    const pathSegments = location.pathname.split('/').filter(Boolean);
    let cumulativePath = '';

    pathSegments.forEach((segment, index) => {
      cumulativePath += `/${segment}`;
      const isLast = index === pathSegments.length - 1 && (!customItems || customItems.length === 0);

      // Lookup known breadcrumb label from route map or capitalize segment
      let label = routeBreadcrumbMap[cumulativePath];

      if (!label) {
        // Fallback: convert slug to title case (e.g. "in-progress" -> "In Progress")
        label = segment
          .split('-')
          .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ');
      }

      items.push({
        label,
        path: cumulativePath,
        isCurrent: isLast,
      });
    });

    // Add any dynamic custom items passed (e.g., subject name "Cálculo I")
    if (customItems && customItems.length > 0) {
      customItems.forEach((custom, index) => {
        const isLast = index === customItems.length - 1;
        items.push({
          label: custom.label,
          path: custom.path || location.pathname,
          isCurrent: isLast,
        });
      });
    }

    // Mark exact last item as current
    if (items.length > 0) {
      items.forEach((item, idx) => {
        item.isCurrent = idx === items.length - 1;
      });
    }

    return items;
  };

  const breadcrumbs = getBreadcrumbItems();

  return (
    <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-xs text-[#5B6770] font-medium">
      {breadcrumbs.map((item, index) => {
        return (
          <React.Fragment key={`${item.path}-${index}`}>
            {index > 0 && (
              <span className="text-[#AAB8BA] select-none flex items-center px-0.5">
                <ChevronRight className="h-3.5 w-3.5 opacity-60" />
              </span>
            )}
            {item.isCurrent ? (
              <span className="font-semibold text-[#1F2937] tracking-tight">
                {item.label}
              </span>
            ) : (
              <Link
                to={item.path}
                className="hover:text-[#005E68] transition-colors duration-150 tracking-tight"
              >
                {item.label}
              </Link>
            )}
          </React.Fragment>
        );
      })}
    </nav>
  );
};
