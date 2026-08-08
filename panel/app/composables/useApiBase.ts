export function apiOrigin(): string {
  const { public: config } = useRuntimeConfig()

  if (config.apiBase) {
    return config.apiBase
  }

  const { protocol, hostname } = window.location

  return `${protocol}//${hostname}:${config.apiPort}`
}

export function websocketHost(): string {
  const { public: config } = useRuntimeConfig()

  return config.reverbHost || window.location.hostname
}
