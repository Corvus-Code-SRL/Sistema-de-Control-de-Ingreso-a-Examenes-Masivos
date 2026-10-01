import React from 'react';
import { CreateExamFormData, SubjectCareerOption, Classroom } from '../types/exams.types';
import { localToday, MAX_DURATION_MINUTES } from '../utils/examValidators';
import { Calendar as CalendarIcon, Clock as ClockIcon } from 'lucide-react';
import { FormSelect } from '@/components/common/FormSelect';

interface Props {
  formData: CreateExamFormData;
  subjects: SubjectCareerOption[];
  classrooms: Classroom[];
  errors: Record<string, string>;
  warnings: Record<string, string>;
  updateFormData: (fields: Partial<CreateExamFormData>) => void;
}

/** El par viaja como una sola opción del select: "idCarrera-idMateria". */
function pairKey(pair: { id_carrera: number; id_materia: number }): string {
  return `${pair.id_carrera}-${pair.id_materia}`;
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
          <FormSelect
            id="categoria"
            value={formData.categoria}
            placeholder="Seleccionar categoría"
            options={[
              { value: 'REGULAR', label: 'REGULAR' },
              { value: 'MESA', label: 'MESA' },
              { value: 'ADMISION', label: 'ADMISIÓN' },
            ]}
            onValueChange={(value) => updateFormData({
              categoria: value as CreateExamFormData['categoria'],
            })}
          />
        </div>

        <div className="space-y-1.5">
          <label htmlFor="fecha" className="block text-xs font-bold text-[#2C2C2C]">
            Fecha *
          </label>
          <div className="relative">
            <input
              id="fecha"
              type="date"
              min={localToday()}
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
          <label htmlFor="materia" className="block text-xs font-bold text-[#2C2C2C]">
            Materia *
          </label>
          <FormSelect
            id="materia"
            value={formData.materia ? pairKey(formData.materia) : undefined}
            placeholder="Seleccionar materia..."
            invalid={Boolean(errors.materia)}
            options={subjects.map((subject) => ({
              value: pairKey(subject),
              label: `${subject.nombre} — ${subject.carrera}`,
            }))}
            onValueChange={(value) => {
              const pair = subjects.find((subject) => pairKey(subject) === value);
              updateFormData({
                materia: pair
                  ? { id_carrera: pair.id_carrera, id_materia: pair.id_materia }
                  : null,
              });
            }}
          />
          {errors.materia && (
            <p className="text-[11px] text-red-600 font-medium">{errors.materia}</p>
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
            max={MAX_DURATION_MINUTES}
            step={1}
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
          <FormSelect
            id="ambientes"
            value={formData.ambientes[0] ? String(formData.ambientes[0]) : undefined}
            placeholder="Seleccionar aula..."
            invalid={Boolean(errors.ambientes)}
            options={classrooms.map((room) => ({
              value: String(room.id_ambiente),
              label: `Aula ${room.nro_aula} (${room.capacidad} pupitres)`,
            }))}
            onValueChange={(value) => updateFormData({ ambientes: [Number(value)] })}
          />
          {errors.ambientes && (
            <p className="text-[11px] text-red-600 font-medium">{errors.ambientes}</p>
          )}
        </div>
      </div>
    </div>
  );
};
