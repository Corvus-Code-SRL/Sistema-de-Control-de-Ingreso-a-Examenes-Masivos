import { useMemo, useState } from 'react'
import { Check, ChevronsUpDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { normalizeSearch } from '@/lib/text'
import { cn } from '@/lib/utils'

export interface SearchableSelectOption {
  value: string
  label: string
  /** Texto sobre el que se busca; por omisión, la etiqueta. */
  searchText?: string
}

interface SearchableSelectProps {
  id: string
  /** Nombre accesible del combobox, que el texto del botón no reemplaza. */
  label: string
  value: string | null
  options: SearchableSelectOption[]
  onValueChange: (value: string) => void
  placeholder: string
  searchPlaceholder?: string
  emptyText?: string
  disabled?: boolean
  invalid?: boolean
  className?: string
}

/**
 * Selector con búsqueda: filtra en el cliente ignorando tildes y mayúsculas.
 *
 * El filtro es propio (`shouldFilter={false}` en cmdk) porque el de cmdk distingue tildes: con él,
 * «calculo» no encontraría «Cálculo I».
 */
export function SearchableSelect({
  id,
  label,
  value,
  options,
  onValueChange,
  placeholder,
  searchPlaceholder = 'Buscar por código o nombre',
  emptyText = 'Sin resultados',
  disabled = false,
  invalid = false,
  className,
}: SearchableSelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')

  const selected = options.find((option) => option.value === value) ?? null

  const visible = useMemo(() => {
    const term = normalizeSearch(search)

    if (term === '') {
      return options
    }

    return options.filter((option) =>
      normalizeSearch(option.searchText ?? option.label).includes(term)
    )
  }, [options, search])

  const handleOpenChange = (next: boolean) => {
    setOpen(next)

    if (!next) {
      setSearch('')
    }
  }

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <Button
          id={id}
          type="button"
          variant="outline"
          role="combobox"
          aria-label={label}
          aria-expanded={open}
          aria-invalid={invalid || undefined}
          disabled={disabled}
          className={cn(
            'w-full justify-between bg-background px-3 font-normal',
            selected === null && 'text-muted-foreground',
            className
          )}
        >
          <span className="truncate">{selected?.label ?? placeholder}</span>
          <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden="true" />
        </Button>
      </PopoverTrigger>

      <PopoverContent align="start" className="w-(--radix-popover-trigger-width) p-0">
        <Command shouldFilter={false} label={`Buscar: ${label}`}>
          <CommandInput
            value={search}
            onValueChange={setSearch}
            placeholder={searchPlaceholder}
          />

          <CommandList>
            {visible.length === 0 ? (
              <p role="status" className="py-6 text-center text-sm text-muted-foreground">
                {emptyText}
              </p>
            ) : (
              <CommandGroup>
                {visible.map((option) => (
                  <CommandItem
                    key={option.value}
                    value={option.value}
                    onSelect={() => {
                      onValueChange(option.value)
                      handleOpenChange(false)
                    }}
                  >
                    <span className="truncate">{option.label}</span>
                    <Check
                      className={cn(
                        'ml-auto size-4',
                        option.value === value ? 'opacity-100' : 'opacity-0'
                      )}
                      aria-hidden="true"
                    />
                  </CommandItem>
                ))}
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
