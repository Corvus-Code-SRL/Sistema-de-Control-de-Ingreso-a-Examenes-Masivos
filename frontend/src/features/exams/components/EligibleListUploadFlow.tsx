import { useEffect, useMemo, useRef, useState } from 'react'
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
import { FormSelect } from '@/components/common/FormSelect'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'

import { useEligibleListUpload } from '../hooks/useEligibleListUpload'
import type { NoEligibleReason } from '../types/eligibleList.types'
import { NO_ELIGIBLE_REASONS } from '../types/eligibleList.types'
import { validateOtroDetalle } from '../utils/eligibleListUtils'

interface EligibleListUploadFlowProps {
  open: boolean
  examId: number | null
  onClose: () => void
  onPendingChanges: (changes: import('../types/eligibleList.types').EligibleChange[]) => void
}

const reasonOptions = (Object.keys(NO_ELIGIBLE_REASONS) as NoEligibleReason[]).map((key) => ({
  value: key,
  label: NO_ELIGIBLE_REASONS[key],
}))

export function EligibleListUploadFlow({
  open,
  examId,
  onClose,
  onPendingChanges,
}: EligibleListUploadFlowProps) {
  const upload = useEligibleListUpload(examId)
  const [motivo, setMotivo] = useState<NoEligibleReason | ''>('')
  const [detalle, setDetalle] = useState('')
  const [formError, setFormError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement | null>(null)

  useEffect(() => {
    if (!open) {
      upload.reset()
      setMotivo('')
      setDetalle('')
      setFormError(null)
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
    if (!motivo) {
      setFormError('Debe seleccionar un motivo.')
      return
    }
    if (motivo === 'OTRO') {
      const err = validateOtroDetalle(detalle)
      if (err) {
        setFormError(err)
        return
      }
    }

    const applied = await upload.applyToPending(
      motivo,
      motivo === 'OTRO' ? detalle : undefined
    )
    if (applied) {
      onPendingChanges(
        applied.cambios_pendientes.map((c) => ({
          id_estudiante: c.id_estudiante,
          estado_habilitacion: c.estado_habilitacion,
          motivo: c.motivo ?? undefined,
          detalle: c.detalle ?? undefined,
        }))
      )
      onClose()
    }
  }

  const previewSummary = useMemo(() => {
    if (!upload.preview) return null
    const filas = upload.preview.filas
    const coinciden = filas.filter((f) => f.estado === 'matched').length
    const noEstan = filas.filter((f) => f.estado === 'not_found_in_exam').length
    const deNominaNoFiguran = upload.preview.faltantes
    return { coinciden, noEstan, deNominaNoFiguran }
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

              {(previewSummary?.deNominaNoFiguran ?? 0) > 0 && (
                <Alert className="border-warn-border bg-warn-soft text-warn-fg">
                  <AlertTriangle aria-hidden="true" />
                  <AlertTitle>
                    {previewSummary?.deNominaNoFiguran}{' '}
                    {previewSummary?.deNominaNoFiguran === 1 ? 'estudiante quedaría no habilitado' : 'estudiantes quedarían no habilitados'}
                  </AlertTitle>
                  <AlertDescription>
                    Son los de la nómina que no figuran en el archivo.
                  </AlertDescription>
                </Alert>
              )}

              <div className="space-y-2">
                <Label htmlFor="global-motivo">
                  Motivo para los que no figuran <span className="text-danger">*</span>
                </Label>
                <FormSelect
                  id="global-motivo"
                  value={motivo}
                  placeholder="Seleccione un motivo"
                  options={reasonOptions}
                  onValueChange={(v) => { setMotivo(v as NoEligibleReason); setFormError(null) }}
                  ariaLabel="Motivo para los que no figuran"
                  invalid={!motivo && !!formError}
                />
              </div>

              {motivo === 'OTRO' && (
                <div className="space-y-2">
                  <Label htmlFor="global-detalle">Detalle <span className="text-danger">*</span></Label>
                  <Textarea
                    id="global-detalle"
                    value={detalle}
                    onChange={(e) => { setDetalle(e.target.value); setFormError(null) }}
                    maxLength={500}
                    placeholder="Mínimo 10 caracteres"
                    className="resize-none"
                  />
                </div>
              )}

              {formError && (
                <Alert variant="destructive">
                  <AlertTriangle aria-hidden="true" />
                  <AlertDescription>{formError}</AlertDescription>
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
              <p className="sciem-label">Aplicando cambios…</p>
            </div>
          )}
        </div>

        <SheetFooter className="border-t border-border-soft p-6">
          <Button variant="ghost" onClick={onClose}>Cancelar</Button>
          {upload.step === 'preview' && (
            <Button onClick={() => void handleApply()} disabled={!motivo}>
              <CheckCircle2 aria-hidden="true" className="size-4" />
              Aplicar a la lista
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
