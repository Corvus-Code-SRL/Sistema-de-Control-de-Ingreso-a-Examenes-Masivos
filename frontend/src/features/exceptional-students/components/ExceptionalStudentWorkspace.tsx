import { useEffect, useState, type FormEvent } from 'react'
import { Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useExceptionalStudentFlow } from '../hooks/useExceptionalStudentFlow'
import { exceptionalStudentService } from '../services/exceptionalStudentService'
import type {
  ExceptionalExam,
  ExceptionalOptions,
  RegisterExceptionalInput
} from '../types/exceptional-student.types'
import { ExceptionalRegistrationForm } from './ExceptionalRegistrationForm'
import { EXCEPTIONAL_MESSAGES, SIS_PATTERN } from './exceptionalStudentMessages'

interface ExceptionalStudentWorkspaceProps {
  exam: ExceptionalExam
  /** El servidor informó que el examen cambió de estado: la vista recarga sus exámenes. */
  onExamChanged: (message: string) => void
}

/**
 * Búsqueda por código SIS y registro del estudiante en el examen elegido. Se monta con `key` del
 * examen: al cambiar de examen se empieza de cero.
 */
export function ExceptionalStudentWorkspace({ exam, onExamChanged }: ExceptionalStudentWorkspaceProps) {
  const flow = useExceptionalStudentFlow(exam, onExamChanged)
  const [options, setOptions] = useState<ExceptionalOptions | null>(null)
  const [optionsError, setOptionsError] = useState('')
  const [code, setCode] = useState('')
  const [codeError, setCodeError] = useState('')
  const searching = flow.search.status === 'searching'

  useEffect(() => {
    const controller = new AbortController()

    exceptionalStudentService
      .options(exam.id_examen, controller.signal)
      .then(({ data }) => {
        if (!controller.signal.aborted) setOptions(data)
      })
      .catch(() => {
        if (!controller.signal.aborted) setOptionsError(EXCEPTIONAL_MESSAGES.optionsFailed)
      })

    return () => controller.abort()
  }, [exam.id_examen])

  function handleSearch(event: FormEvent) {
    event.preventDefault()

    const trimmed = code.trim()

    if (trimmed === '') {
      setCodeError(EXCEPTIONAL_MESSAGES.sisRequired)
      return
    }

    if (!SIS_PATTERN.test(trimmed)) {
      setCodeError(EXCEPTIONAL_MESSAGES.sisInvalid)
      return
    }

    setCodeError('')
    void flow.find(trimmed)
  }

  async function handleRegister(input: RegisterExceptionalInput) {
    const done = await flow.register(input)

    if (done) setCode('')

    return done
  }

  function handleCancel() {
    flow.cancel()
    setCode('')
  }

  return (
    <div className="space-y-4">
      {flow.success && (
        <p role="status" className="text-sm font-medium text-brand-fg">
          {flow.success}
        </p>
      )}

      <Card className="space-y-3 p-4">
        <form onSubmit={handleSearch} noValidate className="space-y-2">
          <Label htmlFor="exceptional-sis">Código SIS</Label>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              id="exceptional-sis"
              value={code}
              maxLength={9}
              inputMode="numeric"
              onChange={(event) => setCode(event.target.value)}
              aria-invalid={codeError !== ''}
              className="h-11 sm:w-72"
            />
            <Button type="submit" disabled={searching}>
              <Search aria-hidden="true" className="size-4" />
              {searching ? 'Buscando…' : 'Buscar'}
            </Button>
          </div>
          {codeError && (
            <p role="alert" className="text-sm text-destructive">
              {codeError}
            </p>
          )}
        </form>

        {flow.search.status === 'notFound' && (
          <p role="alert" className="text-sm text-destructive">
            {EXCEPTIONAL_MESSAGES.notFound}
          </p>
        )}

        {flow.search.status === 'error' && (
          <p role="alert" className="text-sm text-destructive">
            {flow.search.message}
          </p>
        )}
      </Card>

      {flow.search.status === 'found' && (
        <Card className="space-y-4 p-4">
          <h2 className="sciem-h2">Estudiante encontrado</h2>
          <dl className="grid gap-2 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-muted-foreground">Nombre completo</dt>
              <dd className="font-medium">{flow.search.candidate.nombre_completo}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Código SIS</dt>
              <dd className="sciem-tnum">{flow.search.candidate.cod_sis}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Carrera según los registros de SCIEM</dt>
              <dd>{flow.search.candidate.carrera?.nombre ?? EXCEPTIONAL_MESSAGES.noCareer}</dd>
            </div>
          </dl>

          {flow.search.candidate.ya_en_examen ? (
            <div className="space-y-3">
              <p role="alert" className="text-sm text-destructive">
                {EXCEPTIONAL_MESSAGES.alreadyInExam}
              </p>
              <Button type="button" variant="outline" onClick={handleCancel}>
                Buscar otro código
              </Button>
            </div>
          ) : (
            <ExceptionalRegistrationForm
              candidate={flow.search.candidate}
              options={options}
              optionsError={optionsError}
              registering={flow.registering}
              error={flow.formError}
              onSubmit={handleRegister}
              onCancel={handleCancel}
            />
          )}
        </Card>
      )}
    </div>
  )
}
