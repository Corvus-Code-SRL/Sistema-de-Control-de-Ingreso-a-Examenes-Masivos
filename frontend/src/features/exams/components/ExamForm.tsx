import React from 'react';
import { CreateExamFormData, Subject, Classroom } from '../types/exams.types';
import { Calendar as CalendarIcon, Clock as ClockIcon, ChevronDown } from 'lucide-react';

interface Props {
  formData: CreateExamFormData;
  subjects: Subject[];
  classrooms: Classroom[];
  errors: Record<string, string>;
  warnings: Record<string, string>;
  updateFormData: (fields: Partial<CreateExamFormData>) => void;
}

export const ExamForm: React.FC<Props> = ({
  formData,
  subjects,
  classrooms,
  errors,
  updateFormData,
}) => {
  return (
    <div className="bg-white rounded-xl border border-[#DDDDDD] p-8 shadow-xs space-y-6">
      <h2 className="text-base font-bold text-[#2C2C2C]">
        Información general del examen
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
        <div className="space-y-1.5">
          <label htmlFor="nombre_examen" className="block text-xs font-bold text-[#2C2C2C]">
            Nombre del examen *
          </label>
          <input
            id="nombre_examen"
            type="text"
            placeholder="Ej. Primer Parcial"
            value={formData.nombre_examen}
            onChange={(e) => updateFormData({ nombre_examen: e.target.value })}
            className={`w-full px-3.5 py-2.5 bg-white border ${errors.nombre_examen ? 'border-red-500' : 'border-[#DDDDDD]'
              } rounded-lg text-xs outline-none focus:border-[#005E68] text-[#2C2C2C] placeholder:text-[#999999] transition-colors`}
          />
          {errors.nombre_examen && (
            <p className="text-[11px] text-red-600 font-medium">{errors.nombre_examen}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="categoria" className="block text-xs font-bold text-[#2C2C2C]">
            Categoría *
          </label>
          <div className="relative">
            <select
              id="categoria"
              value={formData.categoria}
              onChange={(e) => updateFormData({ categoria: e.target.value as CreateExamFormData['categoria'] })}
              className="w-full px-3.5 py-2.5 bg-white border border-[#DDDDDD] rounded-lg text-xs outline-none focus:border-[#005E68] text-[#2C2C2C] appearance-none cursor-pointer pr-9 font-medium"
            >
              <option value="REGULAR">REGULAR</option>
              <option value="FINAL">FINAL</option>
              <option value="MESA">MESA</option>
              <option value="ADMISION">ADMISION</option>
            </select>
            <ChevronDown className="h-4 w-4 text-[#6C757D] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
        </div>

        <div className="space-y-1.5">
          <label htmlFor="fecha" className="block text-xs font-bold text-[#2C2C2C]">
            Fecha *
          </label>
          <div className="relative">
            <input
              id="fecha"
              type="date"
              value={formData.fecha}
              onChange={(e) => updateFormData({ fecha: e.target.value })}
              className={`w-full px-3.5 py-2.5 bg-white border ${errors.fecha ? 'border-red-500' : 'border-[#DDDDDD]'
                } rounded-lg text-xs outline-none focus:border-[#005E68] text-[#2C2C2C] transition-colors`}
            />
            <CalendarIcon className="h-4 w-4 text-[#6C757D] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
          {errors.fecha && (
            <p className="text-[11px] text-red-600 font-medium">{errors.fecha}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="id_materia" className="block text-xs font-bold text-[#2C2C2C]">
            Materia *
          </label>
          <div className="relative">
            <select
              id="id_materia"
              value={formData.id_materia ?? ''}
              onChange={(e) => updateFormData({ id_materia: e.target.value ? Number(e.target.value) : null })}
              className={`w-full px-3.5 py-2.5 bg-white border ${errors.id_materia ? 'border-red-500' : 'border-[#DDDDDD]'
                } rounded-lg text-xs outline-none focus:border-[#005E68] text-[#2C2C2C] appearance-none cursor-pointer pr-9 font-medium`}
            >
              <option value="">Seleccionar Materia...</option>
              {subjects.map((sub) => (
                <option key={sub.id_materia} value={sub.id_materia}>
                  {sub.nombre}
                </option>
              ))}
            </select>
            <ChevronDown className="h-4 w-4 text-[#6C757D] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
          {errors.id_materia && (
            <p className="text-[11px] text-red-600 font-medium">{errors.id_materia}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="duracion" className="block text-xs font-bold text-[#2C2C2C]">
            Duración (min) *
          </label>
          <input
            id="duracion"
            type="number"
            min={1}
            placeholder="90"
            value={formData.duracion || ''}
            onChange={(e) => {
              const val = e.target.value;
              updateFormData({ duracion: val === '' ? 0 : parseInt(val, 10) || 0 });
            }}
            className={`w-full px-3.5 py-2.5 bg-white border ${errors.duracion ? 'border-red-500' : 'border-[#DDDDDD]'
              } rounded-lg text-xs outline-none focus:border-[#005E68] text-[#2C2C2C] placeholder:text-[#999999] transition-colors`}
          />
          {errors.duracion && (
            <p className="text-[11px] text-red-600 font-medium">{errors.duracion}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="hora_inicio" className="block text-xs font-bold text-[#2C2C2C]">
            Hora *
          </label>
          <div className="relative">
            <input
              id="hora_inicio"
              type="time"
              value={formData.hora_inicio}
              onChange={(e) => updateFormData({ hora_inicio: e.target.value })}
              className={`w-full px-3.5 py-2.5 bg-white border ${errors.hora_inicio ? 'border-red-500' : 'border-[#DDDDDD]'
                } rounded-lg text-xs outline-none focus:border-[#005E68] text-[#2C2C2C] transition-colors`}
            />
            <ClockIcon className="h-4 w-4 text-[#6C757D] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
          {errors.hora_inicio && (
            <p className="text-[11px] text-red-600 font-medium">{errors.hora_inicio}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <label htmlFor="ambientes" className="block text-xs font-bold text-[#2C2C2C]">
            Ambiente(s) *
          </label>
          <div className="relative">
            <select
              id="ambientes"
              value={formData.ambientes[0] ?? ''}
              onChange={(e) => updateFormData({ ambientes: e.target.value ? [Number(e.target.value)] : [] })}
              className={`w-full px-3.5 py-2.5 bg-white border ${errors.ambientes ? 'border-red-500' : 'border-[#DDDDDD]'
                } rounded-lg text-xs outline-none focus:border-[#005E68] text-[#2C2C2C] appearance-none cursor-pointer pr-9 font-medium`}
            >
              <option value="">Seleccionar Aula...</option>
              {classrooms.map((room) => (
                <option key={room.id_ambiente} value={room.id_ambiente}>
                  Aula {room.nro_aula} ({room.capacidad} pupitres)
                </option>
              ))}
            </select>
            <ChevronDown className="h-4 w-4 text-[#6C757D] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          </div>
          {errors.ambientes && (
            <p className="text-[11px] text-red-600 font-medium">{errors.ambientes}</p>
          )}
        </div>
      </div>
    </div>
  );
};
