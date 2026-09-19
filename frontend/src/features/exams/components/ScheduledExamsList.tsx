import React, { useState } from 'react';
import {
  Pencil,
  MoreVertical,
  Building2,
  Users,
  UserCheck,
  Clock,
  Lock,
  Eye,
  Trash2,
} from 'lucide-react';
import { DayGroupedExams, ScheduledExamItem } from '../types/exams.types';
import { cn } from '@/lib/utils';

interface ScheduledExamsListProps {
  dayGroups: DayGroupedExams[];
  onEditExam: (exam: ScheduledExamItem) => void;
  onCancelExam?: (exam: ScheduledExamItem) => void;
}

const BADGE_STYLES: Record<ScheduledExamItem['estadoBadge']['type'], string> = {
  warn: 'bg-[#FFF3C7] text-[#9A6F00]',
  info: 'bg-[#D8ECEE] text-[#005E68]',
  ok: 'bg-[#DFF1E7] text-[#15803D]',
  neutral: 'bg-[#EEF2F2] text-[#4F5B62]',
};

export const ScheduledExamsList: React.FC<ScheduledExamsListProps> = ({
  dayGroups,
  onEditExam,
  onCancelExam,
}) => {
  const [activeMenuId, setActiveMenuId] = useState<number | null>(null);

  const toggleMenu = (id: number, e: React.MouseEvent) => {
    e.stopPropagation();
    setActiveMenuId((prev) => (prev === id ? null : id));
  };

  return (
    <div className="space-y-8">
      {dayGroups.map((group) => (
        <div
          key={`${group.dayNum}-${group.daySubtitle}`}
          className="flex flex-col md:flex-row gap-3 md:gap-6 items-start"
        >
          {/* Date Column */}
          <div className="w-full md:w-[92px] shrink-0 flex md:flex-col items-baseline md:items-start gap-2 md:gap-0 pt-1 border-b md:border-none border-[#E2E8F0] pb-2 md:pb-0">
            <span className="text-2xl md:text-[28px] font-bold text-[#1F2937] leading-tight">
              {group.dayNum}
            </span>
            <span className="text-xs font-medium text-[#5B6770] capitalize">
              {group.daySubtitle}
            </span>
          </div>

          {/* Exam Cards Container */}
          <div className="flex-1 w-full space-y-3.5">
            {group.exams.map((exam) => {
              const isMenuOpen = activeMenuId === exam.id;

              return (
                <div
                  key={exam.id}
                  className="bg-white border border-[#D5DDDF] rounded-xl p-4 sm:p-5 shadow-2xs hover:shadow-xs transition-all duration-150 relative space-y-3 group"
                >
                  {/* Top Header Row inside Card */}
                  <div className="flex flex-wrap items-center justify-between gap-2 border-b border-[#F0F4F4] pb-2.5">
                    {/* Time Badge */}
                    <div className="flex items-center gap-1.5 text-xs font-bold text-[#1F2937]">
                      <Clock className="h-3.5 w-3.5 text-[#5B6770]" />
                      <span>{exam.horario}</span>
                    </div>

                    {/* Status Badge */}
                    <span
                      className={cn(
                        "inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold",
                        BADGE_STYLES[exam.estadoBadge.type]
                      )}
                    >
                      {exam.estadoBadge.type === 'warn' && <Clock className="h-3 w-3" />}
                      {exam.estadoBadge.type === 'info' && <Lock className="h-3 w-3" />}
                      {exam.estadoBadge.label}
                    </span>
                  </div>

                  {/* Main Title & Action Row */}
                  <div className="flex items-start justify-between gap-4">
                    <div className="space-y-0.5 min-w-0">
                      <h4 className="text-base font-bold text-[#1F2937] leading-snug tracking-tight hover:text-[#005E68] transition-colors">
                        {exam.nombre}
                      </h4>
                      <p className="text-xs text-[#5B6770] font-medium leading-relaxed">
                        {exam.materia} <span className="text-[#AAB8BA]">•</span> {exam.grupoStr}
                      </p>
                    </div>

                    {/* Actions: Edit & More Menu */}
                    <div className="flex items-center gap-2 shrink-0">
                      <button
                        type="button"
                        onClick={() => onEditExam(exam)}
                        className="h-9 px-3 border border-[#D5DDDF] rounded-lg text-xs font-semibold text-[#1F2937] bg-white hover:bg-[#F3F8F8] hover:border-[#005E68]/40 hover:text-[#005E68] flex items-center gap-1.5 transition-colors shadow-2xs"
                      >
                        <Pencil className="h-3.5 w-3.5" />
                        <span>Editar</span>
                      </button>

                      {/* Options Dropdown */}
                      <div className="relative">
                        <button
                          type="button"
                          onClick={(e) => toggleMenu(exam.id, e)}
                          className="h-9 w-9 rounded-lg border border-[#D5DDDF] bg-white hover:bg-[#F3F8F8] flex items-center justify-center text-[#5B6770] hover:text-[#1F2937] transition-colors"
                        >
                          <MoreVertical className="h-4 w-4" />
                        </button>

                        {isMenuOpen && (
                          <>
                            <div
                              className="fixed inset-0 z-20"
                              onClick={() => setActiveMenuId(null)}
                            />

                            <div className="absolute right-0 mt-1 w-44 bg-white border border-[#D5DDDF] rounded-xl shadow-md py-1.5 z-30 text-xs animate-in fade-in zoom-in-95 duration-100">
                              <button
                                type="button"
                                onClick={() => {
                                  setActiveMenuId(null);
                                  onEditExam(exam);
                                }}
                                className="w-full text-left px-3.5 py-2 text-[#1F2937] hover:bg-[#F3F8F8] flex items-center gap-2 font-medium"
                              >
                                <Eye className="h-3.5 w-3.5 text-[#5B6770]" />
                                <span>Ver detalles</span>
                              </button>
                              <button
                                type="button"
                                onClick={() => {
                                  setActiveMenuId(null);
                                  onEditExam(exam);
                                }}
                                className="w-full text-left px-3.5 py-2 text-[#1F2937] hover:bg-[#F3F8F8] flex items-center gap-2 font-medium"
                              >
                                <Pencil className="h-3.5 w-3.5 text-[#5B6770]" />
                                <span>Editar examen</span>
                              </button>
                              <div className="my-1 border-t border-[#E2E8F0]" />
                              <button
                                type="button"
                                onClick={() => {
                                  setActiveMenuId(null);
                                  if (onCancelExam) onCancelExam(exam);
                                }}
                                className="w-full text-left px-3.5 py-2 text-red-600 hover:bg-red-50 flex items-center gap-2 font-medium"
                              >
                                <Trash2 className="h-3.5 w-3.5 text-red-500" />
                                <span>Cancelar examen</span>
                              </button>
                            </div>
                          </>
                        )}
                      </div>
                    </div>
                  </div>

                  {/* Meta Items Grid */}
                  <div className="flex flex-wrap items-center gap-x-5 gap-y-2 pt-1 text-xs text-[#5B6770] font-medium">
                    <div className="flex items-center gap-1.5">
                      <Building2 className="h-3.5 w-3.5 text-[#8A969B]" />
                      <span>{exam.aulas}</span>
                    </div>

                    <div className="flex items-center gap-1.5">
                      <Users className="h-3.5 w-3.5 text-[#8A969B]" />
                      <span>{exam.habilitadosCount} habilitados</span>
                    </div>

                    <div className="flex items-center gap-1.5">
                      <UserCheck className="h-3.5 w-3.5 text-[#8A969B]" />
                      <span>{exam.auxiliaresStr}</span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
};
