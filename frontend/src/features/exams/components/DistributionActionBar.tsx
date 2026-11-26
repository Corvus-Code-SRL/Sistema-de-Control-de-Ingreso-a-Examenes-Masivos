import { Save, Shuffle, Undo2 } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface Props {
  busy: boolean
  proposing: boolean
  saving: boolean
  canPropose: boolean
  canSave: boolean
  isDirty: boolean
  onPropose: () => void
  onSave: () => void
  onDiscard: () => void
}

export function DistributionActionBar(props: Props) {
  return (
    <div className="fixed inset-x-0 bottom-0 z-20 grid grid-cols-2 gap-2 border-t border-border bg-background p-3 shadow-sm sm:static sm:flex sm:flex-wrap sm:justify-end sm:rounded-xl sm:border sm:shadow-none"
      role="group" aria-label="Acciones de distribución">
      <Button variant="outline" className="min-h-11 whitespace-normal text-xs sm:text-sm"
        disabled={!props.canPropose || props.busy} onClick={props.onPropose}>
        <Shuffle aria-hidden="true" />{props.proposing ? 'Distribuyendo…' : 'Distribuir por apellido'}
      </Button>
      <Button className="min-h-11 whitespace-normal text-xs sm:order-last sm:text-sm"
        disabled={!props.canSave || props.busy} onClick={props.onSave}>
        <Save aria-hidden="true" />{props.saving ? 'Guardando…' : 'Guardar distribución'}
      </Button>
      {props.isDirty && <Button variant="ghost" className="col-span-2 min-h-11 sm:col-span-1"
        disabled={props.busy} onClick={props.onDiscard}><Undo2 aria-hidden="true" />Descartar cambios</Button>}
    </div>
  )
}
