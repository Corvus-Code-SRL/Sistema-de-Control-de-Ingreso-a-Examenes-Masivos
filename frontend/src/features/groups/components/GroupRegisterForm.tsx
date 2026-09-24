import { useState, useEffect, type FormEvent } from 'react'
import { AlertOctagon, Upload } from 'lucide-react'
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
import { useCreateGroup } from '../hooks/useCreateGroup'
import { usePeriods } from '../hooks/usePeriods'
import type { GroupMutationResponse } from '../types/group.types'

export interface RegistrarGrupoFormProps {
  careerId: number
  subjectId: number
  /** "Bases de Datos I · Ing. de Sistemas", ya resuelto por quien monta el formulario. */
  subjectCareerLabel: string
  /** "Bases de Datos I": nombre corto de la materia, para el subtítulo del modal. */
  subjectName: string
  /** Nombre del docente actual, para el campo de solo lectura "Docente". */
  teacherName: string
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
  subjectName,
  teacherName,
  onCancel,
  onRegistered,
}: RegistrarGrupoFormProps) {
  const { periods, activePeriodId, isLoading: isLoadingPeriods } = usePeriods()
  const { status, error, submit } = useCreateGroup()

  const [numGrupo, setNumGrupo] = useState('')
  const [idPeriodo, setIdPeriodo] = useState<number | null>(activePeriodId)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})

  useEffect(() => {
    if (idPeriodo === null && activePeriodId !== null) {
      setIdPeriodo(activePeriodId)
    }
  }, [activePeriodId, idPeriodo])

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

  const backendNumGrupoError = error?.isValidation ? error.errors.num_grupo?.[0] : undefined
  const backendPeriodoError = error?.isValidation ? error.errors.id_periodo?.[0] : undefined
  // El par materia-carrera es contexto, no un campo del formulario: si el backend lo
  // rechaza (inexistente o inactivo) llega con la clave "id_materia" y se muestra en
  // la alerta global, no debajo de un input.
  const backendMateriaError = error?.isValidation ? error.errors.id_materia?.[0] : undefined

  const numGrupoError = fieldErrors.num_grupo ?? backendNumGrupoError
  const periodoError = fieldErrors.id_periodo ?? backendPeriodoError

  const totalErrors = [numGrupoError, periodoError].filter(Boolean).length
  const showAlert =
    totalErrors > 0 || backendMateriaError !== undefined || (error !== null && !error.isValidation)

  return (
    <div className="w-full flex flex-col">
      <div className="flex items-center justify-between px-6 py-3.5 border-b">
        <div className="flex flex-col pr-6">
          <h2 className="text-base font-semibold leading-none tracking-tight">Nuevo grupo</h2>
          <p className="text-xs text-muted-foreground mt-1">{subjectName} · se asocia a esta materia</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} noValidate className="p-5 flex flex-col gap-3.5">
        {showAlert && (
          <Alert variant="destructive" className="py-2 px-3">
            <AlertOctagon aria-hidden="true" />
            <AlertDescription className="text-xs font-medium leading-tight">
              {backendMateriaError
                ? backendMateriaError
                : error !== null && !error.isValidation
                  ? error.message
                  : `Revise ${totalErrors} ${totalErrors === 1 ? 'campo' : 'campos'} antes de registrar el grupo.`}
            </AlertDescription>
          </Alert>
        )}

        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <ReadOnlyField label="Materia y carrera" value={subjectCareerLabel} />
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
              placeholder="Ej. 1, 2, A..."
              maxLength={5}
              disabled={isSubmitting}
              aria-invalid={Boolean(numGrupoError)}
              onChange={(event) => setNumGrupo(event.target.value)}
              className={`h-8.5 px-3 py-1.5 text-xs bg-card text-foreground placeholder:text-muted-foreground/60 ${
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
              value={idPeriodo !== null ? String(idPeriodo) : undefined}
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
                    {period.id_periodo === activePeriodId ? ' (activo)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {periodoError ? (
              <p className="flex items-center gap-1 text-xs text-danger-fg mt-0.5">
                <AlertOctagon className="size-3.5 shrink-0" aria-hidden="true" />
                <span>{periodoError}</span>
              </p>
            ) : (
              <p className="text-xs text-muted-foreground leading-normal">
                Por defecto, el período activo. Verifíquelo antes de confirmar.
              </p>
            )}
          </div>
        </div>

        {/* Nómina de estudiantes (placeholder visual - HU-021) */}
        <div className="flex flex-col gap-1">
          <Label className="text-xs font-medium text-foreground">
            Nómina de estudiantes <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <RosterDropzonePlaceholder />
          <p className="text-xs text-muted-foreground leading-normal">
            CSV o XLSX, hasta 5 MB. Si no la adjunta ahora, el grupo queda «Sin nómina cargada».
          </p>
        </div>

        <div className="flex justify-end gap-2 pt-3 border-t mt-1">
          <Button type="button" variant="secondary" size="sm" disabled={isSubmitting} onClick={onCancel} className="text-xs">
            Cancelar
          </Button>
          <Button type="submit" size="sm" disabled={isSubmitting} className="text-xs">
            {isSubmitting ? 'Registrando…' : 'Registrar grupo'}
          </Button>
        </div>
      </form>
    </div>
  )
}

function RosterDropzonePlaceholder() {
  return (
    <div
      className="flex items-center justify-center gap-2 rounded-md border border-dashed border-input px-4 py-3 text-xs text-muted-foreground bg-muted/20"
      aria-disabled="true"
    >
      <Upload className="size-3.5 text-brand" aria-hidden="true" />
      <span className="text-xs">
        <span className="text-brand font-medium underline underline-offset-2 cursor-pointer">Seleccionar archivo</span> o arrastrarlo aquí
      </span>
    </div>
  )
}
