import { DoorOpen } from 'lucide-react'
import type { Classroom, CreateExamFormData } from '../types/exams.types'

interface Props {
  classrooms: Classroom[]
  selectedRoomIds: number[]
  error?: string
  updateFormData: (fields: Partial<CreateExamFormData>) => void
}

export function ExamAmbientesForm({ classrooms, selectedRoomIds, error, updateFormData }: Props) {
  return (
    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
      <div className="border-b border-border px-4 py-3">
        <h2 className="text-base font-semibold text-foreground">Ambientes</h2>
      </div>

      <fieldset className="p-3">
        <legend className="sr-only">Ambiente(s) *</legend>
        {classrooms.length === 0 ? (
          <p className="py-3 text-center text-sm text-muted-foreground">No hay ambientes disponibles.</p>
        ) : (
          <div
            className={`grid gap-2 sm:grid-cols-2 ${classrooms.length > 6
              ? 'max-h-[280px] overflow-y-auto pr-1 sm:max-h-[208px]'
              : classrooms.length > 4
                ? 'max-h-[280px] overflow-y-auto pr-1 sm:max-h-none sm:overflow-visible sm:pr-0'
                : ''}`}
            {...(classrooms.length > 4 ? { role: 'region', 'aria-label': 'Lista de ambientes', tabIndex: 0 } : {})}
          >
            {classrooms.map((room) => {
              const selected = selectedRoomIds.includes(room.id_ambiente)
              return (
                <label key={room.id_ambiente} className={`flex h-16 min-w-0 cursor-pointer items-center gap-2 rounded-lg border-2 p-2 transition-colors ${
                  selected ? 'border-primary bg-brand-soft' : 'border-border hover:border-primary hover:bg-sunken'
                }`}>
                  <input
                    type="checkbox"
                    checked={selected}
                    onChange={(event) => updateFormData({
                      ambientes: event.target.checked
                        ? [...selectedRoomIds, room.id_ambiente]
                        : selectedRoomIds.filter((id) => id !== room.id_ambiente),
                    })}
                    className="size-4 shrink-0 rounded border-border-strong accent-brand"
                  />
                  <DoorOpen aria-hidden="true" className="size-7 shrink-0 rounded-lg bg-brand-soft p-1.5 text-brand" />
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-semibold text-foreground">Aula {room.nro_aula}</span>
                    {room.ubicacion && <span className="mt-0.5 block truncate text-xs text-muted-foreground" title={room.ubicacion}>{room.ubicacion}</span>}
                  </span>
                  <span className="shrink-0 text-right text-sm font-semibold text-foreground">
                    {room.capacidad}<span className="block text-xs font-normal text-muted-foreground">lugares</span>
                  </span>
                </label>
              )
            })}
          </div>
        )}
        {error && <p role="alert" className="mt-3 text-xs font-medium text-danger">{error}</p>}
      </fieldset>
    </section>
  )
}
