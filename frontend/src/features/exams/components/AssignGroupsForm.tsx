import React from 'react';
import { Group, Subject } from '../types/exams.types';
import { Loader2, Check, AlertTriangle } from 'lucide-react';

interface Props {
  selectedSubject: Subject | undefined;
  availableGroups: Group[];
  selectedGroupIds: number[];
  loadingGroups: boolean;
  errors: Record<string, string>;
  onToggleGroup: (groupId: number) => void;
}

export const AssignGroupsForm: React.FC<Props> = ({
  selectedSubject,
  availableGroups,
  selectedGroupIds,
  loadingGroups,
  errors,
  onToggleGroup,
}) => {
  const subjectName = selectedSubject?.nombre ?? 'la materia';

  return (
    <div className="space-y-6">
      {/* Header del paso */}
      <div>
        <h2 className="text-lg font-bold text-[#111827]">Grupos</h2>
        <p className="text-sm text-[#6B7280] mt-0.5">
          Elija sus grupos de <strong className="text-[#111827]">{subjectName}</strong> que rinden este examen.
        </p>
      </div>

      {/* Info */}
      <div className="flex items-start gap-2 text-xs text-[#6B7280] bg-[#F9FAFB] rounded-lg px-3.5 py-3 border border-[#F3F4F6]">
        <span className="shrink-0 mt-0.5 text-[#9CA3AF]">ℹ</span>
        <p>
          Solo sus grupos de <strong className="text-[#111827]">{subjectName}</strong>. Los grupos de otros docentes se suman por invitación en el paso Personal.
        </p>
      </div>

      {/* Error de validación */}
      {errors.grupos && (
        <div className="p-3.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-medium flex items-start gap-2">
          <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
          <div>
            <p className="font-semibold">Elija al menos un grupo para continuar</p>
            <p className="text-xs font-normal mt-0.5 text-red-600">
              Un examen necesita al menos un grupo con nómina.
            </p>
          </div>
        </div>
      )}

      {/* Lista de grupos */}
      {loadingGroups ? (
        <div className="py-10 text-center text-[#6B7280] text-sm font-medium flex items-center justify-center gap-2">
          <Loader2 className="h-5 w-5 animate-spin text-[#005E68]" />
          <span>Cargando grupos participantes...</span>
        </div>
      ) : availableGroups.length === 0 ? (
        <div className="py-8 text-center text-sm text-[#6B7280]">
          No se encontraron grupos para esta materia.
        </div>
      ) : (
        <div className="divide-y divide-[#F3F4F6] border border-[#E5E7EB] rounded-lg overflow-hidden">
          {availableGroups.map((group) => {
            const isSelected = selectedGroupIds.includes(group.id_grupo);
            const studentCount = group.cantidad_estudiantes ?? group.inscritos_count ?? 0;
            const hasRoster = group.tiene_nomina || studentCount > 0;

            return (
              <div
                key={group.id_grupo}
                onClick={() => {
                  if (hasRoster) onToggleGroup(group.id_grupo);
                }}
                className={`px-4 py-4 flex items-center justify-between gap-3 transition-colors ${
                  hasRoster ? 'cursor-pointer hover:bg-gray-50/70' : 'cursor-not-allowed opacity-60 bg-gray-50/30'
                }`}
              >
                <div className="flex items-center gap-3">
                  {/* Checkbox */}
                  <div className="shrink-0">
                    <div
                      className={`h-5 w-5 rounded transition-colors flex items-center justify-center border ${
                        !hasRoster
                          ? 'bg-[#F3F4F6] border-[#E5E7EB]'
                          : isSelected
                          ? 'bg-[#005E68] border-[#005E68] text-white'
                          : 'bg-white border-[#D1D5DB]'
                      }`}
                    >
                      {isSelected && hasRoster && <Check className="h-3.5 w-3.5 stroke-[3]" />}
                    </div>
                  </div>

                  {/* Info del grupo */}
                  <div>
                    <p className={`text-sm font-semibold ${hasRoster ? 'text-[#111827]' : 'text-[#9CA3AF]'}`}>
                      Grupo {group.num_grupo}
                    </p>
                    <p className="text-xs text-[#6B7280]">
                      {group.gestion}
                    </p>
                  </div>
                </div>

                {/* Derecha: count o badge */}
                <div className="flex items-center gap-3">
                  {!hasRoster && (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                      <AlertTriangle className="h-3 w-3" />
                      Sin nómina
                    </span>
                  )}
                  {hasRoster && (
                    <div className="text-right">
                      <p className="text-sm font-semibold text-[#111827]">{studentCount}</p>
                      <p className="text-[11px] text-[#6B7280]">inscritos</p>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};
