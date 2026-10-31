import { useId, useRef, useState } from 'react'
import { FileText, Upload, X } from 'lucide-react'
import { FormSelect } from '@/components/common/FormSelect'
import { Button } from '@/components/ui/button'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useReportIncident } from '../hooks/useReportIncident'
import type { IncidentDialogContext } from '../types/incident.types'

interface Props {
  context: IncidentDialogContext
  readOnly?: boolean
  onClose: () => void
  onReported: (alreadyReported: boolean) => void
}

const dialogContentClass = [
  'top-auto bottom-0 left-0 flex max-h-[92dvh] max-w-none translate-x-0 translate-y-0',
  'flex-col gap-0 overflow-hidden rounded-b-none p-0',
  'sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-w-xl sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl',
].join(' ')

/** Modal de escritorio y hoja inferior móvil para reportar un intento de ingreso. */
export function ReportIncidentDialog({ context, onClose, onReported, readOnly = false }: Props) {
  const duringExam = 'origin' in context
  const [retire, setRetire] = useState(false)
  const fieldId = useId()
  const fileInput = useRef<HTMLInputElement>(null)
  const [incidentTypeId, setIncidentTypeId] = useState('')
  const [description, setDescription] = useState('')
  const [evidence, setEvidence] = useState<File | null>(null)
  const report = useReportIncident(context, onReported)

  const disabled = report.busy || report.blocked || readOnly

  function clearEvidence() {
    setEvidence(null)
    if (fileInput.current) fileInput.current.value = ''
  }

  return (
    <Dialog open onOpenChange={(open) => { if (!open && !report.busy) onClose() }}>
      <DialogContent
        className={duringExam
          ? 'flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl sm:max-w-xl flex-col gap-0 overflow-hidden rounded-xl p-0'
          : dialogContentClass}
        showCloseButton={false}
      >
        <DialogHeader className="shrink-0 border-b p-4 text-left">
          <DialogTitle>Reportar incidencia</DialogTitle>
          <DialogDescription>El docente del examen la revisará antes de que llegue a la Central de Riesgo.</DialogDescription>
        </DialogHeader>
        <form className="flex min-h-0 flex-col" noValidate onSubmit={(event) => {
          event.preventDefault()
          if (!disabled) void report.submit({ incidentTypeId, description, evidence, retire })
        }}>
          <div className="min-h-0 space-y-4 overflow-y-auto p-4">
            <section className="space-y-3 rounded-lg border bg-muted/50 p-3" aria-label={duringExam ? 'Datos del examen' : 'Datos del intento'}>
              <p className="text-xs font-medium text-muted-foreground">{duringExam ? 'Datos del examen' : 'Datos del intento'} · no se editan</p>
              <dl className="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-2 text-sm">
                <dt className="text-muted-foreground">Estudiante</dt>
                <dd className="font-medium">
                  <span>{context.student.nombre_completo}</span>
                  {' · '}
                  <span className="sciem-tnum">{context.student.cod_sis}</span>
                </dd>
                <dt className="text-muted-foreground">Examen</dt><dd>{context.examName}</dd>
                <dt className="text-muted-foreground">Ambiente</dt>
                <dd><span>{context.roomName}</span>{context.timeLabel && context.timeLabel !== '—' ? ` · ${context.timeLabel}` : ''}</dd>
                {'reason' in context && <><dt className="text-muted-foreground">Motivo del rechazo</dt><dd>{context.reason}</dd></>}
              </dl>
            </section>
            <div className="space-y-1.5">
              <Label htmlFor={`${fieldId}-type`}>Tipo de falta <span aria-hidden="true" className="text-danger-fg">*</span></Label>
              <FormSelect
                id={`${fieldId}-type`}
                value={incidentTypeId}
                onValueChange={setIncidentTypeId}
                placeholder={report.loadingTypes ? 'Cargando tipos de falta…' : 'Elija el tipo de falta'}
                options={report.types.map((type) => ({ value: String(type.id_falta), label: type.nombre }))}
                disabled={disabled || report.loadingTypes || !!report.catalogError}
                invalid={!!report.errors.id_tipo_falta}
              />
              {duringExam && report.types.find((type) => String(type.id_falta) === incidentTypeId)?.descripcion && (
                <p className="text-xs text-muted-foreground">
                  {report.types.find((type) => String(type.id_falta) === incidentTypeId)?.descripcion}
                </p>
              )}
              {report.errors.id_tipo_falta && <p role="alert" className="text-sm text-danger-fg">{report.errors.id_tipo_falta}</p>}
              {report.catalogError && <div role="alert" className="space-y-2 text-sm text-danger-fg">
                <p>{report.catalogError}</p>
                <Button type="button" variant="outline" onClick={report.retryTypes}>Reintentar carga</Button>
              </div>}
              {!report.loadingTypes && !report.catalogError && report.types.length === 0 && (
                <p role="status" className="text-sm text-muted-foreground">No hay tipos de falta disponibles.</p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={`${fieldId}-description`}>Descripción <span aria-hidden="true" className="text-danger-fg">*</span></Label>
              <Textarea
                id={`${fieldId}-description`}
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                disabled={disabled}
                maxLength={500}
                rows={3}
                placeholder={duringExam ? 'Describa lo ocurrido durante el examen' : 'Describa lo ocurrido durante el intento de ingreso'}
                aria-required="true"
                aria-invalid={!!report.errors.descripcion}
                aria-describedby={`${fieldId}-description-help`}
              />
              <p id={`${fieldId}-description-help`} className="text-xs text-muted-foreground">
                Entre 10 y 500 caracteres. {description.trim().length}/500
              </p>
              {report.errors.descripcion && <p role="alert" className="text-sm text-danger-fg">{report.errors.descripcion}</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor={`${fieldId}-evidence`}>Evidencia (opcional)</Label>
              <input
                ref={fileInput}
                id={`${fieldId}-evidence`}
                type="file"
                className="sr-only"
                accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                disabled={disabled}
                onChange={(event) => setEvidence(event.target.files?.[0] ?? null)}
                aria-invalid={!!report.errors.evidencia}
                aria-describedby={`${fieldId}-evidence-help`}
              />
              {evidence ? <div className="flex items-center gap-3 rounded-lg border bg-muted/50 p-3">
                <FileText className="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{evidence.name}</p>
                  <p className="text-xs text-muted-foreground">
                    {(evidence.size / 1024 / 1024).toLocaleString('es-BO', { maximumFractionDigits: 2 })} MB
                  </p>
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  disabled={disabled}
                  onClick={clearEvidence}
                  aria-label="Quitar evidencia"
                >
                  <X className="size-4" />
                </Button>
              </div> : (
                <Button
                  type="button"
                  variant="outline"
                  className="h-auto w-full justify-start border-dashed p-4 text-left"
                  disabled={disabled}
                  onClick={() => fileInput.current?.click()}
                >
                  <Upload className="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                  <span className="whitespace-normal">Adjunte una foto o un PDF</span>
                </Button>
              )}
              <p id={`${fieldId}-evidence-help`} className="text-xs text-muted-foreground">JPG, PNG o PDF, hasta 5 MB.</p>
              {report.errors.evidencia && <p role="alert" className="text-sm text-danger-fg">{report.errors.evidencia}</p>}
            </div>
            {duringExam && <div className="space-y-2 rounded-lg border p-3">
              <label className="flex items-start gap-2 text-sm" htmlFor={`${fieldId}-retire`}>
                <input id={`${fieldId}-retire`} type="checkbox" checked={retire} disabled={disabled}
                  onChange={(event) => setRetire(event.target.checked)} className="mt-1" />
                Retirar al estudiante del examen
              </label>
              <p className="text-xs text-muted-foreground">
                Al enviar se registrará su retiro inmediato. El reporte quedará pendiente de revisión.
              </p>
            </div>}
            {readOnly && <p role="alert" className="text-sm text-danger-fg">
              El examen o el estudiante cambió. Cierre el formulario y actualice la vista.
            </p>}
            {report.error && <p role="alert" className="text-sm text-danger-fg">{report.error}</p>}
          </div>
          <DialogFooter className="m-0 shrink-0 rounded-none p-4 sm:rounded-b-xl">
            <Button type="button" variant="outline" disabled={report.busy} onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={disabled || report.loadingTypes || !!report.catalogError || !report.types.length}>
              {report.busy ? 'Enviando…' : 'Enviar a revisión'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}