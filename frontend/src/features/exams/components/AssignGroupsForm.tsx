import { AlertTriangle, Check } from 'lucide-react'
import type { Group, SubjectCareerOption } from '../types/exams.types'

interface Props {
  selectedSubject?: SubjectCareerOption
  availableGroups: Group[]
  selectedGroupIds: number[]
  errors: Record<string, string>
  onToggleGroup: (groupId: number) => void
}

export function AssignGroupsForm({
  selectedSubject,
  availableGroups,
  selectedGroupIds,
  errors,
  onToggleGroup,
}: Props) {
  const subjectName = selectedSubject
    ? `${selectedSubject.nombre} — ${selectedSubject.carrera}`
    : 'la materia seleccionada'

  return (
    <section className="bg-card rounded-xl border border-border p-8 shadow-xs space-y-5">
      <div>
        <h2 className="text-base font-bold text-foreground">Grupos participantes</h2>
        <p className="text-xs text-muted-foreground mt-1">
          Elija sus grupos de <strong>{subjectName}</strong> que rendirán el examen.
        </p>
      </div>

      {errors.grupos && (
        <div
          role="alert"
          className="p-3.5 rounded-lg bg-danger-soft border border-danger/20 text-danger-fg text-xs font-medium flex gap-2"
        >
          <AlertTriangle className="h-4 w-4 shrink-0" />
          <span>{errors.grupos}</span>
        </div>
      )}

      {!selectedSubject ? (
        <p className="py-6 text-center text-xs text-muted-foreground">
          Seleccione primero una materia junto con su carrera.
        </p>
      ) : availableGroups.length === 0 ? (
        <p className="py-6 text-center text-xs text-muted-foreground">
          No tiene grupos activos de este par en el periodo vigente.
        </p>
      ) : (
        <div className="divide-y divide-border border border-border rounded-lg overflow-hidden">
          {availableGroups.map((group) => {
            const selected = selectedGroupIds.includes(group.id_grupo)

            return (
              <button
                key={group.id_grupo}
                type="button"
                disabled={!group.tiene_nomina}
                onClick={() => onToggleGroup(group.id_grupo)}
                className="w-full px-4 py-3.5 flex items-center justify-between gap-3 text-left hover:bg-muted/60 disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60"
              >
                <span className="flex items-center gap-3">
                  <span
                    aria-hidden="true"
                    className={`h-5 w-5 rounded border flex items-center justify-center ${
                      selected
                        ? 'bg-primary border-primary text-primary-foreground'
                        : 'bg-card border-border'
                    }`}
                  >
                    {selected && <Check className="h-3.5 w-3.5 stroke-[3]" />}
                  </span>
                  <span>
                    <span className="block text-xs font-bold text-foreground">
                      Grupo {group.num_grupo}
                    </span>
                    <span className="block text-[11px] text-muted-foreground">
                      Gestión {group.gestion}
                    </span>
                  </span>
                </span>

                {group.tiene_nomina ? (
                  <span className="text-right">
                    <span className="block text-xs font-bold text-foreground">
                      {group.cantidad_estudiantes}
                    </span>
                    <span className="block text-[11px] text-muted-foreground">estudiantes activos</span>
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-warn-fg">
                    <AlertTriangle className="h-3.5 w-3.5" />
                    Sin nómina
                  </span>
                )}
              </button>
            )
          })}
        </div>
      )}
    </section>
  )
}
