import { useCallback, useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { Ban, Building2, DoorOpen, Pencil, Users } from 'lucide-react'
import { ApiError } from '@/lib/api-client'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { ShieldAlert } from 'lucide-react'
import { examsService } from '../services/examsService'
import { ExamStatusBadge } from '../components/ExamStatusBadge'
import { examLockedMessage } from '../utils/examLockedMessage'
import type { Exam } from '../types/exams.types'

export function ExamDetailPage() {
  const navigate = useNavigate()
  const { examId } = useParams<{ examId: string }>()
  const id = Number(examId)

  const [exam, setExam] = useState<Exam | null>(null)
  const [loading, setLoading] = useState(true)
  const [notFound, setNotFound] = useState(false)
  const [forbidden, setForbidden] = useState(false)
  const [loadError, setLoadError] = useState<ApiError | null>(null)

  const [showCancelDialog, setShowCancelDialog] = useState(false)
  const [showEnvironmentInfo, setShowEnvironmentInfo] = useState(false)
  const [cancelling, setCancelling] = useState(false)
  const [cancelError, setCancelError] = useState<string | null>(null)

  const backTo = '/examenes/programados'

  const load = useCallback(async (signal?: AbortSignal) => {
    setLoading(true)
    setNotFound(false)
    setForbidden(false)
    setLoadError(null)

    if (
      !examId ||
      !/^[0-9]{1,9}$/.test(examId) ||
      !Number.isSafeInteger(id) ||
      id <= 0
    ) {
      setNotFound(true)
      setLoading(false)
      return
    }

    try {
      const data = await examsService.getExam(id, signal)
      if (!signal?.aborted) setExam(data)
    } catch (error: unknown) {
      if (signal?.aborted) return

      if (error instanceof ApiError && error.isNotFound) {
        setNotFound(true)
      } else if (error instanceof ApiError && error.isForbidden) {
        setForbidden(true)
      } else {
        setLoadError(
          error instanceof ApiError
            ? error
            : new ApiError(
                0,
                error instanceof Error
                  ? error.message
                  : 'No se pudo consultar el examen'
              )
        )
      }
    } finally {
      if (!signal?.aborted) setLoading(false)
    }
  }, [examId, id])

  useEffect(() => {
    const controller = new AbortController()
    void load(controller.signal)
    return () => controller.abort()
  }, [load])

  const handleCancelExam = async () => {
    if (!exam || exam.estado !== 'PROGRAMADO') return

    setCancelling(true)
    setCancelError(null)

    try {
      const updatedExam = await examsService.cancelExam(exam.id_examen)
      setExam(updatedExam)
      setShowCancelDialog(false)
    } catch (error: unknown) {
      setCancelError(
        error instanceof Error
          ? error.message
          : 'No se pudo cancelar el examen'
      )
    } finally {
      setCancelling(false)
    }
  }

  const isProgramado = exam?.estado === 'PROGRAMADO'
  const groups = exam?.grupos ?? []
  const classrooms = exam?.ambientes ?? []

  const totalCapacity = classrooms.reduce(
    (total, room) => total + Number(room.capacidad),
    0
  )

  const totalStudents = groups.reduce(
    (total, group) => total + Number(group.cantidad_estudiantes),
    0
  )

  if (notFound || forbidden) {
    return (
      <AppShell
        mobileTitle="Examen"
        breadcrumbs={[
          { label: 'Exámenes' },
          { label: 'Programados', to: backTo },
          { label: 'Detalle' },
        ]}
      >
        <EmptyState
          icon={ShieldAlert}
          title={
            notFound
              ? 'Examen no encontrado'
              : 'No tiene acceso a este examen'
          }
          description={
            notFound
              ? 'El examen no existe o ya no está disponible.'
              : 'Solo el docente autorizado puede consultar este examen.'
          }
          action={
            <Button variant="outline" onClick={() => navigate(backTo)}>
              Volver a Programados
            </Button>
          }
        />
      </AppShell>
    )
  }

  return (
    <AppShell
      mobileTitle={exam?.nombre_examen ?? 'Examen'}
      breadcrumbs={[
        { label: 'Exámenes' },
        { label: 'Programados', to: backTo },
        { label: exam?.nombre_examen ?? 'Detalle' },
      ]}
    >
      <PageHeader
        title={exam?.nombre_examen ?? 'Detalle del examen'}
        backTo={backTo}
        backLabel="Volver a Programados"
        subtitle={exam && <ExamStatusBadge status={exam.estado} />}
        actions={
          exam && isProgramado ? (
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate(`/examenes/${id}/editar`)}
              >
                <Pencil className="size-4" aria-hidden="true" />
                Editar examen
              </Button>

              <Button
                type="button"
                variant="outline"
                onClick={() => navigate('/mis-auxiliares')}
              >
                <Users className="size-4" aria-hidden="true" />
                Habilitar auxiliares
              </Button>

              <Button
                type="button"
                variant="outline"
                onClick={() => setShowEnvironmentInfo(true)}
              >
                <Building2 className="size-4" aria-hidden="true" />
                Asignar ambientes
              </Button>

              <Button
                type="button"
                variant="outline"
                className="border-danger/30 text-danger hover:bg-danger-soft"
                onClick={() => setShowCancelDialog(true)}
              >
                <Ban className="size-4" aria-hidden="true" />
                Cancelar examen
              </Button>
            </div>
          ) : exam?.estado === 'EN_INGRESO' ? (
            <Button
              type="button"
              onClick={() =>
                navigate(`/examenes/${id}/control-ingreso`)
              }
            >
              <DoorOpen className="size-4" aria-hidden="true" />
              Controlar ingreso
            </Button>
          ) : null
        }
      />

      {loading && (
        <LoadingState rows={5} label="Cargando el examen" />
      )}

      {!loading && loadError && (
        <ErrorState error={loadError} onRetry={() => void load()} />
      )}

      {!loading && !loadError && exam && (
        <div className="mx-auto max-w-5xl space-y-6">
          {exam.estado !== 'PROGRAMADO' && (
            <div
              role="status"
              className="rounded-xl border border-border-soft bg-surface p-4 text-sm text-muted-foreground"
            >
              {examLockedMessage(exam.estado)}
            </div>
          )}

          <section className="space-y-4 rounded-xl border border-border-soft bg-surface p-6">
            <h2 className="sciem-h3">Información general</h2>

            <dl className="grid grid-cols-1 gap-x-8 gap-y-4 text-sm md:grid-cols-2">
              <div>
                <dt className="font-medium text-foreground">Materia</dt>
                <dd className="text-muted-foreground">
                  {exam.materia?.nombre ?? 'No disponible'}
                </dd>
              </div>

              <div>
                <dt className="font-medium text-foreground">Carrera</dt>
                <dd className="text-muted-foreground">
                  {exam.carrera?.nombre ?? 'No disponible'}
                </dd>
              </div>

              <div>
                <dt className="font-medium text-foreground">Tipo de examen</dt>
                <dd className="text-muted-foreground">
                  {exam.tipo_examen?.nombre ?? 'No disponible'}
                </dd>
              </div>

              <div>
                <dt className="font-medium text-foreground">Fecha</dt>
                <dd className="text-muted-foreground">{exam.fecha}</dd>
              </div>

              <div>
                <dt className="font-medium text-foreground">Horario</dt>
                <dd className="text-muted-foreground">
                  {exam.hora_inicio} – {exam.hora_fin}
                </dd>
              </div>

              <div>
                <dt className="font-medium text-foreground">Duración</dt>
                <dd className="text-muted-foreground">
                  {exam.duracion} minutos
                </dd>
              </div>
            </dl>

            {exam.normas && (
              <div className="border-t border-border-soft pt-4 text-sm">
                <h3 className="font-medium text-foreground">
                  Normas del examen
                </h3>
                <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                  {exam.normas}
                </p>
              </div>
            )}
          </section>

          <section className="space-y-4 rounded-xl border border-border-soft bg-surface p-6">
            <h2 className="sciem-h3">Grupos participantes</h2>

            {groups.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No hay grupos asignados a este examen.
              </p>
            ) : (
              <div className="divide-y divide-border-soft overflow-hidden rounded-lg border border-border-soft">
                {groups.map((group) => (
                  <div
                    key={group.id_grupo}
                    className="flex flex-wrap items-center justify-between gap-2 p-4 text-sm"
                  >
                    <span className="font-medium">
                      Grupo {group.num_grupo}
                    </span>
                    <span className="text-muted-foreground">
                      {group.cantidad_estudiantes} estudiantes
                    </span>
                  </div>
                ))}
              </div>
            )}
          </section>

          <section className="space-y-4 rounded-xl border border-border-soft bg-surface p-6">
            <h2 className="sciem-h3">Ambientes del examen</h2>

            {classrooms.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No hay ambientes asignados.
              </p>
            ) : (
              <div className="divide-y divide-border-soft overflow-hidden rounded-lg border border-border-soft">
                {classrooms.map((room) => (
                  <div
                    key={room.id_ambiente}
                    className="flex flex-wrap items-center justify-between gap-2 p-4 text-sm"
                  >
                    <span className="font-medium">
                      {room.nro_aula}
                    </span>
                    <span className="text-muted-foreground">
                      Capacidad: {room.capacidad}
                    </span>
                  </div>
                ))}
              </div>
            )}

            <div className="grid grid-cols-1 gap-3 border-t border-border-soft pt-4 text-sm sm:grid-cols-2">
              <p>
                <span className="font-medium">Capacidad total: </span>
                {totalCapacity}
              </p>
              <p>
                <span className="font-medium">Estudiantes: </span>
                {totalStudents}
              </p>
            </div>
          </section>

          <section className="space-y-3 rounded-xl border border-border-soft bg-surface p-6">
            <h2 className="sciem-h3">
              Personal del control de ingreso
            </h2>
            <p className="text-sm text-muted-foreground">
              La habilitación de auxiliares y la asignación de sus
              ambientes se gestionan por separado.
            </p>
            <p className="text-xs text-muted-foreground">
              Las acciones correspondientes están disponibles
              en el encabezado mientras el examen esté Programado.
            </p>
          </section>
        </div>
      )}

      {exam && (
        <Dialog
          open={showCancelDialog}
          onOpenChange={setShowCancelDialog}
        >
          <DialogContent>
            <DialogHeader>
              <DialogTitle>
                Cancelar «{exam.nombre_examen}»
              </DialogTitle>
              <DialogDescription>
                El examen quedará cancelado y no se eliminará
                del registro.
              </DialogDescription>
            </DialogHeader>

            {cancelError && (
              <p role="alert" className="text-sm text-danger">
                {cancelError}
              </p>
            )}

            <DialogFooter>
              <Button
                variant="outline"
                disabled={cancelling}
                onClick={() => setShowCancelDialog(false)}
              >
                Volver
              </Button>
              <Button
                variant="destructive"
                disabled={cancelling}
                onClick={() => void handleCancelExam()}
              >
                {cancelling
                  ? 'Cancelando...'
                  : 'Sí, cancelar examen'}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      )}

      <Dialog
        open={showEnvironmentInfo}
        onOpenChange={setShowEnvironmentInfo}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Asignar ambientes</DialogTitle>
            <DialogDescription>
              Esta funcionalidad se incorporará mediante
              HU-030, con su modal independiente.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button onClick={() => setShowEnvironmentInfo(false)}>
              Entendido
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppShell>
  )
}
