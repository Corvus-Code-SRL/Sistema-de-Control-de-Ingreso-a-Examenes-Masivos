import React, { useMemo, useState } from 'react';
import { Classroom } from '../types/exams.types';
import { Check, Search, Info } from 'lucide-react';

interface Props {
  classrooms: Classroom[];
  selectedClassroomIds: number[];
  totalStudents: number;
  errors: Record<string, string>;
  onToggleClassroom: (classroomId: number) => void;
}

export const AssignClassroomsForm: React.FC<Props> = ({
  classrooms,
  selectedClassroomIds,
  totalStudents,
  errors,
  onToggleClassroom,
}) => {
  const [searchQuery, setSearchQuery] = useState('');

  const filteredClassrooms = useMemo(() => {
    if (!searchQuery.trim()) return classrooms;
    const q = searchQuery.toLowerCase();
    return classrooms.filter(
      (c) =>
        c.nro_aula.toLowerCase().includes(q) ||
        (c.ubicacion && c.ubicacion.toLowerCase().includes(q))
    );
  }, [classrooms, searchQuery]);

  const selectedClassrooms = classrooms.filter((c) => selectedClassroomIds.includes(c.id_ambiente));
  const totalCapacity = selectedClassrooms.reduce((acc, c) => acc + c.capacidad, 0);
  const capacityPercent = totalStudents > 0 ? Math.min((totalCapacity / totalStudents) * 100, 100) : 0;
  const isCapacitySufficient = totalCapacity >= totalStudents;

  return (
    <div className="space-y-6">
      {/* Header del paso */}
      <div>
        <h2 className="text-lg font-bold text-[#111827]">Ambientes</h2>
        <p className="text-sm text-[#6B7280] mt-0.5">
          Uno o más ambientes del catálogo.
        </p>
      </div>

      {/* Buscador */}
      <div className="relative">
        <Search className="h-4 w-4 text-[#9CA3AF] absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          type="text"
          placeholder="Buscar ambiente del catálogo por nombre o ubicación"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="w-full pl-10 pr-4 py-2.5 bg-white border border-[#D1D5DB] rounded-lg text-sm outline-none focus:border-[#005E68] focus:ring-1 focus:ring-[#005E68]/20 text-[#111827] placeholder:text-[#9CA3AF] transition-all"
        />
      </div>

      {/* Info */}
      <div className="flex items-start gap-2 text-xs text-[#6B7280]">
        <Info className="h-3.5 w-3.5 shrink-0 mt-0.5 text-[#9CA3AF]" />
        <p>Solo ambientes del catálogo. Si falta uno, lo registra el administrador.</p>
      </div>

      {/* Error */}
      {errors.ambientes && (
        <div className="p-3.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-medium flex items-center gap-2">
          <span className="shrink-0">⚠</span>
          {errors.ambientes}
        </div>
      )}

      {/* Lista de ambientes */}
      {filteredClassrooms.length === 0 ? (
        <div className="py-8 text-center text-sm text-[#6B7280]">
          No se encontraron ambientes.
        </div>
      ) : (
        <div className="divide-y divide-[#F3F4F6] border border-[#E5E7EB] rounded-lg overflow-hidden">
          {filteredClassrooms.map((room) => {
            const isSelected = selectedClassroomIds.includes(room.id_ambiente);

            return (
              <div
                key={room.id_ambiente}
                onClick={() => onToggleClassroom(room.id_ambiente)}
                className="px-4 py-4 flex items-center justify-between gap-3 cursor-pointer hover:bg-gray-50/70 transition-colors"
              >
                <div className="flex items-center gap-3">
                  {/* Checkbox */}
                  <div className="shrink-0">
                    <div
                      className={`h-5 w-5 rounded transition-colors flex items-center justify-center border ${
                        isSelected
                          ? 'bg-[#005E68] border-[#005E68] text-white'
                          : 'bg-white border-[#D1D5DB]'
                      }`}
                    >
                      {isSelected && <Check className="h-3.5 w-3.5 stroke-[3]" />}
                    </div>
                  </div>

                  {/* Info del ambiente */}
                  <div>
                    <p className="text-sm font-semibold text-[#111827]">
                      {room.nro_aula}
                    </p>
                    {room.ubicacion && (
                      <p className="text-xs text-[#6B7280]">{room.ubicacion}</p>
                    )}
                  </div>
                </div>

                {/* Capacidad */}
                <div className="text-right">
                  <p className="text-sm font-semibold text-[#111827]">{room.capacidad}</p>
                  <p className="text-[11px] text-[#6B7280]">lugares</p>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Barra de capacidad */}
      {selectedClassroomIds.length > 0 && (
        <div className={`rounded-lg border p-4 space-y-2 ${
          isCapacitySufficient
            ? 'border-[#E5E7EB] bg-white'
            : 'border-amber-200 bg-amber-50/50'
        }`}>
          <div className="flex items-center justify-between text-sm">
            <span className="font-semibold text-[#111827]">
              Capacidad: {totalCapacity} lugares
            </span>
            <span className="text-[#6B7280]">
              {totalStudents} estudiantes habilitados
            </span>
          </div>
          <div className="w-full h-2 bg-[#E5E7EB] rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-all duration-300 ${
                isCapacitySufficient ? 'bg-[#005E68]' : 'bg-amber-500'
              }`}
              style={{ width: `${capacityPercent}%` }}
            />
          </div>
          {!isCapacitySufficient && (
            <p className="text-xs text-amber-700 font-medium flex items-center gap-1.5">
              <span>⚠</span>
              Capacidad insuficiente. Faltan {totalStudents - totalCapacity} lugares. Añada otro ambiente.
            </p>
          )}
        </div>
      )}
    </div>
  );
};
