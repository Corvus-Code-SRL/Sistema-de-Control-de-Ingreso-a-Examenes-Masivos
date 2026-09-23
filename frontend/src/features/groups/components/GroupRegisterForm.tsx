import { useEffect, useState, type FormEvent } from 'react'
import {
  AlertOctagon,
  Download,
  FileText,
  Lock,
  Upload,
} from 'lucide-react'
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
import { RosterStandalonePreview } from '@/features/students'
import {
  previewStandaloneRoster,
  validateRosterFile,
} from '@/features/students/services/rosterService'
import { countRosterRows } from '@/features/students/utils/rosterRows'
import type { RosterPreviewData } from '@/features/students/types/roster.types'
import { ApiError } from '@/lib/api-client'
import { useCreateGroup } from '../hooks/useCreateGroup'
import { usePeriods } from '../hooks/usePeriods'
import type { GroupMutationResponse } from '../types/group.types'

export interface RegistrarGrupoFormProps {
  careerId: number
  subjectId: number
  subjectCareerLabel: string
  subjectName: string
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

  // Nómina adjunta
  const [rosterFile, setRosterFile] = useState<File | null>(null)
  const [rosterPreview, setRosterPreview] = useState<RosterPreviewData | null>(null)
  const [rosterError, setRosterError] = useState<string | null>(null)
  const [rosterErrorTitle, setRosterErrorTitle] = useState<string | null>(null)
  const [isPreviewing, setIsPreviewing] = useState(false)

  // Auxiliar para filtrar la lista de estudiantes incorporables en el preview
  const [auxiliarySearch, setAuxiliarySearch] = useState('')

  useEffect(() => {
    if (idPeriodo === null && activePeriodId !== null) {
      setIdPeriodo(activePeriodId)
    }
  }, [activePeriodId, idPeriodo])

  const isSubmitting = status === 'submitting'
  const hasRoster = rosterFile !== null || rosterPreview !== null || rosterError !== null

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

  async function handleFileSelected(file: File) {
    const rejection = validateRosterFile(file)

    if (rejection !== null) {
      setRosterFile(file)
      setRosterPreview(null)
      setRosterError(rejection)
      setRosterErrorTitle('No se pudo procesar el archivo')
      return
    }

    setRosterFile(file)
    setRosterPreview(null)
    setRosterError(null)
    setRosterErrorTitle(null)
    setIsPreviewing(true)

    try {
      const response = await previewStandaloneRoster(file)
      setRosterPreview(response.data)
    } catch (cause) {
      const apiError = cause instanceof ApiError ? cause : null
      setRosterError(
        apiError?.errors?.archivo?.[0] ??
          apiError?.message ??
          'No se pudo procesar el archivo.'
      )
      setRosterErrorTitle('No se pudo procesar el archivo')
    } finally {
      setIsPreviewing(false)
    }
  }

