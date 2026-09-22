import { useEffect } from 'react'

export interface ToastProps {
  title: string
  description: string
  duration?: number
  onClose: () => void
}

export function Toast({ title, description, duration = 5000, onClose }: ToastProps) {
  useEffect(() => {
    const timer = setTimeout(() => {
      onClose()
    }, duration)

    return () => clearTimeout(timer)
  }, [duration, onClose])

  return (
    <div
      className="fixed top-20 right-8 z-50 flex items-start gap-3 w-[380px] bg-card text-card-foreground p-3.5 rounded-lg border border-border shadow-lg animate-in fade-in slide-in-from-top-2 duration-200"
      role="status"
      aria-live="polite"
    >
      {/* Ícono de éxito OK */}
      <svg
        className="size-5 shrink-0 text-[#008A52] mt-0.5"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
        <path d="m9 11 3 3L22 4"></path>
      </svg>

      {/* Contenido principal */}
      <div className="flex-1 min-w-0 pr-1">
        <div className="text-sm font-semibold text-[#2C2C2C]">{title}</div>
        <div className="text-xs text-muted-foreground mt-0.5 leading-normal">
          {description}
        </div>
      </div>

      {/* Botón de cierre */}
      <button
        type="button"
        onClick={onClose}
        className="text-[#8A969B] hover:text-foreground transition-colors p-0.5 rounded-sm"
        aria-label="Cerrar notificación"
      >
        <svg
          className="size-4"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M18 6 6 18"></path>
          <path d="m6 6 12 12"></path>
        </svg>
      </button>
    </div>
  )
}