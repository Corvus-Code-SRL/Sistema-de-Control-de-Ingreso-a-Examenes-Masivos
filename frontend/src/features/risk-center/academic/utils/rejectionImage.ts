/** Límites que también impone el servidor (RejectRectificationRequestRequest). */
export const MAX_REJECTION_IMAGE_BYTES = 5 * 1024 * 1024

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp']

export const REJECTION_IMAGE_ERROR = 'Formato o tamaño no permitido. Use una imagen jpg, png o webp de hasta 5 MB.'

/** Mensaje si la imagen no sirve como evidencia del rechazo; null si es válida. */
export function validateRejectionImage(file: File): string | null {
  if (!ALLOWED_TYPES.includes(file.type) || file.size > MAX_REJECTION_IMAGE_BYTES) return REJECTION_IMAGE_ERROR

  return null
}
