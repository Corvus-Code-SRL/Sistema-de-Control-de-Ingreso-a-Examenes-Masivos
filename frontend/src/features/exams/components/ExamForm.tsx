import React from 'react';
import { CreateExamFormData, Subject } from '../types/exams.types';
import { Calendar as CalendarIcon, Clock as ClockIcon, ChevronDown } from 'lucide-react';

interface Props {
  formData: CreateExamFormData;
  subjects: Subject[];
  errors: Record<string, string>;
  updateFormData: (fields: Partial<CreateExamFormData>) => void;
}

export const ExamForm: React.FC<Props> = ({
  formData,
  subjects,
  errors,
  updateFormData,
}) => {
  const charCount = formData.nombre_examen.length;

  return (
    <div className="space-y-6">
      {/* Header del paso */}
      <div>
        <h2 className="text-lg font-bold text-[#111827]">Datos generales</h2>
        <p className="text-sm text-[#6B7280] mt-0.5">
          Nombre, materia, fecha, horario y apertura del control.
        </p>
      </div>

      {/* Banner de errores */}
      {Object.keys(errors).length > 0 && (
        <div className="p-3.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs font-medium flex items-center gap-2">
          <span className="shrink-0">⚠</span>
          Corrija {Object.keys(errors).length} campo{Object.keys(errors).length > 1 ? 's' : ''} para continuar
        </div>
      )}

      {/* Nombre del examen */}
      <div className="space-y-1.5">
        <label htmlFor="nombre_examen" className="block text-sm font-semibold text-[#111827]">
          Nombre del examen <span className="text-red-500">*</span>
        </label>
        <input
          id="nombre_examen"
          type="text"
          maxLength={25}
          placeholder="Ej. 1er Parcial"
          value={formData.nombre_examen}
          onChange={(e) => updateFormData({ nombre_examen: e.target.value })}
          className={`w-full px-3.5 py-2.5 bg-white border ${
            errors.nombre_examen ? 'border-red-400 bg-red-50/30' : 'border-[#D1D5DB]'
          } rounded-lg text-sm outline-none focus:border-[#005E68] focus:ring-1 focus:ring-[#005E68]/20 text-[#111827] placeholder:text-[#9CA3AF] transition-all`}
        />
        <div className="flex items-center justify-between">
          {errors.nombre_examen ? (
            <p className="text-xs text-red-600 font-medium flex items-center gap-1">
              <span>⚠</span> {errors.nombre_examen}
            </p>
          ) : (
            <span />
          )}
          <p className="text-xs text-[#9CA3AF]">
            {charCount} / 25 caracteres.
          </p>
        </div>
      </div>

      {/* Materia */}
      <div className="space-y-1.5">
        <label htmlFor="id_materia" className="block text-sm font-semibold text-[#111827]">
          Materia <span className="text-red-500">*</span>
        </label>
        <div className="relative">
          <select
            id="id_materia"
            value={formData.id_materia ?? ''}
            onChange={(e) => updateFormData({ id_materia: e.target.value ? Number(e.target.value) : null })}
            className={`w-full px-3.5 py-2.5 bg-white border ${
              errors.id_materia ? 'border-red-400 bg-red-50/30' : 'border-[#D1D5DB]'
            } rounded-lg text-sm outline-none focus:border-[#005E68] focus:ring-1 focus:ring-[#005E68]/20 text-[#111827] appearance-none cursor-pointer pr-9 font-medium`}
          >
            <option value="">Seleccione una de sus materias</option>
            {subjects.map((sub) => (
              <option key={sub.id_materia} value={sub.id_materia}>
                {sub.nombre}
              </option>
            ))}
          </select>
          <ChevronDown className="h-4 w-4 text-[#6B7280] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        </div>
        {errors.id_materia && (
          <p className="text-xs text-red-600 font-medium flex items-center gap-1">
            <span>⚠</span> {errors.id_materia}
          </p>
        )}
      </div>

      {/* Fecha + Hora de inicio */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
        <div className="space-y-1.5">
          <label htmlFor="fecha" className="block text-sm font-semibold text-[#111827]">
            Fecha <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <input
              id="fecha"
              type="date"
              value={formData.fecha}
              onChange={(e) => updateFormData({ fecha: e.target.value })}
              className={`w-full px-3.5 py-2.5 bg-white border ${
                errors.fecha ? 'border-red-400 bg-red-50/30' : 'border-[#D1D5DB]'
              } rounded-lg text-sm outline-none focus:border-[#005E68] focus:ring-1 focus:ring-[#005E68]/20 text-[#111827] transition-all`}
            />
            <CalendarIcon className="h-4 w-4 text-[#6B7280] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
          {errors.fecha && (
            <p className="text-xs text-red-600 font-medium flex items-center gap-1">
              <span>⚠</span> {errors.fecha}
            </p>
          )}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="hora_inicio" className="block text-sm font-semibold text-[#111827]">
            Hora de inicio <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <input
              id="hora_inicio"
              type="time"
              value={formData.hora_inicio}
              onChange={(e) => updateFormData({ hora_inicio: e.target.value })}
              className={`w-full px-3.5 py-2.5 bg-white border ${
                errors.hora_inicio ? 'border-red-400 bg-red-50/30' : 'border-[#D1D5DB]'
              } rounded-lg text-sm outline-none focus:border-[#005E68] focus:ring-1 focus:ring-[#005E68]/20 text-[#111827] transition-all`}
            />
            <ClockIcon className="h-4 w-4 text-[#6B7280] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
          {errors.hora_inicio && (
            <p className="text-xs text-red-600 font-medium flex items-center gap-1">
              <span>⚠</span> {errors.hora_inicio}
            </p>
          )}
        </div>
      </div>

      {/* Duración */}
      <div className="space-y-1.5">
        <label htmlFor="duracion" className="block text-sm font-semibold text-[#111827]">
          Duración <span className="text-red-500">*</span>
        </label>
        <div className="relative max-w-[200px]">
          <input
            id="duracion"
            type="number"
            min={30}
            placeholder="Ej. 120"
            value={formData.duracion || ''}
            onChange={(e) => {
              const val = e.target.value;
              updateFormData({ duracion: val === '' ? 0 : parseInt(val, 10) || 0 });
            }}
            className={`w-full px-3.5 py-2.5 bg-white border ${
              errors.duracion ? 'border-red-400 bg-red-50/30' : 'border-[#D1D5DB]'
            } rounded-lg text-sm outline-none focus:border-[#005E68] focus:ring-1 focus:ring-[#005E68]/20 text-[#111827] placeholder:text-[#9CA3AF] transition-all pr-12`}
          />
          <span className="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-[#6B7280] font-medium pointer-events-none">
            min
          </span>
        </div>
        {errors.duracion && (
          <p className="text-xs text-red-600 font-medium flex items-center gap-1">
            <span>⚠</span> {errors.duracion}
          </p>
        )}
      </div>
    </div>
  );
};
