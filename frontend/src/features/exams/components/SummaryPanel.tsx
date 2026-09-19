import React from 'react';
import { CreateExamFormData, Subject, Classroom, Group } from '../types/exams.types';
import { Info } from 'lucide-react';

interface Props {
  formData: CreateExamFormData;
  subjects: Subject[];
  classrooms: Classroom[];
  selectedGroups: Group[];
}

export const SummaryPanel: React.FC<Props> = ({
  formData,
  subjects,
  classrooms,
  selectedGroups,
}) => {
  const selectedSubject = subjects.find((m) => m.id_materia === formData.id_materia);

  const formatDate = (dateStr: string) => {
    if (!dateStr) return '—';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
      const year = parts[0];
      const monthIdx = parseInt(parts[1], 10) - 1;
      const day = parseInt(parts[2], 10);
      const months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
      const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
      const dateObj = new Date(parseInt(year), monthIdx, day);
      const dayName = dayNames[dateObj.getDay()];
      return `${dayName} ${String(day).padStart(2, '0')}/${String(monthIdx + 1).padStart(2, '0')}/${year}`;
    }
    return dateStr;
  };

  const formatTime = (time: string, duracion: number) => {
    if (!time) return '—';
    const [h, m] = time.split(':').map(Number);
    const endMinutes = h * 60 + m + duracion;
    const endH = Math.floor(endMinutes / 60) % 24;
    const endM = endMinutes % 60;
    return `${time}–${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
  };

  const selectedClassrooms = classrooms.filter((c) => formData.ambientes.includes(c.id_ambiente));
  const totalStudents = selectedGroups.reduce(
    (acc, g) => acc + (g.cantidad_estudiantes ?? g.inscritos_count ?? 0),
    0,
  );
  const totalCapacity = selectedClassrooms.reduce((acc, c) => acc + c.capacidad, 0);

  return (
    <aside className="bg-white rounded-xl border border-[#E5E7EB] p-5 shadow-xs h-fit sticky top-6 space-y-4">
      <h3 className="text-base font-bold text-[#111827]">Resumen</h3>

      <div className="space-y-3.5">
        {/* Nombre */}
        <div>
          <p className="text-[10px] font-bold text-[#6B7280] uppercase tracking-wider">Nombre</p>
          <p className="text-sm font-semibold text-[#111827] mt-0.5">
            {formData.nombre_examen || '—'}
          </p>
        </div>

        {/* Materia */}
        <div>
          <p className="text-[10px] font-bold text-[#6B7280] uppercase tracking-wider">Materia</p>
          <p className="text-sm font-semibold text-[#111827] mt-0.5">
            {selectedSubject ? selectedSubject.nombre : '—'}
          </p>
        </div>

        {/* Fecha y hora */}
        <div>
          <p className="text-[10px] font-bold text-[#6B7280] uppercase tracking-wider">Fecha y hora</p>
          <p className="text-sm font-semibold text-[#111827] mt-0.5">
            {formData.fecha
              ? `${formatDate(formData.fecha)} · ${formatTime(formData.hora_inicio, formData.duracion || 0)}`
              : '—'}
          </p>
        </div>

        {/* Grupos */}
        <div>
          <p className="text-[10px] font-bold text-[#6B7280] uppercase tracking-wider">Grupos</p>
          <p className="text-sm font-semibold text-[#111827] mt-0.5">
            {selectedGroups.length > 0
              ? `${selectedGroups.map((g) => `Grupo ${g.num_grupo}`).join(', ')} · ${totalStudents} estudiantes`
              : '—'}
          </p>
        </div>

        {/* Ambientes */}
        <div>
          <p className="text-[10px] font-bold text-[#6B7280] uppercase tracking-wider">Ambientes</p>
          <p className="text-sm font-semibold text-[#111827] mt-0.5">
            {selectedClassrooms.length > 0
              ? selectedClassrooms.map((c) => `Aula ${c.nro_aula} · ${c.capacidad} lugares`).join(', ')
              : '—'}
          </p>
        </div>
      </div>

      {/* Nota al pie */}
      <div className="pt-3 border-t border-[#F3F4F6]">
        <p className="text-[11px] text-[#6B7280] flex items-center gap-1.5">
          <Info className="h-3.5 w-3.5 shrink-0" />
          Se crea en estado <span className="font-semibold">En configuración</span>.
        </p>
      </div>
    </aside>
  );
};
