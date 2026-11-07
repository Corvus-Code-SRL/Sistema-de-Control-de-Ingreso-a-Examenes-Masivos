import { FileSpreadsheet, FileText, RefreshCw, Upload } from 'lucide-react'
import { useId } from 'react'

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import type { GroupCareer } from '@/features/groups'

interface RosterFileStepProps {
  subjectName: string
  groupLabel: string
  file: File | null
  errorMessage: string | null
  canRetry: boolean
  /** Carreras del grupo (HU-021, multicarrera): con más de una se pide la carrera de la nómina. */
  careers?: GroupCareer[]
  careerId?: number | null
  onSelectCareer?: (careerId: number | null) => void
  onSelectFile: (file: File) => void
  onSubmitPreview: () => void
  onRetry: () => void
  onReset: () => void
}

/**
 * Paso 1: selección local del archivo antes de solicitar el preview.
 */
export function RosterFileStep({
  subjectName,
  groupLabel,
  file,
  errorMessage,
  canRetry,
  careers = [],
  careerId = null,
  onSelectCareer,
  onSelectFile,
  onSubmitPreview,
  onRetry,
  onReset,
}: RosterFileStepProps) {
  const inputId = useId()
  const careerSelectId = useId()
  const isMultiCareer = careers.length > 1

  const handleFileChange = (
    event: React.ChangeEvent<HTMLInputElement>,
  ): void => {
    const selectedFile = event.currentTarget.files?.[0]

    if (selectedFile) {
      onSelectFile(selectedFile)
    }
  }

  return (
    <>
      <CardHeader className="border-b">
        <CardTitle className="sciem-h2">Cargar nómina</CardTitle>

        <div className="sciem-supporting text-muted-foreground">
          {subjectName} · {groupLabel}
        </div>

        <p className="sciem-supporting text-muted-foreground">
          La nómina se asocia solo a este grupo.
          {isMultiCareer &&
            ` Atiende a ${careers.length} carreras: ${careers.map((career) => career.nombre).join(', ')}.`}
        </p>
      </CardHeader>

      <CardContent className="space-y-5">
        <div className="space-y-2">
          <label
            htmlFor={inputId}
            className="sciem-label block text-foreground"
          >
            Archivo de nómina
          </label>

          <input
            id={inputId}
            type="file"
            accept=".csv,.xlsx"
            className="sr-only"
            onChange={handleFileChange}
          />

          <label
            htmlFor={inputId}
            className="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-border-strong bg-sunken p-4 transition-colors hover:bg-muted focus-within:ring-2 focus-within:ring-ring"
          >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
              <Upload aria-hidden="true" className="size-5" />
            </span>

            <span className="min-w-0 flex-1">
              <span className="sciem-label block">
                {file ? 'Cambiar archivo' : 'Seleccionar archivo'}
              </span>

              <span className="sciem-supporting block truncate text-muted-foreground">
                CSV o XLSX, hasta 10 MB · una fila por estudiante
              </span>
            </span>
          </label>
        </div>

        {isMultiCareer && onSelectCareer && (
          <div className="space-y-2">
            <label htmlFor={careerSelectId} className="sciem-label block text-foreground">
              Carrera de esta nómina
            </label>

            <select
              id={careerSelectId}
              value={careerId ?? ''}
              onChange={(event) =>
                onSelectCareer(event.target.value === '' ? null : Number(event.target.value))
              }
              className="h-10 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
              <option value="">Según la columna «Carrera» del archivo</option>
              {careers.map((career) => (
                <option key={career.id_carrera} value={career.id_carrera}>
                  Todos son de {career.nombre}
                </option>
              ))}
            </select>

            <p className="sciem-supporting text-muted-foreground">
              Si el archivo mezcla carreras, deje «Según la columna» y agregue la columna «Carrera»
              con el código o el nombre de cada una.
            </p>
          </div>
        )}

        {file && (
          <div className="flex items-center gap-3 rounded-xl border border-border-soft bg-surface p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
              {file.name.toLowerCase().endsWith('.xlsx') ? (
                <FileSpreadsheet aria-hidden="true" className="size-5" />
              ) : (
                <FileText aria-hidden="true" className="size-5" />
              )}
            </span>

            <div className="min-w-0">
              <p className="sciem-label truncate">{file.name}</p>
              <p className="sciem-caption">{formatFileSize(file.size)}</p>
            </div>
          </div>
        )}

        <div className="rounded-xl border border-border-soft bg-sunken p-4">
          <p className="sciem-label">Columnas esperadas</p>

          <p className="sciem-supporting mt-1 text-muted-foreground">
            Estudiante, Apellidos, Nombres y, opcional, Carrera (código o nombre).
            Los encabezados deben estar en la primera fila; las demás columnas se ignoran.
          </p>

          <a
            href="/plantillas/nomina-plantilla.csv"
            download
            className="mt-3 inline-flex items-center text-sm font-medium text-brand underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            Descargar plantilla
          </a>
        </div>

        <Alert className="border-info-border bg-info-soft text-info">
          <FileText aria-hidden="true" />

          <AlertTitle>Ningún registro se guarda todavía</AlertTitle>

          <AlertDescription>
            El archivo solo se procesa para generar una previsualización. Los
            estudiantes se incorporan únicamente después de confirmar.
          </AlertDescription>
        </Alert>

        {errorMessage && (
          <Alert
            variant="destructive"
            aria-live="assertive"
          >
            <AlertTitle>No se pudo procesar la nómina</AlertTitle>

            <AlertDescription>{errorMessage}</AlertDescription>
          </Alert>
        )}

        {canRetry && (
          <Button
            type="button"
            variant="outline"
            onClick={onRetry}
          >
            <RefreshCw aria-hidden="true" />
            Reintentar
          </Button>
        )}
      </CardContent>

      <CardFooter className="justify-end gap-2">
        <Button
          type="button"
          variant="outline"
          onClick={onReset}
        >
          Cancelar
        </Button>

        <Button
          type="button"
          onClick={onSubmitPreview}
          disabled={!file}
        >
          Previsualizar
        </Button>
      </CardFooter>
    </>
  )
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${Math.max(1, Math.round(bytes / 1024))} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
