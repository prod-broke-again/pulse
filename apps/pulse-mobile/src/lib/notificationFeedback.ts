/**
 * Короткий системный сигнал для входящего сообщения (без внешних ассетов).
 */
export function playIncomingTone(enabled: boolean): void {
  if (!enabled || typeof window === 'undefined') {
    return
  }
  try {
    const ctx = new AudioContext()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.frequency.value = 880
    osc.type = 'sine'
    gain.gain.value = 0.07
    const now = ctx.currentTime
    osc.start(now)
    osc.stop(now + 0.1)
  } catch {
    /* WebAudio может быть заблокирован до жеста пользователя */
  }
}

export function vibrateIncoming(enabled: boolean): void {
  if (!enabled || typeof navigator === 'undefined' || typeof navigator.vibrate !== 'function') {
    return
  }
  try {
    navigator.vibrate(45)
  } catch {
    /* ignore */
  }
}
