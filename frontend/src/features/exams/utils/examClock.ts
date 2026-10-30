export interface ExamClockSync {
  serverNowMs: number;
  sampledMonoMs: number;
}

/**
 * Calcula el tiempo restante utilizando la hora del servidor
 * y el tiempo monotónico transcurrido en el navegador.
 *
 * No depende de Date.now() del dispositivo.
 */
export function remainingExamSeconds(
  endAtIso: string,
  sync: ExamClockSync,
  currentMonoMs: number
): number {
  const endMs = Date.parse(endAtIso);

  if (!Number.isFinite(endMs)) {
    return 0;
  }

  const elapsedMs = Math.max(
    0,
    currentMonoMs - sync.sampledMonoMs
  );

  const estimatedServerNowMs =
    sync.serverNowMs + elapsedMs;

  return Math.max(
    0,
    Math.ceil((endMs - estimatedServerNowMs) / 1000)
  );
}
