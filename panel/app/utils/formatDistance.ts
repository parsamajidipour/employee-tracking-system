export function formatDistance(meters: number): string {
  if (meters >= 1000) return `${(meters / 1000).toFixed(2)} km`
  return `${Math.round(meters)} m`
}

export function formatSpeed(metersPerSecond: number | null): string {
  if (metersPerSecond === null) return '—'
  return `${(metersPerSecond * 3.6).toFixed(1)} km/h`
}
