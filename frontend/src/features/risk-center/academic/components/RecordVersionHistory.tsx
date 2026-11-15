import { formatDate, formatTime } from '../utils/format'
import type { RecordVersion } from '../types/riskRectification.types'
import { FaultTypeBadge } from './FaultTypeBadge'
import { VersionStateBadge } from './VersionStateBadge'

interface RecordVersionHistoryProps {
  /** Ya en el orden del servidor: la versión más nueva primero. */
  versions: RecordVersion[]
}

/**
 * Historial completo de un registro: cada versión con su estado, su fecha y su autor y, en las
 * rectificaciones, el motivo. La vigente se marca «Vigente»; las demás, «Anterior». Solo lectura.
 */
export function RecordVersionHistory({ versions }: RecordVersionHistoryProps) {
  return (
    <section aria-labelledby="record-history-title" className="space-y-3">
      <h2 id="record-history-title" className="sciem-h3">
        Historial de versiones
      </h2>

      <ol className="space-y-3">
        {versions.map((version) => (
          <li
            key={version.numero_version}
            aria-label={`Versión ${version.numero_version}, ${version.estado}`}
            className="space-y-2 rounded-lg border border-border p-4"
          >
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-sm font-semibold text-foreground">Versión {version.numero_version}</span>
              <VersionStateBadge state={version.estado} />
              <FaultTypeBadge name={version.tipo_falta.nombre} />
            </div>

            <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
              <dt className="text-muted-foreground">Fecha</dt>
              <dd>
                {formatDate(version.fecha)} {formatTime(version.fecha)}
              </dd>

              <dt className="text-muted-foreground">Autor</dt>
              <dd>{version.autor.nombre_completo}</dd>

              <dt className="text-muted-foreground">Descripción</dt>
              <dd className="whitespace-pre-line break-words">{version.descripcion ?? 'Sin descripción'}</dd>

              <dt className="text-muted-foreground">Motivo</dt>
              <dd className="whitespace-pre-line break-words">
                {version.motivo ?? 'Registro original, sin rectificación'}
              </dd>
            </dl>
          </li>
        ))}
      </ol>
    </section>
  )
}
