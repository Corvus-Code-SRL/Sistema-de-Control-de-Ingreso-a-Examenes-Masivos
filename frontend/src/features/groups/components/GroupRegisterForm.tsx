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
import { useCreateGroup } from '../hooks/useCreateGroup'
import { usePeriods } from '../hooks/usePeriods'
import type { GroupMutationResponse } from '../types/group.types'

export interface RegistrarGrupoFormProps {
  careerId: number
  subjectId: number
  /** "Bases de Datos I · Ing. de Sistemas", ya resuelto por quien monta el formulario. */
  subjectCareerLabel: string
  onCancel: () => void
  onRegistered: (group: GroupMutationResponse['data']) => void
}

interface FieldErrors {
  num_grupo?: string
  id_periodo?: string
}

export function RegistrarGrupoForm({
  careerId,
  subjectId,
  subjectCareerLabel,
  onCancel,
  onRegistered,
}: RegistrarGrupoFormProps) {
  const { periods, activePeriodId, isLoading: isLoadingPeriods } = usePeriods()
  const { status, error, submit } = useCreateGroup()

  const [numGrupo, setNumGrupo] = useState('')
  const [idPeriodo, setIdPeriodo] = useState<number | null>(activePeriodId)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})

  const isSubmitting = status === 'submitting'

  function validate(): boolean {
    const errors: FieldErrors = {}

    if (numGrupo.trim().length === 0) {
      errors.num_grupo = 'Debe indicarse el número de grupo.'
    } else if (numGrupo.length > 5) {
      errors.num_grupo = 'El número de grupo no puede superar los 5 caracteres.'
    }

    if (idPeriodo === null) {
      errors.id_periodo = 'Debe seleccionarse un período académico.'
    }

    setFieldErrors(errors)
    return Object.keys(errors).length === 0
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()

    if (!validate() || idPeriodo === null) {
      return
    }

    const result = await submit({
      id_carrera: careerId,
      id_materia: subjectId,
      num_grupo: numGrupo,
      id_periodo: idPeriodo,
    })

    if (result !== null) {
      onRegistered(result)
    }
  }

  // Errores 422 del backend (duplicidad, período inválido) se resaltan igual
  // que los de validación local: en el mismo campo, dentro del contenedor.
  const backendNumGrupoError = error?.isValidation ? error.errors.num_grupo?.[0] : undefined
  const backendPeriodoError = error?.isValidation ? error.errors.id_periodo?.[0] : undefined

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
      {error !== null && !error.isValidation && (
        <div role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
          {error.message}
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <Label>Materia y carrera</Label>
        <Input value={subjectCareerLabel} readOnly disabled aria-readonly />
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
        <p className="text-xs text-muted-foreground">Único dentro de la materia y la carrera.</p>
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="id_periodo">
          Período académico <span aria-hidden="true">*</span>
        </Label>
        <Select
          disabled={isSubmitting || isLoadingPeriods}
          value={idPeriodo !== null ? String(idPeriodo) : undefined}
          onValueChange={(value) => setIdPeriodo(Number(value))}
        >
          <SelectTrigger id="id_periodo" aria-invalid={Boolean(fieldErrors.id_periodo ?? backendPeriodoError)}>
            <SelectValue placeholder="Seleccione un período" />
          </SelectTrigger>
          <SelectContent>
            {periods.map((period) => (
              <SelectItem key={period.id_periodo} value={String(period.id_periodo)}>
                {period.nombre_periodo}-{period.gestion}
                {period.id_periodo === activePeriodId ? ' (activo)' : ''}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {(fieldErrors.id_periodo ?? backendPeriodoError) && (
          <p className="text-sm text-destructive">{fieldErrors.id_periodo ?? backendPeriodoError}</p>
        )}
      </div>

      <div className="flex justify-end gap-2 pt-2">
        <Button type="button" variant="secondary" disabled={isSubmitting} onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? 'Registrando…' : 'Registrar grupo'}
        </Button>
      </div>
    </form>
  )
}