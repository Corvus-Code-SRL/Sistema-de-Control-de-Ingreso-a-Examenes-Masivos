/** Minúsculas y sin marcas diacríticas: «Cálculo» y «calculo» se comparan igual. */
export function normalizeSearch(text: string): string {
  return text
    .normalize('NFD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .trim()
}
