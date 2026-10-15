import React from 'react';
import { CreateExamFormData, SubjectCareerOption, Classroom } from '../types/exams.types';
import { localToday, MAX_DURATION_MINUTES } from '../utils/examValidators';
import { Calendar as CalendarIcon } from 'lucide-react';
import { FormSelect } from '@/components/common/FormSelect';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { TimePicker, TimePickerContent, TimePickerTrigger } from '@/components/reui/time-picker';

function parseLocalDate(value: string): Date | undefined {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return undefined;
  const [year, month, day] = value.split('-').map(Number);
  const date = new Date(year, month - 1, day);
  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day
    ? date
    : undefined;
}

function displayDate(date: Date): string {
  return new Intl.DateTimeFormat('es-BO', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}

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
  const [dateOpen, setDateOpen] = React.useState(false);
  const selectedDate = parseLocalDate(formData.fecha);
  const today = parseLocalDate(localToday())!;

  return (
    <div className="space-y-6 rounded-xl border border-border bg-card p-4 shadow-xs sm:p-8">
      <h2 className="text-base font-bold text-foreground">
        Información general del examen
      </h2>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
        <div className="min-w-0 space-y-1.5">
          <label htmlFor="nombre_examen" className="block text-xs font-bold text-foreground">
            Nombre del examen *
          </label>
          <input
            id="nombre_examen"
            type="text"
            placeholder="Ej. Primer Parcial"
            value={formData.nombre_examen}
            onChange={(e) => updateFormData({ nombre_examen: e.target.value })}
            className={`h-8 w-full min-w-0 px-3.5 bg-card border ${errors.nombre_examen ? 'border-danger' : 'border-border'
              } rounded-lg text-xs outline-none focus:border-ring text-foreground placeholder:text-muted-foreground transition-colors`}
          />
          {errors.nombre_examen && (
            <p className="text-[11px] text-danger-fg font-medium">{errors.nombre_examen}</p>
          )}
        </div>

        <div className="min-w-0 space-y-1.5">
          <label htmlFor="categoria" className="block text-xs font-bold text-foreground">
            Categoría *
          </label>
          <FormSelect
            id="categoria"
            className="h-8 min-w-0 data-[size=default]:h-8"
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

        <div className="min-w-0 space-y-1.5">
          <label id="fecha-label" htmlFor="fecha" className="block text-xs font-bold text-foreground">
            Fecha *
          </label>
          <Popover open={dateOpen} onOpenChange={setDateOpen}>
            <PopoverTrigger asChild>
              <Button
                id="fecha"
                type="button"
                aria-labelledby="fecha-label fecha-value"
                variant="outline"
                aria-invalid={Boolean(errors.fecha) || undefined}
                aria-describedby={errors.fecha ? 'fecha-error' : undefined}
                className={cn(
                  'h-8 w-full min-w-0 justify-between gap-2 border-input bg-background px-3.5 text-left text-xs font-medium',
                  !selectedDate && 'text-muted-foreground'
                )}
              >
                <span id="fecha-value" className="min-w-0 truncate">{selectedDate ? displayDate(selectedDate) : 'Seleccionar fecha'}</span>
                <CalendarIcon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
              </Button>
            </PopoverTrigger>
            <PopoverContent align="start" collisionPadding={12} className="w-[min(19rem,calc(100vw-1.5rem))] p-3">
              <Calendar
                mode="single"
                selected={selectedDate}
                defaultMonth={selectedDate ?? today}
                disabled={{ before: today }}
                onSelect={(date) => {
                  if (!date) return;
                  updateFormData({ fecha: localToday(date) });
                  setDateOpen(false);
                }}
              />
            </PopoverContent>
          </Popover>
          {errors.fecha && (
            <p id="fecha-error" className="text-[11px] font-medium text-danger-fg">{errors.fecha}</p>
          )}
        </div>

        <div className="min-w-0 space-y-1.5">
          <label htmlFor="materia" className="block text-xs font-bold text-foreground">
            Materia *
          </label>
          <FormSelect
            id="materia"
            className="h-8 min-w-0 data-[size=default]:h-8"
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
            <p className="text-[11px] text-danger-fg font-medium">{errors.materia}</p>
          )}
        </div>

        <div className="min-w-0 space-y-1.5">
          <label htmlFor="duracion" className="block text-xs font-bold text-foreground">
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
            className={`h-8 w-full min-w-0 px-3.5 bg-card border ${errors.duracion ? 'border-danger' : 'border-border'
              } rounded-lg text-xs outline-none focus:border-ring text-foreground placeholder:text-muted-foreground transition-colors`}
          />
          {errors.duracion && (
            <p className="text-[11px] text-danger-fg font-medium">{errors.duracion}</p>
          )}
        </div>

        <div className="min-w-0 space-y-1.5">
          <label id="hora-inicio-label" htmlFor="hora_inicio" className="block text-xs font-bold text-foreground">
            Hora *
          </label>
          <TimePicker
            id="hora_inicio"
            aria-labelledby="hora-inicio-label"
            value={formData.hora_inicio || null}
            onValueChange={(value) => updateFormData({ hora_inicio: value ?? '' })}
            hourCycle={24}
            granularity="minute"
            invalid={Boolean(errors.hora_inicio)}
            aria-describedby={errors.hora_inicio ? 'hora-inicio-error' : undefined}
            i18n={{
              labels: {
                placeholder: 'Seleccionar hora',
                hour: 'Hora',
                minute: 'Minuto',
                now: 'Ahora',
                clear: 'Limpiar',
                confirm: 'Aceptar',
                panelLabel: 'Elegir hora de inicio',
              },
            }}
          >
            <TimePickerTrigger className="h-8 w-full min-w-0 border-input bg-background px-3.5 text-xs font-medium" />
            <TimePickerContent collisionPadding={12} className="max-w-[calc(100vw-1.5rem)]" />
          </TimePicker>
          {errors.hora_inicio && (
            <p id="hora-inicio-error" className="text-[11px] font-medium text-danger-fg">{errors.hora_inicio}</p>
          )}
        </div>

        <div className="min-w-0 space-y-1.5">
          <label htmlFor="ambientes" className="block text-xs font-bold text-foreground">
            Ambiente(s) *
          </label>
          <FormSelect
            id="ambientes"
            className="h-8 min-w-0 data-[size=default]:h-8"
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
            <p className="text-[11px] text-danger-fg font-medium">{errors.ambientes}</p>
          )}
        </div>
      </div>
    </div>
  );
};
