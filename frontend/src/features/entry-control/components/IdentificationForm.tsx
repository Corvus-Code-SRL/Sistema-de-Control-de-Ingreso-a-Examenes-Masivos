import type { FormEvent } from 'react'
import { ArrowRight, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { StudentSearch } from './StudentSearch'
import { IdentifierSuggestions } from './IdentifierSuggestions'
import type { StudentMatch, StudentSearchSource } from '../types/entry-control.types'
import { CI_MAX_LENGTH, isValidCi, isValidSis, SIS_LENGTH } from '../utils/identificationValidation'

interface Props {
  examId: number
  sis: string
  ci: string
  disabled: boolean
  busy: boolean
  onSisChange: (value: string) => void
  onCiChange: (value: string) => void
  onSelect: (student: StudentMatch, field: 'sis' | 'ci') => void
  onVerify: () => void
  /** Sugerencias y búsqueda por nombre; dependen del control abierto, así que el ingreso con retraso las apaga. */
  suggestions?: boolean
  /** Fuente de las sugerencias; por defecto, la del control de ingreso (HU-032). */
  searchSource?: StudentSearchSource
}

export function IdentificationForm(props: Props) {
  const suggestions = props.suggestions ?? true
  function submit(event: FormEvent) { event.preventDefault(); props.onVerify() }
  function select(student: StudentMatch, field: 'sis' | 'ci') { props.onSelect(student, field) }

  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm sm:p-6" aria-labelledby="verify-heading">
      <div className="mb-5">
        <h2 id="verify-heading" className="sciem-h2">Verificar información de estudiante</h2>
        <p className="mt-1 text-sm text-muted-foreground">Ingrese el código SIS o el CI para verificar al estudiante.</p>
      </div>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="entry-sis">Código SIS</Label>
            <Input id="entry-sis" inputMode="numeric" autoComplete="off" value={props.sis} onChange={(event) => props.onSisChange(event.target.value)} placeholder="Ej: 202301903" className="h-12 sciem-tnum text-base" maxLength={SIS_LENGTH} disabled={props.disabled} />
            {suggestions && <IdentifierSuggestions examId={props.examId} field="sis" query={props.sis} disabled={props.disabled} onSelect={select} searchSource={props.searchSource} />}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="entry-ci">Carnet de identidad (CI)</Label>
            <Input id="entry-ci" inputMode="numeric" autoComplete="off" value={props.ci} onChange={(event) => props.onCiChange(event.target.value)} placeholder="Ej: 9342145" className="h-12 sciem-tnum text-base" maxLength={CI_MAX_LENGTH} disabled={props.disabled} />
            {suggestions && <IdentifierSuggestions examId={props.examId} field="ci" query={props.ci} disabled={props.disabled} onSelect={select} searchSource={props.searchSource} />}
          </div>
        </div>
        {suggestions && <StudentSearch examId={props.examId} onSelect={(student) => select(student, 'sis')} disabled={props.disabled} searchSource={props.searchSource} />}
        <Button type="submit" className="h-12 w-full text-sm" disabled={props.disabled || props.busy || (props.sis === '' && props.ci === '') || (props.sis !== '' && !isValidSis(props.sis)) || !isValidCi(props.ci)}>
          {props.busy ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : <ArrowRight className="size-4" aria-hidden="true" />}
          {props.busy ? 'Verificando…' : 'Verificar estudiante'}
        </Button>
      </form>
    </section>
  )
}
