import { ClipboardCheck, UserSearch } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { useStudentRecords } from '../hooks/useStudentRecords'
import { RectifiedRecordList } from './RectifiedRecordList'
import { RectifyRecordList } from './RectifyRecordList'

interface RectifyStudentRecordsProps {
  studentId: number
  onRectify: (recordId: number) => void
  onNewSearch: () => void
}

/**
 * Los registros activos de un estudiante (los que se pueden rectificar) y, aparte y de solo lectura, los que
 * ya se rectificaron. Un estudiante sin registros activos muestra el aviso y, si los tiene, sus rectificados.
 */
export function RectifyStudentRecords({ studentId, onRectify, onNewSearch }: RectifyStudentRecordsProps) {
  const { records, status, error, reload } = useStudentRecords(studentId)

  return (
    <div className="space-y-4">
      <Button type="button" variant="outline" size="sm" onClick={onNewSearch}>
        <UserSearch className="size-4" aria-hidden="true" />
        Buscar otro estudiante
      </Button>

      <Card className="overflow-hidden p-0" aria-busy={status === 'loading'}>
        {records === null && status === 'loading' && <LoadingState rows={4} label="Cargando registros" />}

        {status === 'error' && error !== null && <ErrorState error={error} onRetry={reload} />}

        {records !== null && status !== 'error' && (
          <>
            <div className="border-b border-border px-5 py-4">
              <h2 className="sciem-h3">{records.estudiante.nombre_completo}</h2>
              <p className="text-sm text-muted-foreground">
                Código SIS {records.estudiante.cod_sis} · Carnet {records.estudiante.ci ?? 'sin dato'}
              </p>
            </div>

            {records.registros.length === 0 ? (
              <EmptyState
                icon={ClipboardCheck}
                title="El estudiante no tiene registros activos en la Central de Riesgo"
                description="No hay nada que rectificar para este estudiante."
              />
            ) : (
              <RectifyRecordList
                records={records.registros}
                onRectify={(record) => onRectify(record.id_cr_estudiante)}
              />
            )}
          </>
        )}
      </Card>

      {records !== null && status !== 'error' && records.rectificados.length > 0 && (
        <Card className="overflow-hidden p-0">
          <div className="border-b border-border px-5 py-4">
            <h2 className="sciem-h3">Registros rectificados</h2>
            <p className="text-sm text-muted-foreground">
              Ya no figuran en la Central de Riesgo. Solo se consultan: no se pueden reactivar.
            </p>
          </div>

          <RectifiedRecordList records={records.rectificados} />
        </Card>
      )}
    </div>
  )
}
