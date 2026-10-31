const LOCALE = 'es-BO'

/** «1,2 MB», «340 KB», «512 B». */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`

  const format = (value: number) => value.toLocaleString(LOCALE, { maximumFractionDigits: 1 })

  if (bytes < 1024 * 1024) return `${format(bytes / 1024)} KB`

  return `${format(bytes / (1024 * 1024))} MB`
}
