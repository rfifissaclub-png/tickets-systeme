import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

import { useAuthStore } from '@/stores/auth-store'

// Reverb speaks the Pusher protocol — laravel-echo's "reverb" broadcaster is
// really just Pusher-js under the hood with different defaults, and it looks
// for `window.Pusher` at construction time.
declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}
window.Pusher = Pusher

let echoInstance: Echo<'reverb'> | null = null

/**
 * Lazily creates a single Echo connection, authenticated with the current
 * Sanctum bearer token. Returns null if there's no session to authenticate
 * with — callers should treat that as "don't subscribe yet", not an error.
 */
export function getEcho(): Echo<'reverb'> | null {
  const token = useAuthStore.getState().token
  if (!token) return null

  if (echoInstance) return echoInstance

  const scheme = import.meta.env.VITE_REVERB_SCHEME
  const port = Number(import.meta.env.VITE_REVERB_PORT)

  echoInstance = new Echo<'reverb'>({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: scheme === 'https' ? ['ws', 'wss'] : ['ws'],
    // /broadcasting/auth is registered at the app root, not under /api/v1
    // (see backend/tms-backend/bootstrap/app.php's withBroadcasting call).
    authEndpoint: `${import.meta.env.VITE_APP_URL}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    },
  })

  return echoInstance
}

/** Call on logout — a stale connection would keep authenticating as the old user. */
export function disconnectEcho(): void {
  echoInstance?.disconnect()
  echoInstance = null
}
