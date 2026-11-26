import { Ban } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { DISTRIBUTION_MESSAGES as messages } from '../utils/roomDistributionMessages'

interface Props { busy: boolean; onStop: () => void }

/** Con el control abierto la distribución ya no se edita: lo único posible es dejar de usarla. */
export function DistributionStopNotice({ busy, onStop }: Props) {
  return (
    <Alert className="border-warn-border bg-warn-soft text-warn-fg">
      <Ban aria-hidden="true" />
      <AlertTitle>{messages.stopTitle}</AlertTitle>
      <AlertDescription className="space-y-2">
        <p>{messages.stopDescription}</p>
        <Button variant="outline" className="min-h-11 w-full sm:w-auto" disabled={busy} onClick={onStop}>
          {messages.stopAction}
        </Button>
      </AlertDescription>
    </Alert>
  )
}
