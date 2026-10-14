import * as React from 'react'
import { DayPicker, type DayPickerProps } from 'react-day-picker'
import { es } from 'react-day-picker/locale'
import { cn } from '@/lib/utils'

/** Calendario compacto con los colores y tamaños de SCIEM. */
export function Calendar({ className, classNames, ...props }: DayPickerProps) {
  return (
    <DayPicker
      locale={es}
      weekStartsOn={1}
      showOutsideDays
      className={cn('relative w-full text-sm text-foreground', className)}
      classNames={{
        months: 'w-full',
        month: 'w-full',
        month_caption: 'flex h-10 items-center justify-center px-10',
        caption_label: 'font-semibold capitalize',
        nav: 'absolute inset-x-0 top-0 flex h-10 items-center justify-between',
        button_previous: 'flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-40',
        button_next: 'flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-40',
        chevron: 'size-4 fill-current',
        month_grid: 'w-full table-fixed border-collapse',
        weekdays: 'w-full',
        weekday: 'h-9 text-center text-xs font-medium text-muted-foreground',
        week: 'w-full',
        day: 'h-9 text-center align-middle',
        day_button: 'mx-auto flex size-9 items-center justify-center rounded-lg text-sm hover:bg-accent hover:text-accent-foreground focus-visible:outline-2 focus-visible:outline-ring',
        today: 'font-bold text-primary',
        selected: '[&>button]:bg-primary [&>button]:text-primary-foreground [&>button]:hover:bg-primary [&>button]:hover:text-primary-foreground',
        outside: 'text-muted-foreground/50',
        disabled: 'text-muted-foreground/40 [&>button]:cursor-not-allowed [&>button]:hover:bg-transparent',
        ...classNames,
      }}
      {...props}
    />
  )
}
