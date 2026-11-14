import { SearchX, Users } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { EmptyState } from '@/components/common/EmptyState'
import { FormSelect } from '@/components/common/FormSelect'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { normalizeSearch } from '@/lib/text'
import type { AttendanceStatus, ExamAttendanceDetail } from '../types/examAttendance.types'

const STATUS: Record<AttendanceStatus, { label: string; total: keyof ExamAttendanceDetail['totales']; tone: string }> = {
  PENDIENTE: { label: 'Pendiente', total: 'pendientes', tone: 'bg-neutral-soft text-neutral-fg' },
  A_TIEMPO: { label: 'A tiempo', total: 'a_tiempo', tone: 'bg-ok-soft text-ok-fg' },
  CON_RETRASO: { label: 'Con retraso', total: 'con_retraso', tone: 'bg-warn-soft text-warn-fg' },
  AUSENTE: { label: 'Ausente', total: 'ausentes', tone: 'bg-neutral-soft text-neutral-fg' },
  EXPULSADO: { label: 'Expulsado', total: 'expulsados', tone: 'bg-danger-soft text-danger-fg' },
  NO_HABILITADO: { label: 'No habilitado', total: 'no_habilitados', tone: 'bg-danger-soft text-danger-fg' },
  SIN_CLASIFICAR: { label: 'Sin clasificar', total: 'sin_clasificar', tone: 'bg-neutral-soft text-neutral-fg' },
}
const STATUS_OPTIONS = Object.entries(STATUS)

const FILTER_KEYS = ['q', 'carrera', 'estado', 'ambiente'] as const
const ALL = 'todos'
const FILTER_CONTENT_CLASS = 'w-(--radix-select-trigger-width) min-w-0 max-w-(--radix-select-content-available-width) [&_[data-slot=select-item]]:wrap-anywhere'

export function clearAttendanceFilters(params: URLSearchParams) {
  FILTER_KEYS.forEach((key) => params.delete(key))
}

