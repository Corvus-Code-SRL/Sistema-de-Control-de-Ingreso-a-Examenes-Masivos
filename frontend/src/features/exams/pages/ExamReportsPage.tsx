import { FileText } from 'lucide-react'
import { Link } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow
} from '@/components/ui/table'
import { useFinishedExams } from '../hooks/useFinishedExams'
import { examFinalReportPath } from '../utils/paths'

function formatDate(date: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date)
  return match ? `${match[3]}/${match[2]}/${match[1]}` : date
}

/**
 * Punto de entrada del reporte final (HU-051): lista los exámenes finalizados del docente para
 * elegir cuál reportar, sin depender del resumen del examen.
 */
export function ExamReportsPage() {
  const { exams, isLoading, isEmpty, error, reload } = useFinishedExams()

  return (
    <AppShell
      mobileTitle="Generar reporte"
      breadcrumbs={[
        { label: 'Historial de exámenes y reportes' },
        { label: 'Generar reporte de examen' }
      ]}
    >
      <PageHeader
        title="Generar reporte de examen"
        subtitle="Elija uno de sus exámenes finalizados para ver su reporte final."
      />

      <Card className="overflow-hidden p-0">
        {isLoading && <LoadingState rows={4} label="Cargando sus exámenes finalizados" />}

        {!isLoading && error && <ErrorState error={error} onRetry={reload} />}

        {!isLoading && !error && isEmpty && (
          <EmptyState
            icon={FileText}
            title="No tiene exámenes finalizados"
            description="Cuando finalice un examen podrá generar su reporte final desde aquí."
          />
        )}

        {!isLoading && !error && !isEmpty && (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="sciem-overline text-muted-foreground">
                  Examen / materia
                </TableHead>
                <TableHead className="sciem-overline text-muted-foreground">Fecha</TableHead>
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
                    <p className="sciem-tnum">{formatDate(exam.fecha)}</p>
                    <p className="text-xs text-muted-foreground">
                      {exam.hora_inicio.slice(0, 5)}–{exam.hora_fin.slice(0, 5)}
                    </p>
                  </TableCell>

                  <TableCell className="text-right">
                    <Button variant="outline" size="sm" asChild>
                      <Link to={examFinalReportPath(exam.id_examen)}>Generar reporte</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>
    </AppShell>
  )
}
