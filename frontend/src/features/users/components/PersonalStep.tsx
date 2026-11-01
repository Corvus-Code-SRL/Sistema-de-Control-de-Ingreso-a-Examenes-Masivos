import { useState } from 'react'
import { PasswordInput } from '@/components/common/PasswordInput'
import { Input } from '@/components/ui/input'
import {
  MAX_EMAIL_LENGTH,
  MAX_NAME_LENGTH,
  MAX_PASSWORD_LENGTH,
  MAX_SURNAME_LENGTH,
  validatePersonal,
  type DraftErrors,
  type DraftField,
  type RegistrationDraft,
} from '../lib/registrationDraft'
import { FormField } from './FormField'

interface PersonalStepProps {
  draft: RegistrationDraft
  /** Lo que el servidor rechazó de estos campos al registrar; se borra al editar el campo. */
  serverErrors: DraftErrors
  onChange: (changes: Partial<RegistrationDraft>) => void
  onClearServerError: (field: DraftField) => void
}

/** Paso 2 del registro: nombres, correo y contraseña temporal. */
export function PersonalStep({ draft, serverErrors, onChange, onClearServerError }: PersonalStepProps) {
  const [touched, setTouched] = useState<Partial<Record<DraftField, boolean>>>({})
  const found = validatePersonal(draft)

  const errorOf = (field: DraftField) => serverErrors[field] ?? (touched[field] ? found[field] : undefined)
  const touch = (field: DraftField) => setTouched((current) => ({ ...current, [field]: true }))
  const edit = (field: DraftField, changes: Partial<RegistrationDraft>) => {
    onChange(changes)
    onClearServerError(field)
  }

  return (
    <div className="grid gap-5">
      <div className="grid gap-5 md:grid-cols-3">
        <FormField id="registro-nombre" label="Nombres" required error={errorOf('nombre')}>
          <Input
            id="registro-nombre"
            value={draft.nombre}
            maxLength={MAX_NAME_LENGTH}
            aria-required="true"
            aria-invalid={Boolean(errorOf('nombre')) || undefined}
            onBlur={() => touch('nombre')}
            onChange={(event) => edit('nombre', { nombre: event.target.value })}
            className="h-11"
          />
        </FormField>
        <FormField id="registro-paterno" label="Apellido paterno" required error={errorOf('apellido_paterno')}>
          <Input
            id="registro-paterno"
            value={draft.apellidoPaterno}
            maxLength={MAX_SURNAME_LENGTH}
            aria-required="true"
            aria-invalid={Boolean(errorOf('apellido_paterno')) || undefined}
            onBlur={() => touch('apellido_paterno')}
            onChange={(event) => edit('apellido_paterno', { apellidoPaterno: event.target.value })}
            className="h-11"
          />
        </FormField>
        <FormField id="registro-materno" label="Apellido materno" error={errorOf('apellido_materno')}>
          <Input
            id="registro-materno"
            value={draft.apellidoMaterno}
            maxLength={MAX_SURNAME_LENGTH}
            onChange={(event) => edit('apellido_materno', { apellidoMaterno: event.target.value })}
            className="h-11"
          />
        </FormField>
      </div>

      <FormField
        id="registro-correo"
        label="Correo institucional"
        required
        error={errorOf('correo')}
        hint="Este correo quedará asociado a la cuenta."
      >
        <Input
          id="registro-correo"
          type="email"
          value={draft.correo}
          maxLength={MAX_EMAIL_LENGTH}
          autoComplete="off"
          aria-required="true"
          aria-invalid={Boolean(errorOf('correo')) || undefined}
          onBlur={() => touch('correo')}
          onChange={(event) => edit('correo', { correo: event.target.value })}
          className="h-11"
        />
      </FormField>

      <FormField
        id="registro-contrasenia"
        label="Contraseña temporal"
        required
        error={errorOf('contrasenia_temporal')}
        hint="Entréguela a la persona: deberá cambiarla en su primer ingreso."
      >
        <PasswordInput
          id="registro-contrasenia"
          name="contrasenia_temporal"
          autoComplete="new-password"
          autoCapitalize="off"
          autoCorrect="off"
          spellCheck={false}
          maxLength={MAX_PASSWORD_LENGTH}
          placeholder="Mínimo 8 caracteres"
          aria-required="true"
          aria-invalid={Boolean(errorOf('contrasenia_temporal')) || undefined}
          value={draft.contrasenia}
          onBlur={() => touch('contrasenia_temporal')}
          onChange={(event) => edit('contrasenia_temporal', { contrasenia: event.target.value })}
          groupClassName={errorOf('contrasenia_temporal') ? 'border-danger' : undefined}
        />
      </FormField>
    </div>
  )
}
