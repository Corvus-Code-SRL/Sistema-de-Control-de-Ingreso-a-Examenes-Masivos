export const SIS_LENGTH = 9
export const CI_MAX_LENGTH = 10

export function digitsOnly(value: string, maxLength: number): string {
  return value.replace(/[^0-9]/g, '').slice(0, maxLength)
}

export function isValidSis(sis: string): boolean {
  return /^[0-9]{9}$/.test(sis)
}

export function isValidCi(ci: string): boolean {
  return ci === '' || /^[0-9]{1,10}$/.test(ci)
}
