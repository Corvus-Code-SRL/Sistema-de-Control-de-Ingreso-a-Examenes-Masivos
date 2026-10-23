import { CalendarClock, ChevronRight, Plus } from 'lucide-react'
import { Link } from 'react-router-dom'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { ExamStatusBadge, examDetailPath } from '@/features/exams'
import { GroupContextLayout } from '../components/GroupContextLayout'
import { useGroupExams } from '../hooks/useGroupExams'
import { courseTitle, type GroupDetail } from '../types/group.types'
import { groupCreateExamPath, groupDetailPath } from '../utils/paths'

export function GroupExamsPage() {
  return (
    <GroupContextLayout title="Exámenes del grupo">
      {(detail) => <GroupExamsView detail={detail} />}
    </GroupContextLayout>
  )
}

function GroupExamsView({ detail }: { detail: GroupDetail }) {
  const groupId = detail.group.id_grupo
  const resource = useGroupExams(groupId)

  return (
    <>
      <PageHeader
        title="Exámenes del grupo"
        subtitle={`${courseTitle(detail.subject, detail.group)} · ${detail.subject.carrera.nombre} · ${detail.group.periodo.etiqueta}`}
        backTo={groupDetailPath(groupId)}
        backLabel="Volver al grupo"
        actions={
          <Button asChild>
            <Link to={groupCreateExamPath(groupId)}>
              <Plus className="size-4" aria-hidden="true" />
              Nuevo examen
            </Link>
          </Button>
        }
      />

      <Card className="overflow-hidden p-0">
        {resource.status === 'loading' && (
          <LoadingState rows={3} label="Cargando los exámenes del grupo" />
        )}
        {resource.status === 'error' && resource.error && (
          <ErrorState error={resource.error} onRetry={resource.reload} />
        )}
        {resource.status === 'success' && resource.data?.length === 0 && (
          <EmptyState
            icon={CalendarClock}
            title="Este grupo aún no participa en ningún examen"
            description="Los exámenes asociados a este grupo aparecerán aquí."
          />
        )}
        {resource.status === 'success' && (resource.data?.length ?? 0) > 0 && (
          <>
            <div className="hidden md:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Examen</TableHead>
                    <TableHead>Fecha y horario</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead><span className="sr-only">Acciones</span></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {resource.data?.map((exam) => (
                    <TableRow key={exam.id_examen}>
                      <TableCell className="font-medium">{exam.nombre_examen}</TableCell>
                      <TableCell className="sciem-tnum">
                        {exam.fecha}
                        <p className="text-xs text-muted-foreground">
                          {exam.hora_inicio}{exam.hora_fin ? `–${exam.hora_fin}` : ''}
                        </p>
                      </TableCell>
                      <TableCell><ExamStatusBadge status={exam.estado} /></TableCell>
                      <TableCell className="text-right">
                        <Button asChild variant="outline" size="sm">
                          <Link to={examDetailPath(exam.id_examen, groupId)}>
                          Ver detalle
                          <ChevronRight className="size-4" aria-hidden="true" />
                        </Link>
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
            <ul aria-label="Exámenes del grupo" className="md:hidden">
              {resource.data?.map((exam) => (
                <li key={exam.id_examen} className="space-y-3 border-b border-border px-4 py-3 last:border-b-0">
                  <h2 className="break-words text-sm font-medium">{exam.nombre_examen}</h2>
                  <dl className="grid grid-cols-2 gap-3 text-xs">
                    <div>
                      <dt className="text-muted-foreground">Fecha</dt>
                      <dd className="sciem-tnum"><time dateTime={exam.fecha}>{exam.fecha}</time></dd>
                    </div>
                    <div>
                      <dt className="text-muted-foreground">Horario</dt>
                      <dd className="sciem-tnum">
                        {exam.hora_inicio}{exam.hora_fin ? `–${exam.hora_fin}` : ''}
                      </dd>
                    </div>
                  </dl>
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <ExamStatusBadge status={exam.estado} />
                    <Button asChild variant="outline" size="sm">
                      <Link to={examDetailPath(exam.id_examen, groupId)} aria-label={`Ver detalle de ${exam.nombre_examen}`}>
                        Ver detalle
                        <ChevronRight className="size-4" aria-hidden="true" />
                      </Link>
                    </Button>
                  </div>
                </li>
              ))}
            </ul>
          </>
        )}
      </Card>
    </>
  )
}