export function ExamAttendanceRoster({ detail }: { detail: ExamAttendanceDetail }) {
  const [params, setParams] = useSearchParams()
  const { ambientes, estudiantes, totales } = detail
  const canFilterRoom = detail.id_ambiente_asignado === null && ambientes.length > 0
  const careers = new Map<number, string>()
  for (const { carrera } of estudiantes) {
    if (carrera) careers.set(carrera.id_carrera, carrera.nombre)
  }

  const search = params.get('q') ?? ''
  const career = [...careers.keys()].find((id) => String(id) === params.get('carrera'))
  const state = STATUS_OPTIONS.find(([key]) => key === params.get('estado'))?.[0]
  const room = canFilterRoom
    ? ambientes.find(({ id_ambiente }) => String(id_ambiente) === params.get('ambiente'))?.id_ambiente
    : undefined
  const terms = normalizeSearch(search).split(/\s+/).filter(Boolean)
  const visibleStudents = estudiantes.filter((student) => {
    if (career !== undefined && student.carrera?.id_carrera !== career) return false
    if (state !== undefined && student.estado !== state) return false
    if (room !== undefined && student.id_ambiente !== room) return false
    const identity = normalizeSearch([student.nombre_completo, student.cod_sis, student.ci].join(' '))
    return terms.every((term) => identity.includes(term))
  })

  function updateFilter(key: typeof FILTER_KEYS[number], value: string) {
    setParams((current) => {
      const next = new URLSearchParams(current)
      if (!value || (value === ALL && key !== 'q')) next.delete(key)
      else next.set(key, value)
      return next
    }, { replace: key === 'q' })
  }

  function clearFilters() {
    setParams((current) => {
      const next = new URLSearchParams(current)
      clearAttendanceFilters(next)
      return next
    })
  }

  return (
    <>
      <Card>
        <CardContent>
          <dl aria-label="Totales de asistencia" className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div className="rounded-lg bg-muted px-3 py-2">
              <dt className="text-sm text-muted-foreground">Total de estudiantes</dt>
              <dd className="sciem-h3 sciem-tnum">{totales.total}</dd>
            </div>
            {STATUS_OPTIONS.map(([key, info]) => (
              <div key={key} className="rounded-lg border px-3 py-2">
                <dt className="text-sm text-muted-foreground">{info.label}</dt>
                <dd className="sciem-h3 sciem-tnum">{totales[info.total]}</dd>
              </div>
            ))}
          </dl>
        </CardContent>
      </Card>
      {estudiantes.length === 0 ? (
        <EmptyState icon={Users} title="No hay estudiantes disponibles para este examen"
          description="Seleccione otro examen para consultar su nómina." />
      ) : (
        <Card>
          <CardContent className="min-w-0 space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="min-w-0 space-y-2">
                <Label htmlFor="attendance-search">Nombre, SIS o CI</Label>
                <Input id="attendance-search" type="search" value={search}
                  onChange={(event) => updateFilter('q', event.target.value)} />
              </div>
              <div className="min-w-0 space-y-2">
                <Label htmlFor="attendance-career">Carrera</Label>
                <FormSelect id="attendance-career" value={career === undefined ? ALL : String(career)}
                  onValueChange={(value) => updateFilter('carrera', value)} placeholder="Todas las carreras"
                  className="min-w-0 [&_[data-slot=select-value]]:truncate"
                  contentClassName={FILTER_CONTENT_CLASS}
                  options={[{ value: ALL, label: 'Todas las carreras' }, ...[...careers].map(([id, label]) => ({
                    value: String(id), label,
                  }))]} />
              </div>
              <div className="min-w-0 space-y-2">
                <Label htmlFor="attendance-status">Estado</Label>
                <FormSelect id="attendance-status" value={state ?? ALL} placeholder="Todos los estados"
                  onValueChange={(value) => updateFilter('estado', value)}
                  contentClassName={FILTER_CONTENT_CLASS}
                  options={[{ value: ALL, label: 'Todos los estados' }, ...STATUS_OPTIONS.map(([value, info]) => ({
                    value, label: info.label,
                  }))]} />
              </div>
              {canFilterRoom && (
                <div className="min-w-0 space-y-2">
                  <Label htmlFor="attendance-room">Ambiente</Label>
                  <FormSelect id="attendance-room" value={room === undefined ? ALL : String(room)}
                    placeholder="Todos los ambientes" onValueChange={(value) => updateFilter('ambiente', value)}
                    className="min-w-0 [&_[data-slot=select-value]]:truncate"
                    contentClassName={FILTER_CONTENT_CLASS}
                    options={[{ value: ALL, label: 'Todos los ambientes' }, ...ambientes.map((item) => ({
                      value: String(item.id_ambiente), label: item.nro_aula,
                    }))]} />
                </div>
              )}
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p role="status" className="text-sm text-muted-foreground">
                {visibleStudents.length} de {estudiantes.length} estudiantes
              </p>
              {FILTER_KEYS.some((key) => params.has(key)) && (
                <Button variant="ghost" onClick={clearFilters}>Limpiar filtros</Button>
              )}
            </div>
            {visibleStudents.length === 0 ? (
              <EmptyState icon={SearchX} title="No hay estudiantes que coincidan con los filtros"
                description="Cambie la búsqueda o limpie los filtros para ver más estudiantes." />
            ) : (
              <div role="region" aria-label="Nómina de asistencia" tabIndex={0}
                className="overflow-x-auto rounded-md [&_[data-slot=table-container]]:overflow-visible">
                <Table aria-label="Estudiantes del examen">
                  <TableHeader>
                    <TableRow>
                      <TableHead scope="col">Nombre completo</TableHead>
                      <TableHead scope="col">SIS</TableHead>
                      <TableHead scope="col">CI</TableHead>
                      <TableHead scope="col">Carrera</TableHead>
                      <TableHead scope="col">Ambiente</TableHead>
                      <TableHead scope="col">Estado</TableHead>
                      <TableHead scope="col">Hora de ingreso</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {visibleStudents.map((student) => {
                      const info = STATUS[student.estado]
                      return (
                        <TableRow key={student.id_estudiante}>
                          <TableCell className="min-w-36 whitespace-normal wrap-anywhere font-medium">
                            {student.nombre_completo}
                          </TableCell>
                          <TableCell className="sciem-tnum">{student.cod_sis}</TableCell>
                          <TableCell className="sciem-tnum">{student.ci ?? '—'}</TableCell>
                          <TableCell className="min-w-28 whitespace-normal wrap-anywhere">
                            {student.carrera?.nombre ?? '—'}
                          </TableCell>
                          <TableCell>{student.ambiente ?? '—'}</TableCell>
                          <TableCell><Badge variant="secondary" className={info.tone}>{info.label}</Badge></TableCell>
                          <TableCell className="sciem-tnum">{student.hora_ingreso ?? '—'}</TableCell>
                        </TableRow>
                      )
                    })}
                  </TableBody>
                </Table>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </>
  )
}
