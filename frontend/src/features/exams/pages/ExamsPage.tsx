import { CalendarPlus, Plus } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { AppShell } from '@/components/layout/AppShell';
import { EmptyState } from '@/components/common/EmptyState';
import { ErrorState } from '@/components/common/ErrorState';
import { LoadingState } from '@/components/common/LoadingState';
import { PageHeader } from '@/components/common/PageHeader';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useExams } from '../hooks/useExams';
import { ExamStatusBadge } from '../components/ExamStatusBadge';
import { Exam } from '../types/exams.types';

function totalStudents(exam: Exam): number {
  return (exam.grupos ?? []).reduce((total, group) => total + group.cantidad_estudiantes, 0);
}

/**
 * Exámenes del docente actual, ordenados por fecha (HU-24 criterios 10-12).
 *
 * Es el punto de entrada para reabrir un examen PROGRAMADO y editarlo o
 * gestionar sus grupos (HU-25 criterio 7).
 */
export function ExamsPage() {
  const navigate = useNavigate();
  const { exams, isLoading, isEmpty, error, reload } = useExams();

  return (
    <AppShell
      mobileTitle="Programados"
      breadcrumbs={[{ label: 'Exámenes' }, { label: 'Programados' }]}
    >
      <PageHeader
        title="Exámenes programados"
        subtitle="Sus exámenes del período vigente, del más próximo al más lejano."
        actions={
          <Button onClick={() => navigate('/examenes/nuevo')}>
            <Plus className="size-4" aria-hidden="true" />
            Nuevo examen
          </Button>
        }
      />

      <Card className="overflow-hidden p-0">
        {isLoading && <LoadingState rows={4} label="Cargando sus exámenes" />}

        {!isLoading && error && <ErrorState error={error} onRetry={reload} />}

        {!isLoading && !error && isEmpty && (
          <EmptyState
            icon={CalendarPlus}
            title="No tiene exámenes programados"
            description="Programe una fecha de evaluación para su asignatura: podrá vincular sus grupos, asignar ambientes y luego habilitar el control de ingreso."
            action={
              <Button onClick={() => navigate('/examenes/nuevo')}>
                <Plus className="size-4" aria-hidden="true" />
                Programar mi primer examen
              </Button>
            }
          />
        )}

        {!isLoading && !error && !isEmpty && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="sciem-overline text-muted-foreground">Examen / materia</TableHead>
                <TableHead className="sciem-overline text-muted-foreground">Fecha y horario</TableHead>
                <TableHead className="sciem-overline text-muted-foreground">Ambientes</TableHead>
                <TableHead className="sciem-overline text-right text-muted-foreground">Estudiantes</TableHead>
                <TableHead className="sciem-overline text-muted-foreground">Estado</TableHead>
                <TableHead className="text-right">
                  <span className="sr-only">Acciones</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {exams.map((exam) => (
                <TableRow key={exam.id_examen}>
                  <TableCell>
                    <p className="font-medium">{exam.nombre_examen}</p>
                    <p className="text-xs text-muted-foreground">
                      {exam.materia?.nombre} · {exam.carrera?.nombre}
                    </p>
                  </TableCell>

                  <TableCell>
                    <p className="sciem-tnum">{exam.fecha}</p>
                    <p className="text-xs text-muted-foreground">
                      {exam.hora_inicio}–{exam.hora_fin} ({exam.duracion} min)
                    </p>
                  </TableCell>

                  <TableCell>
                    {(exam.ambientes ?? []).map((room) => room.nro_aula).join(', ') || '—'}
                  </TableCell>

                  <TableCell className="text-right">
                    <p className="sciem-tnum font-medium">{totalStudents(exam)}</p>
                    <p className="text-xs text-muted-foreground">
                      {(exam.grupos ?? []).length} grupo(s)
                    </p>
                  </TableCell>

                  <TableCell>
                    <ExamStatusBadge status={exam.estado} />
                  </TableCell>

                  <TableCell className="text-right">
                    <Button variant="outline" size="sm" onClick={() => navigate(`/examenes/${exam.id_examen}`)}>
                      {exam.estado === 'PROGRAMADO' ? 'Configurar' : 'Ver detalle'}
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>
    </AppShell>
  );
}
