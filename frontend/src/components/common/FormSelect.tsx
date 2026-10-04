import { cn } from '@/lib/utils'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export interface FormSelectOption {
  value: string
  label: string
  disabled?: boolean
}

interface FormSelectProps {
  id: string
  value?: string
  placeholder: string
  options: FormSelectOption[]
  onValueChange: (value: string) => void
  disabled?: boolean
  invalid?: boolean
  className?: string
}

/** Selector controlado común para formularios; el contenido vive en un portal estable. */
export function FormSelect({
  id,
  value,
  placeholder,
  options,
  onValueChange,
  disabled = false,
  invalid = false,
  className,
}: FormSelectProps) {
  return (
    <Select value={value ?? ''} onValueChange={onValueChange} disabled={disabled}>
      <SelectTrigger
        id={id}
        aria-invalid={invalid || undefined}
        className={cn('h-11 w-full bg-background px-3.5 text-left text-xs font-medium', className)}
      >
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent position="popper" align="start">
        {options.map((option) => (
          <SelectItem key={option.value} value={option.value} disabled={option.disabled}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
