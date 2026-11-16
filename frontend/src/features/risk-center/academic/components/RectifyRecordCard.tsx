import type { ReactNode } from 'react'
import { FileX, Lock, Paperclip } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { useEvidenceDownload } from '../hooks/useEvidenceDownload'
import type { RectifiableRecordDetail } from '../types/riskRectification.types'
import { formatDate, formatDayMonthTime, formatFileSize, formatPlainDate, formatTime } from '../utils/format'
import { FaultTypeBadge } from './FaultTypeBadge'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="space-y-0.5">
      <dt className="sciem-overline text-muted-foreground">{label}</dt>
      <dd className="text-sm text-foreground">{children}</dd>
    </div>
  )
}

function Evidence({
  recordId,
  evidence,
}: {
  recordId: number
  evidence: NonNullable<RectifiableRecordDetail['evidencia']>
}) {
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

/**
 * Toda la información del registro que se va a rectificar, de solo lectura y a lo largo de la pantalla, para
 * que el administrador revise antes de decidir. No es el formulario de reporte de incidencias: no se edita nada.
 */
export function RectifyRecordCard({ record }: { record: RectifiableRecordDetail }) {
  const { estudiante, examen, ambiente, reportante, aprobador } = record

  return (
    <Card className="space-y-5 p-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="sciem-h3">Registro de la Central de Riesgo</h2>
          <p className="flex items-center gap-1 text-sm text-muted-foreground">
            <Lock className="size-3.5" aria-hidden="true" />
            Solo lectura: revise el registro antes de rectificarlo
          </p>
        </div>
        <FaultTypeBadge name={record.tipo_falta.nombre} />
      </div>

      <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
        <Field label="Estudiante">{estudiante.nombre_completo}</Field>
        <Field label="Código SIS">
          <span className="sciem-tnum">{estudiante.cod_sis}</span>
        </Field>
        <Field label="Carnet de identidad">
          <span className="sciem-tnum">{estudiante.ci ?? 'Sin dato'}</span>
        </Field>
        <Field label="Examen">{examen.nombre}</Field>
        <Field label="Materia">{examen.materia ?? 'Sin materia'}</Field>
        <Field label="Fecha del examen">{formatPlainDate(examen.fecha)}</Field>
        <Field label="Fecha y hora del hecho">
          {formatDate(record.hecho.fecha_hora)} · {formatTime(record.hecho.fecha_hora)}
        </Field>
        {ambiente !== null && (
          <Field label="Aula">{ambiente.nro_aula ? `Aula ${ambiente.nro_aula}` : 'Aula sin número'}</Field>
        )}
        <Field label="Tipo de falta">{record.tipo_falta.nombre}</Field>
        <Field label="Reportó">
          {reportante.nombre_completo} · {formatDayMonthTime(reportante.fecha)}
        </Field>
        <Field label="Aprobó">
          {aprobador === null ? (
            <span className="text-muted-foreground">Sin dato</span>
          ) : (
            <>
              {aprobador.nombre_completo} · {formatDayMonthTime(aprobador.fecha)}
            </>
          )}
        </Field>
        <Field label="Ingresó a la Central">{formatDate(record.fecha_registro)}</Field>
      </dl>

      <dl className="space-y-4">
        <Field label="Descripción">
          <span className="whitespace-pre-line break-words">
            {record.descripcion ?? <span className="text-muted-foreground">Sin descripción</span>}
          </span>
        </Field>

        <Field label="Evidencia">
          {record.evidencia === null ? (
            <span className="text-muted-foreground">Sin evidencia</span>
          ) : (
            <Evidence recordId={record.id_cr_estudiante} evidence={record.evidencia} />
          )}
        </Field>
      </dl>
    </Card>
  )
}
