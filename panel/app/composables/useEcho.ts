import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

/**
 * pusher-js's built-in channel-auth transport never sets `withCredentials`
 * on its XHR (see node_modules/pusher-js/src/runtimes/isomorphic/auth/
 * xhr_auth.ts) — fine for same-origin Pusher/Reverb setups, wrong here,
 * where the panel and api/ are different origins and the auth request must
 * carry the Sanctum session cookie. `channelAuthorization.customHandler`
 * replaces that transport outright with the same credentialed apiFetch()
 * every other authenticated request in this app already uses.
 */
export function createEcho(): Echo<'reverb'> {
  const { public: config } = useRuntimeConfig()

  return new Echo({
    broadcaster: 'reverb',
    key: config.reverbAppKey,
    wsHost: config.reverbHost,
    wsPort: Number(config.reverbPort),
    wssPort: Number(config.reverbPort),
    forceTLS: config.reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    Pusher,
    channelAuthorization: {
      customHandler: async (
        params: { channelName: string; socketId: string },
        callback: (error: Error | null, data: { auth: string; channel_data?: string } | null) => void,
      ) => {
        try {
          const data = await apiFetch<{ auth: string; channel_data?: string }>('/broadcasting/auth', {
            method: 'POST',
            body: { channel_name: params.channelName, socket_id: params.socketId },
          })
          callback(null, data)
        } catch (e) {
          callback(e as Error, null)
        }
      },
    },
  })
}
