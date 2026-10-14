export const SIS_LENGTH = 9
export const CI_MAX_LENGTH = 10

export function digitsOnly(value: string, maxLength: number): string {
  return value.replace(/[^0-9]/g, '').slice(0, maxLength)
}

export function isValidSis(sis: string): boolean {
  return /^[0-9]{9}$/.test(sis)
}

export function hasInvalidSisMonth(sis: string): boolean {
  if (!/^[0-9]{6,9}$/.test(sis)) return false
  const month = Number(sis.slice(4, 6))
  return month < 1 || month > 12
}

export function isValidCi(ci: string): boolean {
  return ci === '' || /^[0-9]{1,10}$/.test(ci)
}
