import { useState, type FormEvent } from 'react'
import { Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useExceptionalStudentFlow } from '../hooks/useExceptionalStudentFlow'
import type { ExceptionalExam, RegisterExceptionalInput } from '../types/exceptional-student.types'
import { ExceptionalRegistrationForm } from './ExceptionalRegistrationForm'
import { EXCEPTIONAL_MESSAGES } from './exceptionalStudentMessages'

/**
 * Búsqueda por código SIS y registro del estudiante en el examen elegido. Se monta con `key` del
 * examen: al cambiar de examen se empieza de cero.
 */
export function ExceptionalStudentWorkspace({ exam }: { exam: ExceptionalExam }) {
  const flow = useExceptionalStudentFlow(exam)
  const [code, setCode] = useState('')
  const [codeError, setCodeError] = useState('')
  const blocked = !exam.permitido
  const searching = flow.search.status === 'searching'

  function handleSearch(event: FormEvent) {
    event.preventDefault()

    if (code.trim() === '') {
      setCodeError(EXCEPTIONAL_MESSAGES.sisRequired)
      return
    }

    setCodeError('')
    void flow.find(code)
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
      {blocked && (
        <p role="status" className="rounded-lg border p-3 text-sm text-warn-fg">
          {EXCEPTIONAL_MESSAGES.notAllowed}
        </p>
      )}

      {flow.success && (
        <p role="status" className="text-sm font-medium text-brand">
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
              maxLength={30}
              disabled={blocked}
              onChange={(event) => setCode(event.target.value)}
              aria-invalid={codeError !== ''}
              className="h-11 sm:w-72"
            />
            <Button type="submit" disabled={blocked || searching}>
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
