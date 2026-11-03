import type { ReactNode } from 'react'
import { EyeOff, FileX, Lock, X } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
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
import { useAuditLogDetail } from '../hooks/useAuditLogDetail'
import type { AuditChange, AuditLogDetail } from '../types/auditLog.types'
import { formatDateTime, formatValue } from '../utils/format'
import { ActorLabel } from './ActorLabel'

interface AuditLogDetailPanelProps {
  /** Operación abierta (`?detalle=<id>`); null si el id de la URL no es válido. */
  id: number | null
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

function ChangeRow({ change }: { change: AuditChange }) {
  if (change.oculto) {
    return (
      <li className="space-y-0.5 py-3">
        <p className="text-sm font-medium text-foreground">{change.etiqueta}</p>
        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
          <EyeOff className="size-4 shrink-0" aria-hidden="true" />
          (oculto)
        </p>
      </li>
    )
  }

  return (
    <li className="space-y-1 py-3">
      <p className="text-sm font-medium text-foreground">{change.etiqueta}</p>
      <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
        <dt className="text-muted-foreground">Anterior</dt>
        <dd className="break-words text-foreground">{formatValue(change.anterior)}</dd>
        <dt className="text-muted-foreground">Nuevo</dt>
        <dd className="break-words text-foreground">{formatValue(change.nuevo)}</dd>
      </dl>
    </li>
  )
}

function Detail({ entry }: { entry: AuditLogDetail }) {
  const { docente_examen: teacher } = entry

  return (
    <div className="space-y-5 px-4">
      <dl className="space-y-3">
        <Field label="Fecha y hora">
          <span className="sciem-tnum">{formatDateTime(entry.fecha_hora)}</span>
        </Field>

        <Field label="Usuario">
          <ActorLabel actor={entry.usuario} />
        </Field>

        {teacher && (
          <Field label="Docente del examen">
            {teacher.nombre_completo} ({teacher.cod_sis})
          </Field>
        )}

        <Field label="Acción">{entry.accion.etiqueta}</Field>
        <Field label="Módulo">{entry.modulo.etiqueta}</Field>
        <Field label="Registro afectado">{entry.registro.descripcion}</Field>
      </dl>

      <section aria-labelledby="audit-log-changes" className="space-y-1">
        <h3 id="audit-log-changes" className="sciem-overline text-muted-foreground">
          Cambios
        </h3>

        {entry.cambios.length === 0 ? (
          <p className="text-sm text-muted-foreground">Esta operación no registró cambios de campos.</p>
        ) : (
          <ul className="divide-y divide-border-soft">
            {entry.cambios.map((change) => (
              <ChangeRow key={change.campo} change={change} />
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

/**
 * Detalle de una operación en un panel lateral (hoja a pantalla completa en móvil), de solo lectura.
 *
 * Se abre con `?detalle=<id>` sobre el listado. Un id inválido, inexistente o sin permiso muestra el error
 * dentro del panel; cerrarlo quita el parámetro y conserva los filtros.
 */
export function AuditLogDetailPanel({ id, onClose }: AuditLogDetailPanelProps) {
  return (
    <Sheet open onOpenChange={(open) => !open && onClose()}>
      <SheetContent
        side="right"
        showCloseButton={false}
        className="gap-0 overflow-y-auto data-[side=right]:w-full data-[side=right]:sm:max-w-md"
      >
        <SheetHeader className="flex-row items-start justify-between gap-3 border-b border-border-soft">
          <div className="space-y-1">
            <SheetTitle>Detalle de la operación</SheetTitle>
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
          {id === null ? <InvalidId /> : <Loaded id={id} />}
        </div>

        <SheetFooter className="border-t border-border-soft">
          <p className="text-xs text-muted-foreground">La bitácora no se edita ni se elimina.</p>
          <SheetClose className={buttonVariants({ variant: 'outline' })}>Cerrar</SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}

function InvalidId() {
  return (
    <EmptyState
      icon={FileX}
      title="Operación no encontrada"
      description="El enlace no corresponde a ninguna operación de la bitácora."
    />
  )
}

function Loaded({ id }: { id: number }) {
  const { entry, status, error, reload } = useAuditLogDetail(id)

  if (status === 'loading') return <LoadingState rows={6} label="Cargando la operación" />

  if (status === 'error' && error) {
    return error.isNotFound ? <InvalidId /> : <ErrorState error={error} onRetry={reload} />
  }

  return entry ? <Detail entry={entry} /> : null
}