  function clearRoster() {
    setRosterFile(null)
    setRosterPreview(null)
    setRosterError(null)
    setRosterErrorTitle(null)
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
      token: rosterPreview?.token,
    })

    if (result !== null) {
      onRegistered(result)
    }
  }

  const backendNumGrupoError = error?.isValidation ? error.errors.num_grupo?.[0] : undefined
  const backendPeriodoError = error?.isValidation ? error.errors.id_periodo?.[0] : undefined
  const backendMateriaError = error?.isValidation ? error.errors.id_materia?.[0] : undefined

  const numGrupoError = fieldErrors.num_grupo ?? backendNumGrupoError
  const periodoError = fieldErrors.id_periodo ?? backendPeriodoError

  const totalErrors = [numGrupoError, periodoError].filter(Boolean).length
  const showAlert =
    totalErrors > 0 ||
    backendMateriaError !== undefined ||
    (error !== null && !error.isValidation)

  const incorporables = rosterPreview ? countRosterRows(rosterPreview).incorporables : 0
  const submitLabel =
    incorporables > 0
      ? `Registrar grupo con ${incorporables} ${incorporables === 1 ? 'estudiante' : 'estudiantes'}`
      : 'Registrar sin nómina'

  return (
    <div className="w-full flex flex-col max-h-[80vh]">
      {/* Cabecera fija */}
      <div className="flex items-center justify-between px-6 py-3.5 border-b shrink-0">
        <div className="flex flex-col pr-6">
          <h2 className="text-base font-semibold leading-none tracking-tight">Nuevo grupo</h2>
          <p className="text-xs text-muted-foreground mt-1">
            {subjectName} · se asocia a esta materia
          </p>
        </div>
      </div>

      {/* Cuerpo con scroll */}
      <form
        id="create-group-form"
        onSubmit={handleSubmit}
        noValidate
        className="flex-1 overflow-y-auto"
      >
        <div className="p-5 flex flex-col gap-3.5">
          {showAlert && (
            <Alert className="border-[#A21B12]/20 bg-[#FDE2E1] text-[#A21B12] py-2 px-3">
              <AlertDescription className="text-xs font-medium text-[#A21B12] leading-tight">
                {backendMateriaError
                  ? backendMateriaError
                  : error !== null && !error.isValidation
                    ? error.message
                    : `Revise ${totalErrors} ${totalErrors === 1 ? 'campo' : 'campos'} antes de registrar el grupo.`}
              </AlertDescription>
            </Alert>
          )}

          {/* Materia y docente: solo si no hay nómina adjunta */}
          {!hasRoster && (
            <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
              <ReadOnlyField label="Materia y carrera" value={subjectCareerLabel} />
              <ReadOnlyField label="Docente" value={teacherName} />
            </div>
          )}

          {/* N° de grupo y período */}
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
                <p className="text-xs text-muted-foreground leading-normal">
                  Debe ser único dentro de la materia.
                </p>
              )}
            </div>

            {/* Período */}
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
                    <SelectItem
                      key={period.id_periodo}
                      value={String(period.id_periodo)}
                      className="text-xs"
                    >
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

          {/* Auxiliares: solo si no hay nómina adjunta */}
          {!hasRoster && (
            <div className="flex flex-col gap-1">
              <Label htmlFor="auxiliares_input" className="text-xs font-medium text-[#2C2C2C]">
                Auxiliares{' '}
                <span className="text-muted-foreground font-normal">(opcional)</span>
              </Label>
              <Input
                id="auxiliares_input"
                value={auxiliarySearch}
                onChange={(e) => setAuxiliarySearch(e.target.value)}
                placeholder="Buscar por nombre o código SIS"
                disabled={isSubmitting}
                className="h-8.5 px-3 py-1.5 text-xs bg-card text-[#2C2C2C] border-input placeholder:text-muted-foreground/60 w-full"
              />
              <p className="text-xs text-muted-foreground leading-normal">
                Solo auxiliares registrados por el administrador.
              </p>
            </div>
          )}

          {/* Nómina */}
          <div className="flex flex-col gap-2">
            <Label className="text-xs font-medium text-[#2C2C2C]">
              Nómina de estudiantes{' '}
              <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>

            {/* Dropzone: solo si no hay archivo ni error */}
            {!hasRoster && !isPreviewing && (
              <RosterDropzone onFileSelected={handleFileSelected} />
            )}

            {/* Cargando preview */}
            {isPreviewing && (
              <p className="text-xs text-muted-foreground">Procesando archivo…</p>
            )}

            {/* Archivo con error: ícono rojo, sin botón quitar */}
            {rosterFile && rosterError && (
              <div className="flex items-center gap-3 rounded-md border border-[#A21B12]/20 bg-[#FDE2E1]/30 px-3 py-2">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-[#FDE2E1] text-[#A21B12]">
                  <FileText className="size-4" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-xs font-medium truncate text-[#2C2C2C]">
                    {rosterFile.name}
                  </p>
                  <p className="text-xs text-[#A21B12]">
                    Formato no admitido · {formatFileSize(rosterFile.size)}
                  </p>
                </div>
              </div>
            )}

            {/* Alerta de error con botón de plantilla */}
            {rosterError && (
              <Alert className="border-[#A21B12]/20 bg-[#FDE2E1] text-[#A21B12] py-2 px-3">
                <AlertDescription className="text-xs leading-tight space-y-2">
                  <div>
                    <p className="font-semibold text-[#A21B12]">{rosterErrorTitle}</p>
                    <p className="text-[#A21B12] mt-1">
                      Solo se admiten archivos CSV o XLSX con encabezados en la
                      primera fila (estudiante, apellidos, nombres). No se cargó
                      ningún dato.
                    </p>
                  </div>
                  <div>
                    <a
                      href="/plantillas/nomina-plantilla.csv"
                      download
                      className="inline-flex items-center gap-1.5 rounded-md border border-[#A21B12]/30 bg-white px-2.5 py-1 text-xs font-medium text-[#2C2C2C] hover:bg-[#FDE2E1] !no-underline hover:!no-underline"
                      style={{ textDecoration: 'none' }}
                    >
                      <Download className="size-3.5" aria-hidden="true" />
                      Descargar plantilla
                    </a>
                  </div>
                </AlertDescription>
              </Alert>
            )}

            {/* Preview válido */}
            {rosterPreview && (
              <RosterStandalonePreview
                preview={rosterPreview}
                fileName={rosterFile?.name ?? ''}
                fileSize={rosterFile?.size ?? 0}
                groupLabel={`Grupo ${numGrupo || '—'}`}
                onClear={clearRoster}
              />
            )}

            {/* Texto de ayuda: solo si no hay archivo */}
            {!hasRoster && (
              <p className="text-xs text-muted-foreground leading-normal">
                CSV o XLSX, hasta 10 MB. Si no la adjunta ahora, el grupo queda
                «Sin nómina cargada».
              </p>
            )}
          </div>
        </div>
      </form>

      {/* Footer fijo */}
      <div className="flex items-center gap-2 px-5 py-3 border-t shrink-0 bg-card">
        {/* Botón "Elegir otro archivo" cuando hay error */}
        {rosterError && rosterFile && (
          <>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={isSubmitting}
              onClick={clearRoster}
              className="text-xs gap-1.5"
            >
              <Upload className="size-3.5" aria-hidden="true" />
              Elegir otro archivo
            </Button>
            <div className="flex-1" />
          </>
        )}

        {/* Si no hay error, empuja los botones a la derecha */}
        {!rosterError && <div className="flex-1" />}

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
        <Button
          type="submit"
          form="create-group-form"
          size="sm"
          disabled={isSubmitting}
          className="text-xs"
        >
          {isSubmitting ? 'Registrando…' : submitLabel}
        </Button>
      </div>
    </div>
  )
}

/* ============================================================
 * Subcomponentes locales
 * ============================================================ */

function ReadOnlyField({ label, value }: { label: string; value: string }) {
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

function RosterDropzone({ onFileSelected }: { onFileSelected: (file: File) => void }) {
  const [inputId] = useState(() => `roster-${Math.random().toString(36).slice(2)}`)

  return (
    <div className="flex items-center justify-center gap-2 rounded-md border border-dashed border-input px-4 py-3 text-xs text-muted-foreground bg-muted/20">
      <Upload className="size-3.5 text-[#005E68]" aria-hidden="true" />
      <label htmlFor={inputId} className="cursor-pointer">
        <span className="text-[#005E68] font-medium underline underline-offset-2">
          Seleccionar archivo
        </span>{' '}
        o arrastrarlo aquí
      </label>
      <input
        id={inputId}
        type="file"
        accept=".csv,.xlsx"
        className="sr-only"
        onChange={(event) => {
          const file = event.currentTarget.files?.[0]
          if (file) onFileSelected(file)
        }}
      />
    </div>
  )
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}