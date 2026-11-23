import { useRef, useState, type FormEvent } from 'react'
import { Upload, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { formatFileSize } from '../utils/format'
import { validateRejectionImage } from '../utils/rejectionImage'
import { MAX_REASON_LENGTH, MIN_REASON_LENGTH, validateReason } from '../utils/rectificationValidators'

export interface RejectionDraft {
  /** Motivo ya recortado. */
  motivo: string
  evidencia: File | null
}

interface RejectRequestFormProps {
  requestId: number
  onReview: (draft: RejectionDraft) => void
  onCancel: () => void
}

/**
 * Datos para rechazar una solicitud: el motivo (obligatorio) y una imagen opcional que lo respalde. El botón no
 * rechaza: abre la confirmación. El selector de archivos admite imágenes de la galería y la cámara del
 * dispositivo, sin forzar la cámara.
 */
export function RejectRequestForm({ requestId, onReview, onCancel }: RejectRequestFormProps) {
  const [reason, setReason] = useState('')
  const [reasonError, setReasonError] = useState<string | null>(null)
  const [image, setImage] = useState<File | null>(null)
  const [imageError, setImageError] = useState<string | null>(null)
  const fileInput = useRef<HTMLInputElement>(null)
  const id = `reject-${requestId}`

  function handleFile(file: File | null) {
    if (file === null) return

    const message = validateRejectionImage(file)

    setImageError(message)
    setImage(message === null ? file : null)

    if (message !== null && fileInput.current) fileInput.current.value = ''
  }

  function clearImage() {
    setImage(null)
    setImageError(null)
    if (fileInput.current) fileInput.current.value = ''
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const message = validateReason(reason)
    setReasonError(message)

    if (message === null && imageError === null) onReview({ motivo: reason.trim(), evidencia: image })
  }

  return (
    <form
      onSubmit={handleSubmit}
      noValidate
      aria-label="Rechazar solicitud"
      className="space-y-4 rounded-md border border-border p-4"
    >
      <div className="space-y-1.5">
        <Label htmlFor={`${id}-reason`} className="text-sm font-medium">
          Motivo del rechazo <span aria-hidden="true">*</span>
        </Label>
        <Textarea
          id={`${id}-reason`}
          value={reason}
          onChange={(event) => setReason(event.target.value)}
          rows={3}
          maxLength={MAX_REASON_LENGTH + 100}
          aria-required="true"
          aria-invalid={reasonError !== null || undefined}
          aria-describedby={`${id}-reason-hint${reasonError === null ? '' : ` ${id}-reason-error`}`}
        />
        <p id={`${id}-reason-hint`} className="text-xs text-muted-foreground">
          Obligatorio, entre {MIN_REASON_LENGTH} y {MAX_REASON_LENGTH} caracteres. Explique por qué el registro
          debe mantenerse.
        </p>
        {reasonError !== null && (
          <p id={`${id}-reason-error`} role="alert" className="text-xs text-danger-fg">
            {reasonError}
          </p>
        )}
      </div>

      <div className="space-y-1.5">
        <Label htmlFor={`${id}-image`} className="text-sm font-medium">
          Imagen de respaldo (opcional)
        </Label>
        <input
          ref={fileInput}
          id={`${id}-image`}
          type="file"
          className="sr-only"
          accept="image/*"
          onChange={(event) => handleFile(event.target.files?.[0] ?? null)}
          aria-invalid={imageError !== null || undefined}
          aria-describedby={`${id}-image-hint${imageError === null ? '' : ` ${id}-image-error`}`}
        />

        {image === null ? (
          <Button type="button" variant="outline" className="w-full justify-start" onClick={() => fileInput.current?.click()}>
            <Upload className="size-4" aria-hidden="true" />
            Adjuntar una imagen
          </Button>
        ) : (
          <div className="flex items-center gap-3 rounded-lg border bg-muted/50 p-3">
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{image.name}</p>
              <p className="text-xs text-muted-foreground">{formatFileSize(image.size)}</p>
            </div>
            <Button type="button" variant="ghost" size="icon-sm" onClick={clearImage} aria-label="Quitar imagen">
              <X className="size-4" aria-hidden="true" />
            </Button>
          </div>
        )}

        <p id={`${id}-image-hint`} className="text-xs text-muted-foreground">
          JPG, PNG o WEBP, hasta 5 MB.
        </p>
        {imageError !== null && (
          <p id={`${id}-image-error`} role="alert" className="text-xs text-danger-fg">
            {imageError}
          </p>
        )}
      </div>

      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="submit" variant="danger">
          Rechazar solicitud
        </Button>
      </div>
    </form>
  )
}
