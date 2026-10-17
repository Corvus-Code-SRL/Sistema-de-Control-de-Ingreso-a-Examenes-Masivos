import type { ReactNode } from 'react'
import { FileX, Lock, Paperclip, X } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { buttonVariants } from '@/components/ui/button'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { cn } from '@/lib/utils'
import { useEvidenceDownload } from '../hooks/useEvidenceDownload'
import { useRiskCenterRecord } from '../hooks/useRiskCenterRecord'
import type { RiskCenterEvidence, RiskCenterRecordDetail } from '../types/riskCenter.types'
import { formatDate, formatDayMonthTime, formatFileSize, formatPlainDate, formatTime, initialsOf } from '../utils/format'
import { FaultTypeBadge } from './FaultTypeBadge'

interface RiskCenterDetailPanelProps {
  /** Registro abierto (`/central-riesgo/:id`). */
  id: number
  onClose: () => void
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="space-y-0.5">
      <dt className="sciem-overline text-muted-foreground">{label}</dt>
      <dd className="text-sm text-foreground">{children}</dd>
    </div>
  )
}

function Evidence({ recordId, evidence }: { recordId: number; evidence: RiskCenterEvidence }) {
  const { open, status, message } = useEvidenceDownload(recordId, evidence.nombre_archivo)

  if (!evidence.disponible) {
    return (
      <p className="flex items-start gap-2 text-sm text-muted-foreground">
        <FileX className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>
          {evidence.nombre_archivo}
          <span className="block text-xs">El archivo de la evidencia no está disponible.</span>
        </span>
      </p>
    )
  }

  return (
    <div className="space-y-1">
      <button
        type="button"
        onClick={open}
        disabled={status === 'loading'}
        className="flex items-center gap-2 text-left text-sm font-medium text-brand-deep underline-offset-4 hover:underline focus-visible:underline disabled:opacity-60"
      >
        <Paperclip className="size-4 shrink-0" aria-hidden="true" />
        <span className="break-all">{evidence.nombre_archivo}</span>
      </button>

      <p className="text-xs text-muted-foreground">
        Evidencia{evidence.tamano_bytes !== null && ` · ${formatFileSize(evidence.tamano_bytes)}`}
        {status === 'loading' && ' · Abriendo…'}
      </p>

      {status === 'error' && message && (
        <p role="alert" className="text-xs text-danger-fg">
          {message}
        </p>
      )}
    </div>
  )
}

function Detail({ record }: { record: RiskCenterRecordDetail }) {
  const { estudiante, examen, ambiente, reportante, aprobador } = record
  const exam = [examen.nombre, examen.materia, examen.carrera, formatPlainDate(examen.fecha)].filter(Boolean).join(' · ')

  return (
    <div className="space-y-5 px-4">
      <div className="flex items-center gap-3">
        <Avatar className="size-10">
          <AvatarFallback className="bg-brand-soft text-sm font-semibold text-brand-deep" aria-hidden="true">
            {initialsOf(estudiante.nombre_completo)}
          </AvatarFallback>
        </Avatar>
        <div className="min-w-0">
          <p className="sciem-h3 truncate">{estudiante.nombre_completo}</p>
          <p className="sciem-tnum text-sm text-muted-foreground">
            {estudiante.cod_sis}
          </p>
        </div>
      </div>

      <FaultTypeBadge name={record.tipo_falta.nombre} />

      <dl className="space-y-3">
        <Field label="Examen">{exam}</Field>

        {ambiente && (
          <Field label="Ambiente">
            {ambiente.nro_aula ? `Aula ${ambiente.nro_aula}` : 'Aula sin número'} · {formatTime(ambiente.hora_intento)}
          </Field>
        )}

        <Field label="Reportó">
          {reportante.nombre_completo} · {formatDayMonthTime(reportante.fecha)}
        </Field>

        {aprobador && (
          <Field label="Aprobó">
            {aprobador.nombre_completo} · {formatDayMonthTime(aprobador.fecha)}
          </Field>
        )}

        <Field label="Observación">
          {record.observacion ?? <span className="text-muted-foreground">Sin observación</span>}
        </Field>

        <Field label="Registro">{formatDate(record.fecha_registro)}</Field>

        <Field label="Descripción">
          {record.descripcion ?? <span className="text-muted-foreground">Sin descripción</span>}
        </Field>

        {record.evidencia && (
          <Field label="Evidencia">
            <Evidence recordId={record.id_cr_estudiante} evidence={record.evidencia} />
          </Field>
        )}
      </dl>
    </div>
  )
}

/**
 * Detalle de un registro en un panel lateral (hoja a pantalla completa en móvil), de solo lectura.
 *
 * Se abre desde `/central-riesgo/:id`, así que un enlace directo muestra el listado con el panel. No
 * ofrece editar ni eliminar: lo único que se puede hacer con la evidencia es abrirla.
 */
export function RiskCenterDetailPanel({ id, onClose }: RiskCenterDetailPanelProps) {
  const { record, status, error, reload } = useRiskCenterRecord(id)

  return (
    <Sheet open onOpenChange={(open) => !open && onClose()}>
      <SheetContent
        side="right"
        showCloseButton={false}
        className="gap-0 overflow-y-auto data-[side=right]:w-full data-[side=right]:sm:max-w-md"
      >
        <SheetHeader className="flex-row items-start justify-between gap-3 border-b border-border-soft">
          <div className="space-y-1">
            <SheetTitle>Registro de la Central de Riesgo</SheetTitle>
            <SheetDescription className="flex items-center gap-1 text-xs">
              <Lock className="size-3" aria-hidden="true" />
              Solo lectura
            </SheetDescription>
          </div>

          <SheetClose
            aria-label="Cerrar detalle"
            className={cn(buttonVariants({ variant: 'ghost', size: 'icon-sm' }), 'shrink-0')}
          >
            <X className="size-4" aria-hidden="true" />
          </SheetClose>
        </SheetHeader>

        <div className="flex-1 py-4">
          {status === 'loading' && <LoadingState rows={6} label="Cargando el registro" />}

          {status === 'error' && error?.isNotFound && (
            <EmptyState
              icon={FileX}
              title="Registro no encontrado"
              description="Es posible que el registro ya no exista o que el enlace sea incorrecto."
            />
          )}

          {status === 'error' && error && !error.isNotFound && <ErrorState error={error} onRetry={reload} />}

          {status === 'success' && record && <Detail record={record} />}
        </div>

        <SheetFooter className="border-t border-border-soft">
          <p className="text-xs text-muted-foreground">Los registros no se editan ni se eliminan.</p>
          <SheetClose className={buttonVariants({ variant: 'outline' })}>Cerrar</SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
