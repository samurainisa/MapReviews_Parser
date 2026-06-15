import { onUnmounted } from 'vue'

/**
 * Периодический опрос (polling) с гарантированной остановкой:
 *  - вручную через stop();
 *  - при размонтировании компонента (уход со страницы);
 *  - когда callback вернул true (условие завершения достигнуто).
 */
export function usePolling(callback: () => Promise<boolean>, intervalMs = 4000) {
  let timer: ReturnType<typeof setInterval> | null = null

  function stop(): void {
    if (timer !== null) {
      clearInterval(timer)
      timer = null
    }
  }

  function start(): void {
    stop()
    timer = setInterval(async () => {
      const done = await callback()
      if (done) stop()
    }, intervalMs)
  }

  onUnmounted(stop)

  return { start, stop }
}
