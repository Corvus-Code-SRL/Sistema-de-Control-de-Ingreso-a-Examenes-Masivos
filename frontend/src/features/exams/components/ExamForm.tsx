import type { CreateExamFormData, SubjectCareerOption } from '../types/exams.types'
import { localToday, MAX_DURATION_MINUTES } from '../utils/examValidators'
import { FormSelect } from '@/components/common/FormSelect'
import { CalendarDays, Clock3 } from 'lucide-react'

interface Props {
  formData: CreateExamFormData
  subjects: SubjectCareerOption[]
  errors: Record<string, string>
  updateFormData: (fields: Partial<CreateExamFormData>) => void
}

const inputClass = (invalid: boolean, empty = false) =>
  `h-9 w-full rounded-lg border bg-card px-3 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-ring ${empty ? 'text-muted-foreground' : 'text-foreground'} ${invalid ? 'border-danger' : 'border-border-strong'}`

const pickerClass = (invalid: boolean) =>
  `relative flex h-9 items-center justify-between rounded-lg border bg-card px-3 text-sm focus-within:ring-2 focus-within:ring-ring ${invalid ? 'border-danger' : 'border-border-strong'}`

export function ExamForm({ formData, subjects, errors, updateFormData }: Props) {
  const [hour, minute] = formData.hora_inicio.split(':').map(Number)
  const displayedTime = formData.hora_inicio
    ? `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${hour < 12 ? 'AM' : 'PM'}`
    : 'hh:mm AM/PM'

  return (
    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
      <div className="border-b border-border px-4 py-3">
        <h2 className="text-base font-semibold text-foreground">Datos generales</h2>
      </div>

      <div className="space-y-3 p-4">
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1">
            <label htmlFor="materia" className="block text-sm font-medium text-foreground">Materia <span className="text-danger">*</span></label>
            <FormSelect
              id="materia"
              className="h-9 border-border-strong bg-card px-3 text-sm font-normal data-[size=default]:h-9"
              value={formData.materia ? String(formData.materia.id_materia) : undefined}
              placeholder="Seleccionar materia..."
              invalid={Boolean(errors.materia)}
              options={subjects.filter((subject, index) =>
                subjects.findIndex((item) => item.id_materia === subject.id_materia) === index
              ).map((subject) => ({ value: String(subject.id_materia), label: subject.nombre }))}
              onValueChange={(value) => {
                const pair = subjects.find((subject) => String(subject.id_materia) === value)
                updateFormData({ materia: pair ? { id_carrera: pair.id_carrera, id_materia: pair.id_materia } : null })
              }}
            />
            {errors.materia && <p className="text-xs font-medium text-danger">{errors.materia}</p>}
          </div>
          <div className="space-y-1">
            <label htmlFor="nombre_examen" className="block text-sm font-medium text-foreground">Nombre del examen <span className="text-danger">*</span></label>
            <input
              id="nombre_examen"
              type="text"
              placeholder="Ej. Primer Parcial"
              value={formData.nombre_examen}
              onChange={(event) => updateFormData({ nombre_examen: event.target.value })}
              className={inputClass(Boolean(errors.nombre_examen))}
            />
            {errors.nombre_examen && <p className="text-xs font-medium text-danger">{errors.nombre_examen}</p>}
          </div>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <div className="space-y-1">
            <label htmlFor="fecha" className="block text-sm font-medium text-foreground">Fecha <span className="text-danger">*</span></label>
            <div className={pickerClass(Boolean(errors.fecha))}>
              <span aria-hidden="true" className={formData.fecha ? 'text-foreground' : 'text-muted-foreground'}>
                {formData.fecha ? new Date(`${formData.fecha}T00:00:00`).toLocaleDateString() : 'Seleccionar fecha'}
              </span>
              <CalendarDays aria-hidden="true" className="size-4 text-muted-foreground" />
              <input id="fecha" type="date" lang="es-BO" min={localToday()} value={formData.fecha}
                onChange={(event) => updateFormData({ fecha: event.target.value })}
                onClick={(event) => event.currentTarget.showPicker?.()}
                className="absolute inset-0 size-full cursor-pointer opacity-0" />
            </div>
            {errors.fecha && <p className="text-xs font-medium text-danger">{errors.fecha}</p>}
          </div>
          <div className="space-y-1">
            <label htmlFor="hora_inicio" className="block text-sm font-medium text-foreground">Hora de inicio <span className="text-danger">*</span></label>
            <div className={pickerClass(Boolean(errors.hora_inicio))}>
              <span aria-hidden="true" className={formData.hora_inicio ? 'text-foreground' : 'text-muted-foreground'}>
                {displayedTime}
              </span>
              <Clock3 aria-hidden="true" className="size-4 text-muted-foreground" />
              <input id="hora_inicio" type="time" lang="es-BO" value={formData.hora_inicio}
                onChange={(event) => updateFormData({ hora_inicio: event.target.value })}
                onClick={(event) => event.currentTarget.showPicker?.()}
                className="absolute inset-0 size-full cursor-pointer opacity-0" />
            </div>
            {errors.hora_inicio && <p className="text-xs font-medium text-danger">{errors.hora_inicio}</p>}
          </div>
          <div className="space-y-1">
            <label htmlFor="duracion" className="block text-sm font-medium text-foreground">Duración <span className="text-danger">*</span></label>
            <div className="relative">
              <input id="duracion" type="number" min={1} max={MAX_DURATION_MINUTES} step={1}
                placeholder="90" value={formData.duracion || ''}
                onChange={(event) => {
                  const value = event.target.value
                  updateFormData({ duracion: value === '' ? 0 : parseInt(value, 10) || 0 })
                }}
                className={`${inputClass(Boolean(errors.duracion))} pr-12`} />
              <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">min</span>
            </div>
            {errors.duracion && <p className="text-xs font-medium text-danger">{errors.duracion}</p>}
          </div>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1">
            <label htmlFor="categoria" className="block text-sm font-medium text-foreground">Categoría <span className="text-danger">*</span></label>
            <FormSelect id="categoria" className="h-9 border-border-strong bg-card px-3 text-sm font-normal data-[size=default]:h-9" value={formData.categoria} placeholder="Seleccionar categoría"
              options={[
                { value: 'REGULAR', label: 'REGULAR' },
                { value: 'MESA', label: 'MESA' },
                { value: 'ADMISION', label: 'ADMISIÓN' },
              ]}
              onValueChange={(value) => updateFormData({ categoria: value as CreateExamFormData['categoria'] })} />
          </div>
          <div className="space-y-1">
            <label htmlFor="control_ingreso" className="block text-sm font-medium text-foreground">¿Realizar control de ingreso?</label>
            <div className="flex h-9 items-center gap-3 text-sm text-foreground">
              <input id="control_ingreso" type="checkbox" role="switch"
                checked={formData.control_ingreso ?? true}
                onChange={(event) => updateFormData({ control_ingreso: event.target.checked })}
                className="peer sr-only" />
              <label htmlFor="control_ingreso" aria-hidden="true" className="relative h-6 w-11 cursor-pointer rounded-full bg-muted ring-1 ring-border-strong transition-colors peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-ring after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-card after:shadow-sm after:transition-transform peer-checked:after:translate-x-5" />
              <span>{formData.control_ingreso === false ? 'No' : 'Sí'}</span>
            </div>
          </div>
        </div>
        {formData.control_ingreso !== false && (
          <div className="space-y-1">
            <label htmlFor="minutos_apertura" className="block text-sm font-medium text-foreground">Apertura del control de ingreso</label>
            <div className="relative">
              <input id="minutos_apertura" type="number" min={0} step={1}
                value={formData.minutos_apertura === null ? '' : formData.minutos_apertura ?? 10}
                onChange={(event) => updateFormData({
                  minutos_apertura: event.target.value === '' ? null : Number(event.target.value),
                })}
                className={`${inputClass(Boolean(errors.minutos_apertura))} pr-24`} />
              <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">min antes</span>
            </div>
            {errors.minutos_apertura && <p className="text-xs font-medium text-danger">{errors.minutos_apertura}</p>}
          </div>
        )}
      </div>
    </section>
  )
}
