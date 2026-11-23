import type { ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'

interface EntryConfirmDialogProps {
  open: boolean
  title: string
  description: ReactNode
  confirmLabel: string
  cancelLabel: string
  busy: boolean
  onConfirm: () => void
  onOpenChange: (open: boolean) => void
}

/** Confirmación de «registrar de todos modos» del control de ingreso y del ingreso con retraso. */
export function EntryConfirmDialog({
  open, title, description, confirmLabel, cancelLabel, busy, onConfirm, onOpenChange,
}: EntryConfirmDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription asChild><div>{description}</div></DialogDescription>
        </DialogHeader>
        <DialogFooter className="flex-col gap-2 sm:flex-row">
          <Button type="button" variant="outline" className="h-11 w-full sm:w-auto" disabled={busy}
            onClick={() => onOpenChange(false)}>
            {cancelLabel}
          </Button>
          <Button type="button" className="h-11 w-full sm:w-auto" disabled={busy} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
