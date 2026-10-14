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

        <fieldset className="space-y-2">
          <legend className="text-xs font-bold text-text">
            Ambiente(s) *
          </legend>

          <div
            className={`max-h-52 overflow-y-auto rounded-lg border bg-surface ${
              errors.ambientes
                ? 'border-danger'
                : 'border-border-soft'
            }`}
          >
            {classrooms.length === 0 ? (
              <p className="px-4 py-3 text-xs text-muted-foreground">
                No hay ambientes disponibles.
              </p>
            ) : (
              classrooms.map((room) => {
                const selected = formData.ambientes.includes(
                  room.id_ambiente
                );

                return (
                  <label
                    key={room.id_ambiente}
                    className="flex cursor-pointer items-center
                      justify-between gap-3 border-b
                      border-border-soft px-4 py-3
                      last:border-b-0 hover:bg-sunken"
                  >
                    <span className="flex items-center gap-3">
                      <input
                        type="checkbox"
                        checked={selected}
                        onChange={(event) => {
                          const ambientes = event.target.checked
                            ? [
                                ...formData.ambientes,
                                room.id_ambiente,
                              ]
                            : formData.ambientes.filter(
                                (id) => id !== room.id_ambiente
                              );

                          updateFormData({ ambientes });
                        }}
                        className="h-4 w-4 rounded
                          border-border-strong accent-brand"
                      />

                      <span className="text-xs font-medium text-text">
                        Aula {room.nro_aula}
                      </span>
                    </span>

                    <span className="text-xs text-muted-foreground">
                      {room.capacidad} plazas
                    </span>
                  </label>
                );
              })
            )}
          </div>

          {formData.ambientes.length > 0 && (
            <div className="flex items-center justify-between
              rounded-lg bg-sunken px-4 py-3 text-xs"
            >
              <span className="font-medium text-text">
                {formData.ambientes.length}
                {' '}
                {formData.ambientes.length === 1
                  ? 'ambiente seleccionado'
                  : 'ambientes seleccionados'}
              </span>

              <span className="font-bold text-brand">
                Capacidad total:{' '}
                {classrooms
                  .filter((room) =>
                    formData.ambientes.includes(room.id_ambiente)
                  )
                  .reduce(
                    (total, room) => total + room.capacidad,
                    0
                  )}
              </span>
            </div>
          )}

          {errors.ambientes && (
            <p className="text-[11px] font-medium text-danger">
              {errors.ambientes}
            </p>
          )}
        </fieldset>

      </div>
    </div>
  );
};
