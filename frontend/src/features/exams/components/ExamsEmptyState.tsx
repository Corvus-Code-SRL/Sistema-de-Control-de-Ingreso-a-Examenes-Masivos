import React from 'react';
import { CalendarPlus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ExamsEmptyStateProps {
  onNewExamClick: () => void;
  isFiltered?: boolean;
}

export const ExamsEmptyState: React.FC<ExamsEmptyStateProps> = ({
  onNewExamClick,
  isFiltered = false,
}) => {
  return (
    <div className="bg-white border border-[#D5DDDF] rounded-2xl p-8 sm:p-12 md:p-16 flex flex-col items-center text-center space-y-4 shadow-2xs max-w-2xl mx-auto my-4">
      {/* Icon */}
      <div className="w-14 h-14 rounded-2xl bg-[#D8ECEE] text-[#005E68] flex items-center justify-center shadow-2xs">
        <CalendarPlus className="h-7 w-7" />
      </div>

      {/* Content */}
      <div className="space-y-1.5 max-w-md">
        <h3 className="text-lg font-bold text-[#1F2937] tracking-tight">
          {isFiltered ? 'No se encontraron exámenes' : 'No tiene exámenes programados'}
        </h3>
        <p className="text-sm text-[#5B6770] leading-relaxed">
          {isFiltered
            ? 'No hay evaluaciones programadas que coincidan con la búsqueda o filtro seleccionado.'
            : 'Cree un examen para uno o más de sus grupos. Aparecerá aquí hasta que abra su control de ingreso.'}
        </p>
      </div>

      {/* Action Button */}
      {!isFiltered && (
        <Button
          onClick={onNewExamClick}
          className="bg-[#005E68] hover:bg-[#004D56] text-white font-semibold text-xs px-5 py-2.5 rounded-lg gap-2 shadow-xs transition-colors duration-150 mt-2"
        >
          <Plus className="h-4 w-4" /> Nuevo examen
        </Button>
      )}
    </div>
  );
};
