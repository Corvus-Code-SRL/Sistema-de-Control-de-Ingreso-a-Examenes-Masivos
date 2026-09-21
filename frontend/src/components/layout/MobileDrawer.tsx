import { Dialog } from 'radix-ui'
import type { NavItem } from '@/config/navigation'
import type { NavStatus } from '@/config/navStatus'
import Sidebar from './Sidebar'

interface MobileDrawerProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  items: NavItem[]
  status: NavStatus
}

export default function MobileDrawer({ open, onOpenChange, items, status }: MobileDrawerProps) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
        {/* El fondo va acá y no solo en Sidebar: esta es la caja que se posiciona
            y se anima, y al promoverse a su propia capa de composición un panel
            transparente deja pasar el overlay por el borde. */}
        <Dialog.Content
          aria-describedby={undefined}
          className="fixed inset-y-0 left-0 z-50 w-[300px] max-w-[85vw] bg-sidebar outline-none data-[state=open]:animate-in data-[state=open]:slide-in-from-left"
        >
          <Dialog.Title className="sr-only">Menú de navegación</Dialog.Title>
          <Sidebar items={items} status={status} collapsed={false} onClose={() => onOpenChange(false)} />
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  )
}
