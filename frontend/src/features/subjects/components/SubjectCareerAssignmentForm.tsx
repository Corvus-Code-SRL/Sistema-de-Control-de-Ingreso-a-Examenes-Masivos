import { useState, type FormEvent } from 'react'
import {
  AlertCircle,
  CheckCircle2,
  Link2,
  Loader2,
} from 'lucide-react'
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
import type { SubjectCareerAssignment } from '../types/subject.types'

interface SubjectCareerAssignmentFormProps {
  /** Se llama tras una asignación confirmada con éxito, para que la vista refresque su lista. */
  onAssigned?: (assignment: SubjectCareerAssignment) => void
}

/**
 * Formulario administrativo para vincular una materia existente
 * del catálogo institucional con una carrera activa.
 */
export function SubjectCareerAssignmentForm({ onAssigned }: SubjectCareerAssignmentFormProps) {
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
  } = useAssignableSubjects(careerId)

  const assignment = useSubjectCareerAssignment()

  const selectedCareer =
    careers.find((career) => career.id_carrera === careerId) ?? null

  const selectedSubject =
    subjects.find((subject) => subject.id_materia === subjectId) ?? null

  const isSubmitting = assignment.status === 'submitting'

  const assignmentError =
    assignment.error?.errors.id_materia?.[0] ??
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
    })

    if (!created) {
      return
    }

    setSuccessMessage(
      `${created.materia.nombre} fue asignada a ${created.carrera.nombre}.`
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

  return (
    <>
      <form onSubmit={handleSubmit}>
        <Card>
          <CardHeader>
            <CardTitle>Asignar materia a una carrera</CardTitle>

            <CardDescription>
              Selecciona una carrera y una materia existente del catálogo
              institucional para crear la asignación.
            </CardDescription>
          </CardHeader>

          <CardContent className="space-y-5">
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

                <Select
                  value={subjectId === null ? '' : String(subjectId)}
                  onValueChange={handleSubjectChange}
                  disabled={
                    careerId === null ||
                    subjectsStatus === 'loading' ||
                    subjectsStatus === 'error' ||
                    subjects.length === 0
                  }
                >
                  <SelectTrigger
                    id="subject-select"
                    className="w-full"
                    aria-label="Materia"
                    aria-invalid={
                      assignment.error?.errors.id_materia
                        ? true
                        : undefined
                    }
                  >
                    <SelectValue
                      placeholder={
                        careerId === null
                          ? 'Selecciona primero una carrera'
                          : subjectsStatus === 'loading'
                            ? 'Cargando materias...'
                            : 'Selecciona una materia'
                      }
                    />
                  </SelectTrigger>

                  <SelectContent>
                    {subjects.map((subject) => (
                      <SelectItem
                        key={subject.id_materia}
                        value={String(subject.id_materia)}
                      >
                        {subject.codigo} · {subject.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                {careerId !== null &&
                  subjectsStatus === 'success' &&
                  subjects.length === 0 && (
                    <p className="text-xs text-muted-foreground">
                      No hay materias activas pendientes de asignación para
                      esta carrera.
                    </p>
                  )}
              </div>
            </div>

            {subjectsStatus === 'error' && subjectsError && (
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
              Verifica la carrera y la materia antes de continuar.
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