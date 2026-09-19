import React from 'react';
import { Search, X } from 'lucide-react';
import { Subject } from '../types/exams.types';

interface ExamsFilterBarProps {
  searchTerm: string;
  onSearchChange: (val: string) => void;
  selectedSubjectId: string;
  onSubjectChange: (val: string) => void;
  subjects: Subject[];
}

export const ExamsFilterBar: React.FC<ExamsFilterBarProps> = ({
  searchTerm,
  onSearchChange,
  selectedSubjectId,
  onSubjectChange,
  subjects,
}) => {
  return (
    <div className="flex flex-col sm:flex-row items-center gap-3 w-full">
      {/* Search Input */}
      <div className="relative flex-1 w-full">
        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#8A969B]" />
        <input
          type="text"
          value={searchTerm}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Buscar por nombre o materia"
          className="w-full h-11 pl-10 pr-9 bg-white border border-[#D5DDDF] rounded-xl text-sm text-[#2C2C2C] placeholder:text-[#8A969B] focus:outline-none focus:ring-2 focus:ring-[#005E68]/20 focus:border-[#005E68] transition-all duration-150 shadow-2xs"
        />
        {searchTerm && (
          <button
            type="button"
            onClick={() => onSearchChange('')}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-[#8A969B] hover:text-[#2C2C2C] p-0.5 rounded-md"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        )}
      </div>

      {/* Subject Filter Select */}
      <div className="w-full sm:w-64 shrink-0">
        <select
          value={selectedSubjectId}
          onChange={(e) => onSubjectChange(e.target.value)}
          className="w-full h-11 px-3.5 bg-white border border-[#D5DDDF] rounded-xl text-sm font-medium text-[#2C2C2C] focus:outline-none focus:ring-2 focus:ring-[#005E68]/20 focus:border-[#005E68] transition-all duration-150 shadow-2xs cursor-pointer"
        >
          <option value="">Todas las materias</option>
          {subjects.map((s) => (
            <option key={s.id_materia} value={String(s.id_materia)}>
              {s.nombre}
            </option>
          ))}
        </select>
      </div>
    </div>
  );
};
