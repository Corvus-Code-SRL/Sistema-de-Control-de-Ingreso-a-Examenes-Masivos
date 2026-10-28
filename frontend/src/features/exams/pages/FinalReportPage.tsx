import { Link, useParams } from 'react-router-dom'
import { Printer, ShieldAlert } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useFinalReport } from '../hooks/useFinalReport'
import { examDetailPath } from '../utils/paths'
import type { FinalReport } from '../types/finalReport.types'

const NO_INCIDENTS_MESSAGE = 'No se registraron incidencias en este examen'

function formatDate(date: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date)
  return match ? `${match[3]}/${match[2]}/${match[1]}` : date
}

function formatGeneratedAt(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString('es-BO')
}

function orDash(value: string | null): string {
  return value ?? '—'
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="space-y-3">
      <h2 className="sciem-h3">{title}</h2>
      {children}
    </section>
  )
}

function HeaderSection({ report }: { report: FinalReport }) {
  const { cabecera } = report
  const items = [
    ['Examen', cabecera.nombre_examen],
    ['Materia', orDash(cabecera.materia)],
    ['Docente', orDash(cabecera.docente)],
    ['Fecha del examen', formatDate(cabecera.fecha)],
    ['Hora de inicio', orDash(cabecera.hora_inicio)],
    ['Hora de fin', orDash(cabecera.hora_fin)],
    ['Reporte generado', formatGeneratedAt(cabecera.generado_en)],
  ]

  return (
    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {items.map(([label, value]) => (
        <div key={label}>
          <dt className="text-xs text-muted-foreground">{label}</dt>
          <dd className="text-sm font-medium">{value}</dd>
        </div>
      ))}
    </dl>
  )
}

function AttendanceSection({ report }: { report: FinalReport }) {
  const { grupos, totales } = report.asistencia

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Grupo</TableHead>
            <TableHead className="text-right">Inscritos</TableHead>
            <TableHead className="text-right">A tiempo</TableHead>
            <TableHead className="text-right">Con retraso</TableHead>
            <TableHead className="text-right">Ausentes</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {grupos.map((group) => (
            <TableRow key={group.id_grupo}>
              <TableCell>{group.num_grupo}</TableCell>
              <TableCell className="text-right">{group.inscritos}</TableCell>
              <TableCell className="text-right">{group.a_tiempo}</TableCell>
              <TableCell className="text-right">{group.con_retraso}</TableCell>
              <TableCell className="text-right">{group.ausentes}</TableCell>
            </TableRow>
          ))}
          <TableRow className="font-semibold">
            <TableCell>Total</TableCell>
            <TableCell className="text-right">{totales.inscritos}</TableCell>
            <TableCell className="text-right">{totales.a_tiempo}</TableCell>
            <TableCell className="text-right">{totales.con_retraso}</TableCell>
            <TableCell className="text-right">{totales.ausentes}</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  )
}

function StudentsSection({ report }: { report: FinalReport }) {
  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Código SIS</TableHead>
            <TableHead>Nombre</TableHead>
            <TableHead>Aula</TableHead>
            <TableHead>Hora de ingreso</TableHead>
            <TableHead>Estado</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {report.estudiantes.map((student) => (
            <TableRow key={student.cod_sis}>
              <TableCell>{student.cod_sis}</TableCell>
              <TableCell>{student.nombre}</TableCell>
              <TableCell>{orDash(student.ambiente)}</TableCell>
              <TableCell>{orDash(student.hora_ingreso)}</TableCell>
              <TableCell>{student.estado}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}

function IncidentsSection({ report }: { report: FinalReport }) {
  if (report.incidencias.length === 0) {
    return <p className="text-sm text-muted-foreground">{NO_INCIDENTS_MESSAGE}</p>
  }

  return (
    <div className="overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Hora</TableHead>
            <TableHead>Estudiante</TableHead>
            <TableHead>Tipo</TableHead>
            <TableHead>Decisión del docente</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {report.incidencias.map((incident, index) => (
            <TableRow key={`${incident.hora}-${index}`}>
              <TableCell>{incident.hora}</TableCell>
              <TableCell>{orDash(incident.estudiante)}</TableCell>
              <TableCell>{orDash(incident.tipo)}</TableCell>
              <TableCell>
                {incident.decision}
                {incident.observacion ? `: ${incident.observacion}` : ''}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}

export function FinalReportPage() {
  const { examId } = useParams<{ examId: string }>()
  const { report, loading, notFound, loadError, reload } = useFinalReport(examId)

  const summaryPath = examDetailPath(Number(examId))
  const breadcrumbs = [
    { label: 'Exámenes' },
    { label: 'Resumen del examen', to: summaryPath },
    { label: 'Reporte final' },
  ]

  if (notFound) {
    return (
      <AppShell mobileTitle="Reporte final" breadcrumbs={breadcrumbs}>
        <EmptyState
          icon={ShieldAlert}
          title="Examen no encontrado"
          description="El examen no existe o ya no está disponible."
        />
      </AppShell>
    )
  }

  return (
    <AppShell mobileTitle="Reporte final" breadcrumbs={breadcrumbs}>
      <PageHeader
        title="Reporte final"
        subtitle={report?.cabecera.nombre_examen}
        backTo={summaryPath}
        backLabel="Resumen del examen"
        actions={
          <>
            <Button variant="outline" className="print:hidden" asChild>
              <Link to={summaryPath}>Resumen del examen</Link>
            </Button>
            <Button className="print:hidden" onClick={() => window.print()} disabled={!report}>
              <Printer className="size-4" />
              Imprimir
            </Button>
          </>
        }
      />

      {loading && <LoadingState label="Cargando reporte final" />}

      {!loading && loadError && <ErrorState error={loadError} onRetry={reload} />}

      {!loading && report && (
        <div className="space-y-8">
          <Section title="Datos del examen">
            <HeaderSection report={report} />
          </Section>

          <Section title="Asistencia por grupo">
            <AttendanceSection report={report} />
          </Section>

          <Section title="Estudiantes">
            <StudentsSection report={report} />
          </Section>

          <Section title="Incidencias">
            <IncidentsSection report={report} />
          </Section>
        </div>
      )}
    </AppShell>
  )
}
