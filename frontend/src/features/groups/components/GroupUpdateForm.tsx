import { useState, type FormEvent } from 'react'
import { AlertOctagon, Info } from 'lucide-react'
import { ReadOnlyField } from '@/components/common/ReadOnlyField'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useUpdateGroup } from '../hooks/useUpdateGroup'
import type { Group, GroupMutationResponse } from '../types/group.types'

export interface ActualizarGrupoFormProps {
  group: Group
  subjectCareerLabel: string
  teacherName: string
  /**
   * Inscritos activos del grupo; se muestran para dejar claro que no se modifican. null mientras
   * no se conocen: se omite la cifra en lugar de mostrar una equivocada.
   */
  studentCount: number | null
  onCancel: () => void
  onUpdated: (group: GroupMutationResponse['data']) => void
  /** En una vista propia el título lo pone el encabezado de la página. */
  hideTitle?: boolean
  /** Un examen del grupo está en curso (HU-019, CA-5): se ve, pero no se puede guardar. */
  locked?: boolean
}

interface FieldErrors {
  num_grupo?: string
}

export function ActualizarGrupoForm({
  group,
  subjectCareerLabel,
  teacherName,
  studentCount,
  onCancel,
  onUpdated,
  hideTitle = false,
  locked = false,
}: ActualizarGrupoFormProps) {
  const { status, error, submit } = useUpdateGroup()

  const [numGrupo, setNumGrupo] = useState(group.num_grupo)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})

  const isSubmitting = status === 'submitting'
  const isDisabled = isSubmitting || locked

  function validate(): boolean {
    const errors: FieldErrors = {}

    if (numGrupo.trim().length === 0) {
      errors.num_grupo = 'Debe indicarse el número de grupo.'
    } else if (numGrupo.length > 5) {
      errors.num_grupo = 'El número de grupo no puede superar los 5 caracteres.'
    }

    setFieldErrors(errors)
    return Object.keys(errors).length === 0
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()

    if (locked || !validate()) {
      return
    }

    // Solo el número es editable: el período del grupo no cambia (HU-006).
    const result = await submit(group.id_grupo, { num_grupo: numGrupo })

    if (result !== null) {
      onUpdated(result)
    }
  }

  const backendNumGrupoError = error?.isValidation ? error.errors.num_grupo?.[0] : undefined
  const backendPeriodoError = error?.isValidation ? error.errors.id_periodo?.[0] : undefined

  const numGrupoError = fieldErrors.num_grupo ?? backendNumGrupoError

  const totalErrors = [numGrupoError, backendPeriodoError].filter(Boolean).length
  // Un rechazo que no señala un campo (por ejemplo, el bloqueo por examen) se explica con su mensaje.
  const hasFieldError = backendNumGrupoError !== undefined || backendPeriodoError !== undefined
  const showsGeneralError = error !== null && (!error.isValidation || !hasFieldError)
  const generalError = error !== null && showsGeneralError ? error.message : null
  const hasError = totalErrors > 0 || generalError !== null

  const subjectName = subjectCareerLabel.split('·')[0]?.trim() || 'Materia'

  return (
    <div className="w-full flex flex-col">
      {!hideTitle && (
        <div className="flex items-center justify-between px-6 py-3.5 border-b">
          <div className="flex flex-col pr-6">
            <h2 className="text-base font-semibold leading-none tracking-tight">Editar grupo</h2>
            <p className="text-xs text-muted-foreground mt-1">
              {subjectName} · Grupo {group.num_grupo}
            </p>
          </div>
        </div>
      )}

      <form onSubmit={handleSubmit} noValidate className="p-5 flex flex-col gap-3.5">
        {hasError ? (
          <Alert variant="destructive" className="py-2 px-3">
            <AlertOctagon aria-hidden="true" />
            <AlertDescription className="text-xs font-medium leading-tight">
              {totalErrors === 0 && generalError !== null
                ? generalError
                : `Revise ${totalErrors} ${totalErrors === 1 ? 'campo' : 'campos'} antes de guardar los cambios.`}
            </AlertDescription>
          </Alert>
        ) : (
          <Alert variant="info" className="py-2.5 px-3">
            <Info aria-hidden="true" />
            <div className="flex flex-col gap-0.5">
              {studentCount !== null && (
                <span className="text-xs font-semibold leading-tight">
                  Los {studentCount} estudiantes inscritos no se modifican
                </span>
              )}
              <AlertDescription className="text-xs leading-tight">
                Solo cambian los datos del grupo.
              </AlertDescription>
            </div>
          </Alert>
        )}

        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <div className="flex flex-col gap-1">
            <Label htmlFor="num_grupo" className="text-xs font-medium text-foreground">
              N° de grupo <span className="text-destructive" aria-hidden="true">*</span>
            </Label>
            <Input
              id="num_grupo"
              value={numGrupo}
              disabled={isDisabled}
              aria-invalid={Boolean(numGrupoError)}
              onChange={(e) => setNumGrupo(e.target.value)}
              className={`h-8.5 px-3 py-1.5 text-xs bg-card text-foreground ${
                numGrupoError
                  ? 'border-danger-fg bg-danger-soft/20 focus-visible:ring-danger-fg'
                  : 'border-input'
              }`}
            />
            {numGrupoError ? (
              <p className="flex items-center gap-1 text-xs text-danger-fg mt-0.5">
                <AlertOctagon className="size-3.5 shrink-0" aria-hidden="true" />
                <span>{numGrupoError}</span>
              </p>
            ) : (
              <p className="text-xs text-muted-foreground leading-normal">
                Debe ser único dentro de la materia, la carrera, la gestión y el período.
              </p>
            )}
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
          <ReadOnlyField label="Materia de origen" value={subjectCareerLabel} hint="No se puede cambiar." />
          <ReadOnlyField label="Docente" value={teacherName} />
          <div className="flex flex-col gap-1">
            <ReadOnlyField
              label="Período académico"
              value={group.periodo.etiqueta}
              hint={group.periodo.nombre_periodo}
            />
            {backendPeriodoError && (
              <p className="flex items-center gap-1 text-xs text-danger-fg mt-0.5">
                <AlertOctagon className="size-3.5 shrink-0" aria-hidden="true" />
                <span>{backendPeriodoError}</span>
              </p>
            )}
          </div>
        </div>

        <div className="flex justify-end gap-2 pt-3 border-t mt-1">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={isSubmitting}
            onClick={onCancel}
            className="text-xs"
          >
            Cancelar
          </Button>
          <Button type="submit" size="sm" disabled={isDisabled} className="text-xs">
            {isSubmitting ? 'Guardando…' : 'Guardar cambios'}
          </Button>
        </div>
      </form>
    </div>
  )
}
