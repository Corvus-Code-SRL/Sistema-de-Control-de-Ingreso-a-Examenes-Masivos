import type { FormEvent } from 'react'
import { FormSelect } from '@/components/common/FormSelect'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { SituationRoom } from '../types/exam-situation.types'

interface Props {
  /** Prefijo de los ids (`situation`, `incident`) para no repetirlos entre accesos. */
  idPrefix: string
  rooms: SituationRoom[]
  controlRoom: number | null
  effectiveRoom: number | undefined
  draft: string
  onDraft: (value: string) => void
  onRoom: (value: number | undefined) => void
  onSearch: (value: string) => void
}

/** Filtro de ambiente y búsqueda por nombre, código SIS o CI de los accesos independientes. */
export function ExamStudentFilters({
  idPrefix, rooms, controlRoom, effectiveRoom, draft, onDraft, onRoom, onSearch,
}: Props) {
  function submit(event: FormEvent) {
    event.preventDefault()
    onSearch(draft.trim())
  }

  return <div className="grid gap-4 sm:grid-cols-2">
    <div className="space-y-2">
      <Label htmlFor={`${idPrefix}-room-filter`}>Ambiente</Label>
      <FormSelect id={`${idPrefix}-room-filter`} value={String(effectiveRoom ?? 'all')}
        placeholder="Todos los ambientes" disabled={controlRoom !== null}
        onValueChange={(value) => onRoom(value === 'all' ? undefined : Number(value))}
        options={[...(controlRoom === null ? [{ value: 'all', label: 'Todos los ambientes' }] : []),
          ...rooms.map((room) => ({ value: String(room.id_ambiente), label: room.nro_aula }))]} />
    </div>
    <form className="space-y-2" onSubmit={submit}>
      <Label htmlFor={`${idPrefix}-student-search`}>Nombre, código SIS o CI</Label>
      <div className="flex gap-2">
        <Input id={`${idPrefix}-student-search`} value={draft} maxLength={50}
          onChange={(event) => onDraft(event.target.value)} />
        <Button type="submit" variant="outline">Buscar</Button>
      </div>
    </form>
  </div>
}
