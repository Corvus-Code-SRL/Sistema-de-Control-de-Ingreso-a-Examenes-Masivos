import { useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  AlertCircle,
  CheckCircle2,
  Info,
  Link2,
  Loader2,
} from 'lucide-react'
import { SearchableSelect } from '@/components/common/SearchableSelect'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useAdminCareers } from '../hooks/useAdminCareers'
import { useAssignableSubjects } from '../hooks/useAssignableSubjects'
import { useSubjectCareerAssignment } from '../hooks/useSubjectCareerAssignment'
import type { AcademicPeriod, SubjectCareerAssignment } from '../types/subject.types'

interface SubjectCareerAssignmentFormProps {
  /** Período sobre el que se asigna: lo elige la vista, el formulario no lo cambia. */
  period: AcademicPeriod
  /**
   * Si no es `null`, el período es de solo consulta: el formulario se muestra deshabilitado con este
   * aviso y no consulta materias asignables. Solo el período vigente admite escritura.
   */
  readOnlyNotice?: string | null
  /** Se llama tras una asignación confirmada con éxito, para que la vista refresque su lista. */
  onAssigned?: (assignment: SubjectCareerAssignment) => void
}

/**
 * Formulario administrativo (HU-006) para ofrecer una materia existente del catálogo
 * institucional a una carrera activa en un período.
 *
 * Nada se guarda hasta confirmar en el diálogo. Al cambiar de período se descartan la materia
 * elegida y el mensaje de éxito (las opciones dependen del período); la carrera se conserva.
 */
