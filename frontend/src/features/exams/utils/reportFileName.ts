function slugify(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

/** Nombre del archivo descargado del reporte final; es el mismo que arma el backend (HU-051). */
export function reportFileName(examName: string, date: string, extension: 'pdf' | 'xlsx'): string {
  return `reporte-final-${slugify(examName)}-${date}.${extension}`
}
