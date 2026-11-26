import path from 'path'
import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    include: ['src/**/*.test.{ts,tsx}'],
    // Cada archivo monta la app completa en jsdom: con todos los núcleos compiten por CPU y los tests de
    // integración superan sus plazos sin fallar de verdad. Medido sobre 1980 tests: con 20 workers hubo ~11
    // timeouts por corrida, con 8 hubo 1 de 3 corridas y con 4 ninguno (test más lento 1,0 s, suite en ~100 s).
    maxWorkers: 4,
    minWorkers: 1,
  },
})
