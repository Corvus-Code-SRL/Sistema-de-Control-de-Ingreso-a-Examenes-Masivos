import { useState, useEffect, type FormEvent } from 'react'
import { AlertOctagon, Lock, Upload } from 'lucide-react'
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
  const [auxiliarySearch, setAuxiliarySearch] = useState('')
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
  // CA 7: GroupService::assertTeacherHasAccessToPair rechaza con la clave
  // "id_materia" cuando el docente no tiene ningún grupo previo en ese par.
  // No hay un input "id_materia" visible (es contexto, no un campo del
  // formulario), así que este error se muestra en la alerta global, no
  // debajo de un campo.
  const backendMateriaError = error?.isValidation ? error.errors.id_materia?.[0] : undefined

  const numGrupoError = fieldErrors.num_grupo ?? backendNumGrupoError
  const periodoError = fieldErrors.id_periodo ?? backendPeriodoError

  const totalErrors = [numGrupoError, periodoError].filter(Boolean).length
  // Antes solo consideraba totalErrors y "error genérico no-422": un 422 con
  // id_materia (sin num_grupo ni id_periodo) no encendía la alerta y el
  // formulario se quedaba mudo. Se agrega backendMateriaError a la condición.
  const showAlert = totalErrors > 0 || backendMateriaError !== undefined || (error !== null && !error.isValidation)

  return (
    <div className="w-full flex flex-col">
      {/* Cabecera */}
      <div className="flex items-center justify-between px-6 py-3.5 border-b">
        <div className="flex flex-col pr-6">
          <h2 className="text-base font-semibold leading-none tracking-tight">Nuevo grupo</h2>
          <p className="text-xs text-muted-foreground mt-1">{subjectName} · se asocia a esta materia</p>
        </div>
      </div>

      {/* Formulario */}
      <form onSubmit={handleSubmit} noValidate className="p-5 flex flex-col gap-3.5">
        {/* Alerta global de error (Alert Danger) */}
        {showAlert && (
          <Alert className="border-[#A21B12]/20 bg-[#FDE2E1] text-[#A21B12] py-2 px-3">
            <svg className="size-4 text-[#A21B12]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M12 16h.01"></path>
              <path d="M12 8v4"></path>
              <path d="M15.312 2a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586l-4.688-4.688A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2z"></path>
            </svg>
            <AlertDescription className="text-xs font-medium text-[#A21B12] leading-tight">
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
          {/* N° de grupo */}
          <div className="flex flex-col gap-1">
            <Label htmlFor="num_grupo" className="text-xs font-medium text-[#2C2C2C]">
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
              className={`h-8.5 px-3 py-1.5 text-xs bg-card text-[#2C2C2C] placeholder:text-muted-foreground/60 ${
                numGrupoError
                  ? 'border-[#A21B12] bg-[#FDE2E1]/20 focus-visible:ring-[#A21B12]'
                  : 'border-input'
              }`}
            />
            {numGrupoError ? (
              <p className="flex items-center gap-1 text-xs text-[#A21B12] mt-0.5">
                <AlertOctagon className="size-3.5 shrink-0" aria-hidden="true" />
                <span>{numGrupoError}</span>
              </p>
            ) : (
              <p className="text-xs text-muted-foreground leading-normal">Debe ser único dentro de la materia.</p>
            )}
          </div>

          {/* Período académico */}
          <div className="flex flex-col gap-1">
            <Label htmlFor="id_periodo" className="text-xs font-medium text-[#2C2C2C]">
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
                className={`w-full h-8.5 py-1.5 text-[#2C2C2C] text-xs bg-card ${
                  periodoError
                    ? 'border-[#A21B12] bg-[#FDE2E1]/20 focus:ring-[#A21B12]'
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
              <p className="flex items-center gap-1 text-xs text-[#A21B12] mt-0.5">
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

        {/* Auxiliares */}
        <div className="flex flex-col gap-1">
          <Label htmlFor="auxiliares_input" className="text-xs font-medium text-[#2C2C2C]">
            Auxiliares <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <Input
            id="auxiliares_input"
            value={auxiliarySearch}
            onChange={(e) => setAuxiliarySearch(e.target.value)}
            placeholder="Buscar por nombre o código SIS"
            disabled={isSubmitting}
            className="h-8.5 px-3 py-1.5 text-xs bg-card text-[#2C2C2C] border-input placeholder:text-muted-foreground/60 w-full"
          />
          <p className="text-xs text-muted-foreground leading-normal">Solo auxiliares registrados por el administrador.</p>
        </div>

        {/* Nómina de estudiantes (Placeholder visual - HU-021) */}
        <div className="flex flex-col gap-1">
          <Label className="text-xs font-medium text-[#2C2C2C]">
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

function ReadOnlyField({
  label,
  value,
}: {
  label: string
  value: string
}) {
  return (
    <div className="flex flex-col gap-1">
      <Label className="text-xs font-medium text-[#2C2C2C]">{label}</Label>
      <div className="flex items-center gap-2 rounded-md border border-input bg-[#F3F8F8] px-3 py-1.5 h-8.5 text-xs text-[#4F5B62]">
        <Lock className="size-3.5 shrink-0 text-[#4F5B62]" aria-hidden="true" />
        <span className="truncate font-normal">{value}</span>
      </div>
    </div>
  )
}

function RosterDropzonePlaceholder() {
  return (
    <div
      className="flex items-center justify-center gap-2 rounded-md border border-dashed border-input px-4 py-3 text-xs text-muted-foreground bg-muted/20"
      aria-disabled="true"
    >
      <Upload className="size-3.5 text-[#005E68]" aria-hidden="true" />
      <span className="text-xs">
        <span className="text-[#005E68] font-medium underline underline-offset-2 cursor-pointer">Seleccionar archivo</span> o arrastrarlo aquí
      </span>
    </div>
  )
}