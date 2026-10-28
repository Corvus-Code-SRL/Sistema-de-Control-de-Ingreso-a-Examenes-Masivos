import { describe, expect, it, vi } from 'vitest';
import {
  remainingExamSeconds,
  type ExamClockSync,
} from './examClock';

describe('HU-038 - reloj sincronizado con el servidor', () => {
  const sync: ExamClockSync = {
    serverNowMs: Date.parse('2026-10-28T10:00:00-04:00'),
    sampledMonoMs: 5000,
  };

  const endAt = '2026-10-28T10:10:00-04:00';

  it('muestra diez minutos inicialmente', () => {
    expect(
      remainingExamSeconds(endAt, sync, 5000)
    ).toBe(600);
  });

  it('descuenta dos minutos transcurridos', () => {
    expect(
      remainingExamSeconds(endAt, sync, 125000)
    ).toBe(480);
  });

  it('no muestra valores negativos al terminar', () => {
    expect(
      remainingExamSeconds(endAt, sync, 605000)
    ).toBe(0);
  });

  it('no depende de la hora configurada en el dispositivo', () => {
    const dateNow = vi.spyOn(Date, 'now');

    try {
      dateNow.mockReturnValue(
        Date.parse('2035-01-01T00:00:00Z')
      );

      expect(
        remainingExamSeconds(endAt, sync, 65000)
      ).toBe(540);

      dateNow.mockReturnValue(
        Date.parse('2015-01-01T00:00:00Z')
      );

      expect(
        remainingExamSeconds(endAt, sync, 65000)
      ).toBe(540);
    } finally {
      dateNow.mockRestore();
    }
  });
});
