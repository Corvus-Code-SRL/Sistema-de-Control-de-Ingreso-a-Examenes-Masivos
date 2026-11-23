import { Button } from '@/components/ui/button'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import type { DistributionConfirmation } from '../types/roomDistribution.types'
import { DISTRIBUTION_CONFIRMATIONS } from '../utils/roomDistributionMessages'

interface Props { kind: DistributionConfirmation | null; busy: boolean; onCancel: () => void; onConfirm: () => void }

export function DistributionConfirmDialog({ kind, busy, onCancel, onConfirm }: Props) {
  const content = DISTRIBUTION_CONFIRMATIONS[kind ?? 'leave']
  return (
    <Dialog open={kind !== null} onOpenChange={(open) => { if (!open && !busy) onCancel() }}>
      <DialogContent showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>{content.title}</DialogTitle>
          <DialogDescription>{content.description}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" className="min-h-11" disabled={busy} onClick={onCancel}>{'cancel' in content ? content.cancel : 'Seguir editando'}</Button>
          <Button className="min-h-11" disabled={busy} onClick={onConfirm}>{content.confirm}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
