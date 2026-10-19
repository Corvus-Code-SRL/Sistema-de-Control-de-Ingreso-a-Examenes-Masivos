import { describe, expect, it } from 'vitest'
import { formatFileSize } from './format'

describe('formatFileSize', () => {
  it.each([
    [512, '512 B'],
    [2048, '2 KB'],
    [1_258_291, '1,2 MB'],
  ])('%i bytes → %s', (bytes, expected) => {
    expect(formatFileSize(bytes)).toBe(expected)
  })
})
