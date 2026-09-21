import { useState, type FormEvent } from 'react'
import { AlertOctagon, Lock } from 'lucide-react'
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
  teacherName?: string
  studentCount?: number
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
  teacherName = 'P. Careaga',
  studentCount = 118,
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
      {/* Cabecera */}
      <div className="flex items-center justify-between px-6 py-3.5 border-b">
        <div className="flex flex-col pr-6">
          <h2 className="text-base font-semibold leading-none tracking-tight">Editar grupo</h2>
          <p className="text-xs text-muted-foreground mt-1">
            {subjectName} · Grupo {group.num_grupo}
          </p>
        </div>
      </div>

      {/* Formulario */}
      <form onSubmit={handleSubmit} noValidate className="p-5 flex flex-col gap-3.5">
        {/*
          Si hay errores, se despliega la alerta roja.
          De lo contrario, permanece la alerta azul informativa.
        */}
        {hasError ? (
          <Alert className="border-[#A21B12]/20 bg-[#FDE2E1] text-[#A21B12] py-2 px-3">
            <svg className="size-4 text-[#A21B12] shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M12 16h.01"></path>
              <path d="M12 8v4"></path>
              <path d="M15.312 2a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586l-4.688-4.688A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2z"></path>
            </svg>
            <AlertDescription className="text-xs font-medium text-[#A21B12] leading-tight">
              {error !== null && !error.isValidation
                ? error.message
                : `Revise ${totalErrors} ${totalErrors === 1 ? 'campo' : 'campos'} antes de guardar los cambios.`}
            </AlertDescription>
          </Alert>
        ) : (
          <Alert variant="info" className="py-2.5 px-3">
            <svg className="size-4 shrink-0 text-sky-600 mt-0.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M12 16v-4"></path>
              <path d="M12 8h.01"></path>
            </svg>
            <div className="flex flex-col gap-0.5">
              <span className="text-xs font-semibold leading-tight text-sky-900">
                Los {studentCount} estudiantes inscritos no se modifican
              </span>
              <AlertDescription className="text-xs text-sky-700 leading-tight">
                Solo cambian los datos del grupo.
              </AlertDescription>
            </div>
          </Alert>
        )}

        {/* Campos de Solo Lectura: Materia de Origen y Docente */}
        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <ReadOnlyField label="Materia de origen" value={subjectCareerLabel} hint="No se puede cambiar." />
          <ReadOnlyField label="Docente" value={teacherName} />
        </div>

        {/* Campos Editables: N° de grupo y Período académico */}
        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          {/* N° de grupo */}
          <div className="flex flex-col gap-1">
            <Label htmlFor="num_grupo" className="text-xs font-medium text-[#2C2C2C]">
              N° de grupo <span className="text-destructive" aria-hidden="true">*</span>
            </Label>
            <Input
              id="num_grupo"
              value={numGrupo}
              maxLength={5}
              disabled={isSubmitting}
              aria-invalid={Boolean(numGrupoError)}
              onChange={(e) => setNumGrupo(e.target.value)}
              className={`h-8.5 px-3 py-1.5 text-xs bg-card text-[#2C2C2C] ${
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
              value={String(idPeriodo)}
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
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {periodoError && (
              <p className="flex items-center gap-1 text-xs text-[#A21B12] mt-0.5">
                <AlertOctagon className="size-3.5 shrink-0" aria-hidden="true" />
                <span>{periodoError}</span>
              </p>
            )}
          </div>
        </div>

        {/* Acciones */}
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

function ReadOnlyField({
  label,
  value,
  hint,
}: {
  label: string
  value: string
  hint?: string
}) {
  return (
    <div className="flex flex-col gap-1">
      <Label className="text-xs font-medium text-[#2C2C2C]">{label}</Label>
      <div className="flex items-center gap-2 rounded-md border border-input bg-[#F3F8F8] px-3 py-1.5 h-8.5 text-xs text-[#4F5B62]">
        <Lock className="size-3.5 shrink-0 text-[#4F5B62]" aria-hidden="true" />
        <span className="truncate font-normal">{value}</span>
      </div>
      {hint && <p className="text-xs text-muted-foreground leading-normal">{hint}</p>}
    </div>
  )
}