import { useEffect, useMemo, useRef } from 'react'
import { AlertTriangle, CheckCircle2, FileUp, LoaderCircle, Upload, XCircle } from 'lucide-react'

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Badge } from '@/components/ui/badge'

import { useEligibleListUpload } from '../hooks/useEligibleListUpload'
import { STORED_REASON_LABELS } from '../types/eligibleList.types'

interface EligibleListUploadFlowProps {
  open: boolean
  examId: number | null
  /** La lista ya tiene no habilitados guardados que el archivo reemplazaría. */
  replacesCurrentList: boolean
  onClose: () => void
  /** El servidor ya reemplazó la lista: la página debe descartar sus cambios locales y recargar. */
  onReplaced: () => void
}

export function EligibleListUploadFlow({
  open,
  examId,
  replacesCurrentList,
  onClose,
  onReplaced,
}: EligibleListUploadFlowProps) {
  const upload = useEligibleListUpload(examId)
  const inputRef = useRef<HTMLInputElement | null>(null)

  useEffect(() => {
    if (!open) {
      upload.reset()
      if (inputRef.current) {
        inputRef.current.value = ''
      }
    }
  }, [open, upload.reset])

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
    const file = e.currentTarget.files?.[0]
    if (file) upload.startUpload(file)
  }

  const handleApply = async (): Promise<void> => {
    const replaced = await upload.confirmReplacement()
    // No se cierra el panel: el docente ve el resumen de no habilitados antes de pulsar "Listo".
    if (replaced) onReplaced()
  }

  const previewSummary = useMemo(() => {
    if (!upload.preview) return null
    const filas = upload.preview.filas
    const coinciden = filas.filter((f) => f.estado === 'matched').length
    const noEstan = filas.filter((f) => f.estado === 'not_found_in_exam').length
    const deNominaNoFiguran = upload.preview.faltantes
    const conError = filas.filter((f) => f.estado === 'inconsistent')
    const duplicadas = conError.filter((f) =>
      f.errores.some(
        (e) => e === 'duplicate_row_in_file' || e === 'conflicting_duplicate_in_file'
      )
    )
    return { coinciden, noEstan, deNominaNoFiguran, conError, duplicadas }
  }, [upload.preview])

  return (
    <Sheet open={open} onOpenChange={(v) => !v && onClose()}>
      <SheetContent className="w-full sm:max-w-[520px] flex flex-col">
        <SheetHeader className="border-b border-border-soft p-6">
          <SheetTitle className="flex items-center gap-2">
            <FileUp aria-hidden="true" className="size-5 text-brand" />
              Importar desde archivo
          </SheetTitle>
          <SheetDescription>
            Lista de asistencia o de entregas en CSV o XLSX con los códigos SIS.
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto space-y-4 p-6">
          {upload.step === 'idle' && (
            <>
              <input
                ref={inputRef}
                type="file"
                accept=".csv,.xlsx"
                className="sr-only"
                onChange={handleFileChange}
              />
              <div
                role="button"
                tabIndex={0}
                onClick={() => inputRef.current?.click()}
                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') inputRef.current?.click() }}
                className="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed border-border-strong
                bg-sunken p-8 text-center cursor-pointer hover:bg-muted"
              >
                <span className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand">
                  <Upload aria-hidden="true" className="size-6" />
                </span>
                <span className="sciem-label">Arrastre el archivo aquí o elija uno</span>
                <span className="sciem-supporting text-muted-foreground">
                  CSV o XLSX · hasta 10 MB
                </span>
                <Button variant="outline" size="sm" type="button">
                  <Upload aria-hidden="true" className="size-4" />
                    Elegir archivo
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                ¿No tiene el formato?{' '}
                <a
                  href="/plantillas/nomina-plantilla.csv"
                  download="nomina-plantilla.csv"
                  className="font-medium text-brand underline-offset-2 hover:underline"
                >
                  Descargar plantilla
                </a>
              </p>
              <p className="text-xs text-muted-foreground">Ningún dato cambia hasta confirmar.</p>

              {upload.errorMessage && (
                <Alert variant="destructive">
                  <AlertTriangle aria-hidden="true" />
                  <AlertDescription>{upload.errorMessage}</AlertDescription>
                </Alert>
              )}
            </>
          )}

          {upload.step === 'previewing' && (
            <div className="flex flex-col items-center gap-3 py-12" role="status" aria-live="polite">
              <LoaderCircle className="size-8 animate-spin text-brand" aria-hidden="true" />
              <p className="sciem-label">Procesando archivo…</p>
              {upload.file && <p className="sciem-supporting text-muted-foreground truncate max-w-full">{upload.file.name}</p>}
            </div>
          )}

          {upload.step === 'preview' && upload.preview && (
            <>
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <UploadStat label="Coinciden con la nómina" value={previewSummary?.coinciden ?? 0} tone="ok" />
                <UploadStat label="De la nómina no figuran" value={previewSummary?.deNominaNoFiguran ?? 0} tone="danger" />
                <UploadStat label="No están en la nómina" value={previewSummary?.noEstan ?? 0} tone="warn" />
              </div>

              {(previewSummary?.conError.length ?? 0) > 0 && (
                <div className="space-y-2 rounded-xl border border-danger-border bg-danger-soft/40 p-3">
                  <div className="flex items-center gap-2 text-sm font-medium text-danger-fg">
                    <AlertTriangle aria-hidden="true" className="size-4" />
                    Filas con error
                    <Badge variant="outline" className="border-danger-border bg-card text-danger-fg">
                      {previewSummary?.conError.length}
                    </Badge>
                  </div>
                  {(previewSummary?.duplicadas.length ?? 0) > 0 && (
                    <p className="text-xs text-danger-fg">
                      {previewSummary?.duplicadas.length}{' '}
                      {previewSummary?.duplicadas.length === 1 ? 'duplicada' : 'duplicadas'} en el archivo
                    </p>
                  )}
                  <ul className="max-h-48 space-y-1 overflow-y-auto text-sm">
                    {previewSummary?.conError.map((fila) => (
                      <li
                        key={fila.numero_fila ?? `${fila.codigo_sis}-${fila.errores.join('-')}`}
                        className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5"
                      >
                        <span className="sciem-tnum font-medium text-muted-foreground">
                          {fila.codigo_sis ?? '—'}
                        </span>
                        <span className="font-medium">
                          {[fila.apellidos, fila.nombres].filter(Boolean).join(', ') || '—'}
                        </span>
                        <span className="text-xs text-danger-fg">
                          {fila.errores.map(describeError).join(' · ')}
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {(previewSummary?.deNominaNoFiguran ?? 0) > 0 && (
                <Alert className="border-warn-border bg-warn-soft text-warn-fg">
                  <AlertTriangle aria-hidden="true" />
                  <AlertTitle>
                    {previewSummary?.deNominaNoFiguran}{' '}
                    {previewSummary?.deNominaNoFiguran === 1 ? 'estudiante quedaría no habilitado' : 'estudiantes quedarían no habilitados'}
                  </AlertTitle>
                  <AlertDescription>
                    Son los de la nómina que no figuran en el archivo. Su motivo será «
                    {STORED_REASON_LABELS.NO_FIGURA_EN_LISTA}».
                  </AlertDescription>
                </Alert>
              )}

              {replacesCurrentList && (
                <Alert className="border-warn-border bg-warn-soft text-warn-fg">
                  <AlertTriangle aria-hidden="true" />
                  <AlertDescription>
                    Se reemplazará la lista actual.
                  </AlertDescription>
                </Alert>
              )}

              {upload.errorMessage && (
                <Alert variant="destructive">
                  <AlertTriangle aria-hidden="true" />
                  <AlertDescription>{upload.errorMessage}</AlertDescription>
                </Alert>
              )}

              <div className="flex gap-2">
                <input
                  ref={inputRef}
                  type="file"
                  accept=".csv,.xlsx"
                  className="sr-only"
                  onChange={handleFileChange}
                />
                <Button variant="outline" size="sm" type="button" onClick={() => inputRef.current?.click()}>
                  Cambiar archivo
                </Button>
              </div>
            </>
          )}

          {upload.step === 'confirming' && (
            <div className="flex flex-col items-center gap-3 py-12" role="status" aria-live="polite">
              <LoaderCircle className="size-8 animate-spin text-brand" aria-hidden="true" />
              <p className="sciem-label">Reemplazando la lista…</p>
            </div>
          )}
          {upload.step === 'result' && upload.result && (
            <>
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <UploadStat label="Filas leídas" value={upload.result.total_filas} tone="ok" />
                <UploadStat label="Filas con error" value={upload.result.filas_inconsistentes} tone="danger" />
                <UploadStat label="Fuera de la nómina" value={upload.result.no_encontrados} tone="warn" />
              </div>

              {upload.result.no_habilitados.length > 0 ? (
                <div className="space-y-2 rounded-xl border border-warn-border bg-warn-soft/50 p-3">
                  <div className="flex items-center gap-2 text-sm font-medium text-warn-fg">
                    <AlertTriangle aria-hidden="true" className="size-4" />
                    Quedaron no habilitados
                    <Badge variant="outline" className="border-warn-border bg-card text-warn-fg">
                      {upload.result.total_no_habilitados}
                    </Badge>
                  </div>
                  <p className="text-xs text-warn-fg">
                    Son los de la nómina que no figuran en el archivo. La lista ya quedó guardada con el
                    motivo «{STORED_REASON_LABELS[upload.result.motivo]}».
                  </p>
                  <ul className="max-h-48 space-y-1 overflow-y-auto text-sm">
                    {upload.result.no_habilitados.map((cambio) => (
                      <li
                        key={cambio.id_estudiante}
                        className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5"
                      >
                        <span className="sciem-tnum font-medium text-muted-foreground">
                          {cambio.codigo_sis}
                        </span>
                        <span className="font-medium">{cambio.nombre_completo}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : (
                <Alert className="border-ok-border bg-ok-soft text-ok-fg">
                  <CheckCircle2 aria-hidden="true" />
                  <AlertTitle>Ningún estudiante quedó no habilitado</AlertTitle>
                  <AlertDescription>
                    Todos los estudiantes de la nómina figuran en el archivo.
                  </AlertDescription>
                </Alert>
              )}
            </>
          )}
        </div>

        <SheetFooter className="border-t border-border-soft p-6">
          {upload.step !== 'result' && (
            <Button variant="ghost" onClick={onClose}>Cancelar</Button>
          )}
          {upload.step === 'preview' && (
            <Button onClick={() => void handleApply()}>
              <CheckCircle2 aria-hidden="true" className="size-4" />
              Reemplazar la lista
            </Button>
          )}
          {upload.step === 'result' && (
            <Button onClick={onClose}>
              <CheckCircle2 aria-hidden="true" className="size-4" />
              Listo
            </Button>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}

function UploadStat({ label, value, tone }: { label: string; value: number; tone: 'ok' | 'danger' | 'warn' }) {
  const cls =
    tone === 'ok' ? 'bg-ok-soft text-ok-fg border-ok-border'
    : tone === 'danger' ? 'bg-danger-soft text-danger-fg border-danger-border'
    : 'bg-warn-soft text-warn-fg border-warn-border'
  return (
    <div className={`rounded-xl border p-3 ${cls}`}>
      <p className="text-xs font-medium">{label}</p>
      <p className="sciem-tnum text-2xl font-semibold">{value}</p>
    </div>
  )
}

function describeError(code: string): string {
  const labels: Record<string, string> = {
    missing_sis_code: 'sin código SIS',
    sis_code_not_numeric: 'código SIS no numérico',
    sis_code_invalid_length: 'longitud de código SIS inválida',
    duplicate_row_in_file: 'código SIS duplicado en el archivo',
    conflicting_duplicate_in_file: 'código SIS duplicado con datos distintos',
    missing_last_names: 'faltan apellidos',
    missing_first_names: 'faltan nombres',
  }
  return labels[code] ?? code
}
