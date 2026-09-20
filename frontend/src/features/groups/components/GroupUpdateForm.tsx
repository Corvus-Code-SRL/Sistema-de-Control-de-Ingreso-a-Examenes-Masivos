import { useState, type FormEvent } from 'react'
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
  /** "Bases de Datos I · Ing. de Sistemas": campo inmutable, solo lectura (CA 11). */
  subjectCareerLabel: string
  onCancel: () => void
  onUpdated: (group: GroupMutationResponse['data']) => void
}

interface FieldErrors {
  num_grupo?: string
  id_periodo?: string
}

/**
 * Actualizar grupo académico.
 *
 * Precargado con los datos actuales del grupo (CA 2). Materia/carrera se
 * muestran como read-only explícito (CA 11): son inmutables aunque el
 * usuario intente cambiarlas, el backend las ignora igual.
 */
export function ActualizarGrupoForm({
  group,
  subjectCareerLabel,
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

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
      {error !== null && error.isForbidden && (
        <div role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
          No tiene permiso para actualizar este grupo.
        </div>
      )}
      {error !== null && !error.isValidation && !error.isForbidden && (
        <div role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
          {error.message}
        </div>
      )}

      {/* CA 11 — campo de solo lectura marcado explícitamente como tal. */}
      <div className="flex flex-col gap-1.5">
        <Label>Materia y carrera</Label>
        <Input value={subjectCareerLabel} readOnly disabled aria-readonly />
        <p className="text-xs text-muted-foreground">Este dato no puede modificarse.</p>
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="num_grupo">
          N.° de grupo <span aria-hidden="true">*</span>
        </Label>
        <Input
          id="num_grupo"
          value={numGrupo}
          maxLength={5}
          disabled={isSubmitting}
          aria-invalid={Boolean(fieldErrors.num_grupo ?? backendNumGrupoError)}
          onChange={(event) => setNumGrupo(event.target.value)}
        />
        {(fieldErrors.num_grupo ?? backendNumGrupoError) && (
          <p className="text-sm text-destructive">{fieldErrors.num_grupo ?? backendNumGrupoError}</p>
        )}
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="id_periodo">Período académico</Label>
        <Select
          disabled={isSubmitting || isLoadingPeriods}
          value={String(idPeriodo)}
          onValueChange={(value) => setIdPeriodo(Number(value))}
        >
          <SelectTrigger id="id_periodo">
            <SelectValue placeholder="Seleccione un período" />
          </SelectTrigger>
          <SelectContent>
            {periods.map((period) => (
              <SelectItem key={period.id_periodo} value={String(period.id_periodo)}>
                {period.nombre_periodo}-{period.gestion}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="secondary" disabled={isSubmitting} onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Actualizando…' : 'Actualizar grupo'}
        </Button>
      </div>
    </form>
  )
}