import { useState, type FormEvent } from 'react'
import { AlertOctagon, Info } from 'lucide-react'
import { ReadOnlyField } from '@/components/common/ReadOnlyField'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useUpdateGroup } from '../hooks/useUpdateGroup'
import { usePeriods } from '../hooks/usePeriods'
import type { Group, GroupMutationResponse } from '../types/group.types'

export interface ActualizarGrupoFormProps {
  group: Group
  subjectCareerLabel: string
  teacherName: string
  /** Inscritos activos del grupo; se muestran para dejar claro que no se modifican. */
  studentCount: number
  onCancel: () => void
  onUpdated: (group: GroupMutationResponse['data']) => void
}

interface FieldErrors {
  num_grupo?: string
  id_periodo?: string
}

export function ActualizarGrupoForm({
  group,
  subjectCareerLabel,
  teacherName,
  studentCount,
  onCancel,
  onUpdated,
}: ActualizarGrupoFormProps) {
  const { periods, isLoading: isLoadingPeriods } = usePeriods()
  const { status, error, submit } = useUpdateGroup()

  const [numGrupo, setNumGrupo] = useState(group.num_grupo)
  const [idPeriodo, setIdPeriodo] = useState<number>(group.periodo.id_periodo)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})

  const isSubmitting = status === 'submitting'

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

    if (!validate()) {
      return
    }

    const result = await submit(group.id_grupo, {
      num_grupo: numGrupo,
      id_periodo: idPeriodo,
    })

    if (result !== null) {
      onUpdated(result)
    }
  }

  const backendNumGrupoError = error?.isValidation ? error.errors.num_grupo?.[0] : undefined
  const backendPeriodoError = error?.isValidation ? error.errors.id_periodo?.[0] : undefined

  const numGrupoError = fieldErrors.num_grupo ?? backendNumGrupoError
  const periodoError = fieldErrors.id_periodo ?? backendPeriodoError

  const totalErrors = [numGrupoError, periodoError].filter(Boolean).length
  const hasError = totalErrors > 0 || (error !== null && !error.isValidation)

  const subjectName = subjectCareerLabel.split('·')[0]?.trim() || 'Materia'

  return (
    <div className="w-full flex flex-col">
      <div className="flex items-center justify-between px-6 py-3.5 border-b">
        <div className="flex flex-col pr-6">
          <h2 className="text-base font-semibold leading-none tracking-tight">Editar grupo</h2>
          <p className="text-xs text-muted-foreground mt-1">
            {subjectName} · Grupo {group.num_grupo}
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit} noValidate className="p-5 flex flex-col gap-3.5">
        {hasError ? (
          <Alert variant="destructive" className="py-2 px-3">
            <AlertOctagon aria-hidden="true" />
            <AlertDescription className="text-xs font-medium leading-tight">
              {error !== null && !error.isValidation
                ? error.message
                : `Revise ${totalErrors} ${totalErrors === 1 ? 'campo' : 'campos'} antes de guardar los cambios.`}
            </AlertDescription>
          </Alert>
        ) : (
          <Alert variant="info" className="py-2.5 px-3">
            <Info aria-hidden="true" />
            <div className="flex flex-col gap-0.5">
              <span className="text-xs font-semibold leading-tight">
                Los {studentCount} estudiantes inscritos no se modifican
              </span>
              <AlertDescription className="text-xs leading-tight">
                Solo cambian los datos del grupo.
              </AlertDescription>
            </div>
          </Alert>
        )}

        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <ReadOnlyField label="Materia de origen" value={subjectCareerLabel} hint="No se puede cambiar." />
          <ReadOnlyField label="Docente" value={teacherName} />
        </div>

        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <div className="flex flex-col gap-1">
            <Label htmlFor="num_grupo" className="text-xs font-medium text-foreground">
              N° de grupo <span className="text-destructive" aria-hidden="true">*</span>
            </Label>
            <Input
              id="num_grupo"
              value={numGrupo}
              maxLength={5}
              disabled={isSubmitting}
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
                Debe ser único dentro de la materia, la carrera y el período.
              </p>
            )}
          </div>

          <div className="flex flex-col gap-1">
            <Label htmlFor="id_periodo" className="text-xs font-medium text-foreground">
              Período académico <span className="text-destructive" aria-hidden="true">*</span>
            </Label>
            <Select
              disabled={isSubmitting || isLoadingPeriods}
              value={String(idPeriodo)}
              onValueChange={(value) => setIdPeriodo(Number(value))}
            >
              <SelectTrigger
                id="id_periodo"
                aria-invalid={Boolean(periodoError)}
                className={`w-full h-8.5 py-1.5 text-foreground text-xs bg-card ${
                  periodoError
                    ? 'border-danger-fg bg-danger-soft/20 focus:ring-danger-fg'
                    : 'border-input'
                }`}
              >
                <SelectValue placeholder="Seleccione un período" />
              </SelectTrigger>
              <SelectContent>
                {periods.map((period) => (
                  <SelectItem key={period.id_periodo} value={String(period.id_periodo)} className="text-xs">
                    {period.nombre_periodo}-{period.gestion}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {periodoError && (
              <p className="flex items-center gap-1 text-xs text-danger-fg mt-0.5">
                <AlertOctagon className="size-3.5 shrink-0" aria-hidden="true" />
                <span>{periodoError}</span>
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
          <Button type="submit" size="sm" disabled={isSubmitting} className="text-xs">
            {isSubmitting ? 'Guardando…' : 'Guardar cambios'}
          </Button>
        </div>
      </form>
    </div>
  )
}
