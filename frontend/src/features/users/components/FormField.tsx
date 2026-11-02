import type { ReactNode } from 'react'
import { Label } from '@/components/ui/label'

interface FormFieldProps {
  id: string
  label: string
  required?: boolean
  error?: string
  hint?: string
  children: ReactNode
}

/** Campo de formulario: etiqueta, control y, debajo, el error o la ayuda. */
export function FormField({ id, label, required = false, error, hint, children }: FormFieldProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id}>
        {label}
        {required && (
          <span aria-hidden="true" className="text-danger">
            *
          </span>
        )}
      </Label>
      {children}
      {error ? (
        <p id={`${id}-ayuda`} role="alert" className="text-sm font-medium text-danger">
          {error}
        </p>
      ) : (
        hint && (
          <p id={`${id}-ayuda`} className="text-sm text-muted-foreground">
            {hint}
          </p>
        )
      )}
    </div>
  )
}
