import { AlertTriangle, Check } from 'lucide-react'
import type { Group, SubjectCareerOption } from '../types/exams.types'

interface Props {
  selectedSubject?: SubjectCareerOption
  availableGroups: Group[]
  subjects?: SubjectCareerOption[]
  selectedGroupIds: number[]
  errors: Record<string, string>
  onToggleGroup: (groupId: number) => void
}

export function AssignGroupsForm({
  selectedSubject,
  availableGroups,
  subjects = [],
  selectedGroupIds,
  errors,
  onToggleGroup,
}: Props) {
  return (
    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
      <div className="border-b border-border px-4 py-3">
        <div className="min-w-0">
          <h2 className="text-base font-semibold text-foreground">Grupos</h2>
          <p className="truncate text-xs text-muted-foreground" title={`Grupos de ${selectedSubject?.nombre ?? 'la materia seleccionada'}`}>
            {selectedSubject?.nombre ?? 'Seleccione una materia'}
          </p>
        </div>
      </div>

      <div className="p-3">
        {errors.grupos && (
          <p role="alert" className="mb-4 flex gap-2 rounded-lg border border-danger-border bg-danger-soft p-3 text-xs font-medium text-danger-fg">
            <AlertTriangle className="size-4 shrink-0" />{errors.grupos}
          </p>
        )}
        {!selectedSubject ? (
          <p className="py-3 text-center text-sm text-muted-foreground">Seleccione primero una materia.</p>
        ) : availableGroups.length === 0 ? (
          <p className="py-3 text-center text-sm text-muted-foreground">No tiene grupos activos de esta materia en el periodo vigente.</p>
        ) : (
          <div className="grid max-h-40 gap-2 overflow-y-auto sm:grid-cols-2">
            {availableGroups.map((group) => {
              const selected = selectedGroupIds.includes(group.id_grupo)
              const career = subjects.find((subject) =>
                subject.id_materia === group.id_materia && subject.id_carrera === group.id_carrera
              )?.carrera

              return (
                <button
                  key={group.id_grupo}
                  type="button"
                  disabled={!group.tiene_nomina}
                  aria-pressed={selected}
                  onClick={() => onToggleGroup(group.id_grupo)}
                  className={`flex min-w-0 items-start gap-2 rounded-lg border-2 p-3 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-55 ${
                    selected ? 'border-primary bg-brand-soft' : 'border-border hover:border-primary hover:bg-sunken'
                  }`}
                >
                  <span aria-hidden="true" className={`mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border ${selected ? 'border-primary bg-primary text-primary-foreground' : 'border-border-strong bg-card'}`}>
                    {selected && <Check className="size-3 stroke-[3]" />}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-semibold text-foreground">Grupo {group.num_grupo}</span>
                    <span className="mt-0.5 block text-xs text-muted-foreground">
                      {career ?? 'Carrera no disponible'} · {group.tiene_nomina ? 'Nómina cargada' : 'Sin nómina · no seleccionable'}
                    </span>
                  </span>
                  <span className="shrink-0 text-right text-sm font-semibold text-foreground">
                    {group.tiene_nomina ? group.cantidad_estudiantes : '—'}
                    <span className="block text-xs font-normal text-muted-foreground">est.</span>
                  </span>
                </button>
              )
            })}
          </div>
        )}
      </div>
    </section>
  )
}
