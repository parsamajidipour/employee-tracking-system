import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export function createEcho(): Echo<'reverb'> {
  const { public: config } = useRuntimeConfig()

  return new Echo({
    broadcaster: 'reverb',
    key: config.reverbAppKey,
    wsHost: websocketHost(),
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

let shared: Echo<'reverb'> | null = null

export function sharedEcho(): Echo<'reverb'> | null {
  if (shared) return shared

  try {
    shared = createEcho()
  } catch {
    shared = null
  }

  return shared
}
