import { CheckCircle, KeyRound } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { roleLabel } from '@/lib/roleLabel'
import type { UserAccount } from '../types/users.types'

interface RegistrationSummaryProps {
  account: UserAccount
  onRegisterAnother: () => void
}

const REMINDER = 'Entregue la contraseña temporal a la persona: deberá cambiarla en su primer ingreso.'

/** Resultado del registro (no es un paso): datos de la cuenta creada y el recordatorio de la contraseña. */
export function RegistrationSummary({ account, onRegisterAnother }: RegistrationSummaryProps) {
  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center gap-3">
        <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-ok-soft text-ok">
          <CheckCircle aria-hidden="true" className="size-6" />
        </span>
        <h2 className="sciem-h2">Cuenta creada</h2>
      </div>

      <dl className="grid gap-3 rounded-lg border border-border-soft bg-sunken p-4 text-sm sm:grid-cols-2">
        <Item label="Nombre" value={account.nombre_completo} />
        <Item label={account.es_invitado ? 'Código INV' : 'Código SIS'} value={account.cod_sis} mono />
        <Item label="Correo" value={account.correo} />
        <Item label="Rol" value={account.rol ? roleLabel(account.rol.nombre_rol) : 'Sin rol'} />
      </dl>

      <div
        role="note"
        className="flex items-start gap-3 rounded-lg border border-brand/30 bg-brand-soft p-4 text-brand-deep"
      >
        <KeyRound aria-hidden="true" className="mt-0.5 size-5 shrink-0" />
        <div className="space-y-0.5">
          <p className="font-semibold">Entregue la contraseña temporal</p>
          <p className="text-sm">{REMINDER}</p>
        </div>
      </div>

      <div className="flex flex-col gap-2 sm:flex-row">
        <Button onClick={onRegisterAnother}>Registrar otra cuenta</Button>
        <Button variant="outline" asChild>
          <Link to="/cuentas/roles">Ir a Modificar rol</Link>
        </Button>
      </div>
    </div>
  )
}

function Item({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div>
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className={`break-words font-semibold text-foreground${mono ? ' font-mono' : ''}`}>{value}</dd>
    </div>
  )
}
