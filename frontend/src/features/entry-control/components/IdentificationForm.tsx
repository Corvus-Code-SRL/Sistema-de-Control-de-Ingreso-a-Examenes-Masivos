import { useState, type FormEvent } from 'react'
import { ArrowRight, Loader2, Search } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { StudentSearch } from './StudentSearch'
import type { StudentMatch } from '../types/entry-control.types'

interface Props {
  examId: number
  sis: string
  ci: string
  disabled: boolean
  busy: boolean
  onSisChange: (value: string) => void
  onCiChange: (value: string) => void
  onSelect: (student: StudentMatch) => void
  onVerify: () => void
}

export function IdentificationForm(props: Props) {
  const [showSearch, setShowSearch] = useState(false)
  function submit(event: FormEvent) { event.preventDefault(); props.onVerify() }
  function select(student: StudentMatch) { props.onSelect(student); setShowSearch(false) }

  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm sm:p-6" aria-labelledby="verify-heading">
      <div className="mb-5">
        <h2 id="verify-heading" className="sciem-h2">Verificar estudiante</h2>
        <p className="mt-1 text-sm text-muted-foreground">Ingrese el código SIS para verificar.</p>
      </div>
      <form onSubmit={submit} className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="entry-sis">Código SIS</Label>
            <Input id="entry-sis" inputMode="numeric" autoComplete="off" value={props.sis} onChange={(event) => props.onSisChange(event.target.value)} placeholder="Código SIS" className="h-12 sciem-tnum text-base" maxLength={15} disabled={props.disabled} required />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="entry-ci">Carnet de identidad (opcional)</Label>
            <Input id="entry-ci" inputMode="numeric" autoComplete="off" value={props.ci} onChange={(event) => props.onCiChange(event.target.value)} placeholder="Si está disponible" className="h-12 sciem-tnum text-base" maxLength={10} disabled={props.disabled} />
          </div>
        </div>
        <Button type="submit" className="h-12 w-full text-sm" disabled={props.disabled || props.busy || !props.sis}>
          {props.busy ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : <ArrowRight className="size-4" aria-hidden="true" />}
          {props.busy ? 'Verificando…' : 'Verificar estudiante'}
        </Button>
      </form>
      <Button type="button" variant="ghost" className="mt-3 w-full text-brand-deep" onClick={() => setShowSearch((value) => !value)} disabled={props.disabled} aria-expanded={showSearch}>
        <Search className="size-4" aria-hidden="true" /> Buscar por nombre
      </Button>
      {showSearch && <div className="mt-3"><StudentSearch examId={props.examId} onSelect={select} /></div>}
    </section>
  )
}