export function SubjectCareerAssignmentForm({
  period,
  readOnlyNotice = null,
  onAssigned,
}: SubjectCareerAssignmentFormProps) {
  const readOnly = readOnlyNotice !== null
  const [careerId, setCareerId] = useState<number | null>(null)
  const [subjectId, setSubjectId] = useState<number | null>(null)
  const [confirmationOpen, setConfirmationOpen] = useState(false)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  const {
    careers,
    status: careersStatus,
    error: careersError,
    reload: reloadCareers,
  } = useAdminCareers()

  const {
    subjects,
    status: subjectsStatus,
    error: subjectsError,
    reload: reloadSubjects,
  } = useAssignableSubjects(careerId, readOnly ? null : period.id_periodo)

  const assignment = useSubjectCareerAssignment()
  const resetAssignment = assignment.reset

  useEffect(() => {
    setSubjectId(null)
    setSuccessMessage(null)
    setConfirmationOpen(false)
    resetAssignment()
  }, [period.id_periodo, resetAssignment])

  const selectedCareer =
    careers.find((career) => career.id_carrera === careerId) ?? null

  const selectedSubject =
    subjects.find((subject) => subject.id_materia === subjectId) ?? null

  const subjectOptions = useMemo(
    () =>
      subjects.map((subject) => ({
        value: String(subject.id_materia),
        label: `${subject.codigo} · ${subject.nombre}`,
      })),
    [subjects]
  )

  const isSubmitting = assignment.status === 'submitting'

  const assignmentError =
    assignment.error?.errors.id_materia?.[0] ??
    assignment.error?.errors.id_periodo?.[0] ??
    assignment.error?.message ??
    null

  const handleCareerChange = (value: string) => {
    setCareerId(Number(value))
    setSubjectId(null)
    setSuccessMessage(null)
    setConfirmationOpen(false)
    assignment.reset()
  }

  const handleSubjectChange = (value: string) => {
    setSubjectId(Number(value))
    setSuccessMessage(null)
    assignment.reset()
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (!selectedCareer || !selectedSubject) {
      return
    }

    assignment.reset()
    setConfirmationOpen(true)
  }

  const handleConfirm = async () => {
    if (careerId === null || subjectId === null) {
      return
    }

    const created = await assignment.submit(careerId, {
      id_materia: subjectId,
      id_periodo: period.id_periodo,
    })

    if (!created) {
      return
    }

    setSuccessMessage(
      `Materia asignada: ${created.materia.nombre} → ${created.carrera.nombre} (${created.periodo.etiqueta})`
    )

    setSubjectId(null)
    setConfirmationOpen(false)

    assignment.reset()
    reloadSubjects()
    onAssigned?.(created)
  }

  const handleDialogChange = (open: boolean) => {
    if (isSubmitting) {
      return
    }

    setConfirmationOpen(open)

    if (!open) {
      assignment.reset()
    }
  }

  const subjectPlaceholder =
    careerId === null
      ? 'Selecciona primero una carrera'
      : subjectsStatus === 'loading'
        ? 'Cargando materias...'
        : 'Selecciona una materia'

  return (
    <>
      <form onSubmit={handleSubmit}>
        <Card>
          <CardHeader>
            <CardTitle>Asignar materia a una carrera</CardTitle>

            <CardDescription>
              Selecciona una carrera y una materia existente del catálogo institucional para
              ofrecerla en el período {period.etiqueta}.
            </CardDescription>
          </CardHeader>

          <CardContent className="space-y-5">
            {readOnly && (
              <div
                role="status"
                className="flex items-start gap-2 rounded-lg border border-border bg-muted/50 px-3 py-2.5 text-sm text-muted-foreground"
              >
                <Info className="mt-0.5 size-4 shrink-0" aria-hidden="true" />

                <span>{readOnlyNotice}</span>
              </div>
            )}

            {successMessage && (
              <div
                role="status"
                className="flex items-start gap-2 rounded-lg border border-border-strong bg-brand-soft px-3 py-2.5 text-sm text-brand-deep"
              >
                <CheckCircle2
                  className="mt-0.5 size-4 shrink-0"
                  aria-hidden="true"
                />

                <span>{successMessage}</span>
              </div>
            )}

            {careersStatus === 'error' && careersError && (
              <Alert className="border-danger-border bg-danger-soft text-danger-fg">
                <AlertCircle className="size-4" />

                <AlertDescription className="flex items-center justify-between gap-3 text-danger-fg">
                  <span>{careersError.message}</span>

                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={reloadCareers}
                  >
                    Reintentar
                  </Button>
                </AlertDescription>
              </Alert>
            )}

            <div className="grid gap-5 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="career-select">Carrera</Label>

                <Select
                  value={careerId === null ? '' : String(careerId)}
                  onValueChange={handleCareerChange}
                  disabled={
                    readOnly ||
                    careersStatus === 'loading' ||
                    careersStatus === 'error' ||
                    careers.length === 0
                  }
                >
                  <SelectTrigger
                    id="career-select"
                    className="w-full"
                    aria-label="Carrera"
                  >
                    <SelectValue
                      placeholder={
                        careersStatus === 'loading'
                          ? 'Cargando carreras...'
                          : 'Selecciona una carrera'
                      }
                    />
                  </SelectTrigger>

                  <SelectContent>
                    {careers.map((career) => (
                      <SelectItem
                        key={career.id_carrera}
                        value={String(career.id_carrera)}
                      >
                        {career.codigo} · {career.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                {careersStatus === 'success' && careers.length === 0 && (
                  <p className="text-xs text-muted-foreground">
                    No hay carreras activas disponibles.
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="subject-select">Materia</Label>

                <SearchableSelect
                  id="subject-select"
                  label="Materia"
                  value={subjectId === null ? null : String(subjectId)}
                  options={subjectOptions}
                  onValueChange={handleSubjectChange}
                  placeholder={subjectPlaceholder}
                  disabled={
                    readOnly ||
                    careerId === null ||
                    subjectsStatus === 'loading' ||
                    subjectsStatus === 'error'
                  }
                  invalid={Boolean(assignment.error?.errors.id_materia)}
                />

                {!readOnly &&
                  careerId !== null &&
                  subjectsStatus === 'success' &&
                  subjects.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                      No hay materias activas pendientes de asignación para esta carrera en{' '}
                      {period.etiqueta}.
                    </p>
                  )}
              </div>
            </div>

            {!readOnly && subjectsStatus === 'error' && subjectsError && (
              <Alert className="border-danger-border bg-danger-soft text-danger-fg">
                <AlertCircle className="size-4" />

                <AlertDescription className="flex items-center justify-between gap-3 text-danger-fg">
                  <span>{subjectsError.message}</span>

                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={reloadSubjects}
                  >
                    Reintentar
                  </Button>
                </AlertDescription>
              </Alert>
            )}
          </CardContent>

          <CardFooter className="justify-end">
            <Button
              type="submit"
              disabled={
                readOnly ||
                !selectedCareer ||
                !selectedSubject ||
                isSubmitting
              }
            >
              <Link2 className="size-4" aria-hidden="true" />
              Asignar materia
            </Button>
          </CardFooter>
        </Card>
      </form>

      <Dialog
        open={confirmationOpen}
        onOpenChange={handleDialogChange}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Confirmar asignación</DialogTitle>

            <DialogDescription>
              Verifica la carrera, la materia y el período antes de continuar.
            </DialogDescription>
          </DialogHeader>

          {selectedCareer && selectedSubject && (
            <div className="space-y-3 rounded-lg border border-border bg-muted/50 p-3">
              <div>
                <p className="text-xs font-medium text-muted-foreground">
                  Carrera
                </p>

                <p className="font-medium text-foreground">
                  {selectedCareer.codigo} · {selectedCareer.nombre}
                </p>
              </div>

              <div>
                <p className="text-xs font-medium text-muted-foreground">
                  Materia
                </p>

                <p className="font-medium text-foreground">
                  {selectedSubject.codigo} · {selectedSubject.nombre}
                </p>
              </div>

              <div>
                <p className="text-xs font-medium text-muted-foreground">
                  Período
                </p>

                <p className="font-medium text-foreground">
                  {period.etiqueta}
                </p>
              </div>
            </div>
          )}

          {assignment.status === 'error' && assignmentError && (
            <Alert className="border-danger-border bg-danger-soft text-danger-fg">
              <AlertCircle className="size-4" />

              <AlertDescription className="text-danger-fg">
                {assignmentError}
              </AlertDescription>
            </Alert>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              disabled={isSubmitting}
              onClick={() => handleDialogChange(false)}
            >
              Cancelar
            </Button>

            <Button
              type="button"
              disabled={isSubmitting}
              onClick={handleConfirm}
            >
              {isSubmitting ? (
                <>
                  <Loader2
                    className="size-4 animate-spin"
                    aria-hidden="true"
                  />
                  Asignando...
                </>
              ) : (
                <>
                  <Link2 className="size-4" aria-hidden="true" />
                  Confirmar asignación
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
