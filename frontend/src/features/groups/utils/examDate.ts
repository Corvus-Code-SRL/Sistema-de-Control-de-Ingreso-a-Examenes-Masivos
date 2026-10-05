/**
 * La fecha del examen llega como YYYY-MM-DD, sin zona. Se arma a medianoche local: con
 * `new Date('YYYY-MM-DD')` el navegador la toma en UTC y en Bolivia mostraría el día anterior.
 */
export function formatExamDate(value: string | null): string {
  if (!value) return 'Sin fecha'

  return new Date(`${value}T00:00:00`).toLocaleDateString('es-BO', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}
