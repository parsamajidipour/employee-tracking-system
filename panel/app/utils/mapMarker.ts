export interface EmployeeMarkerPosition {
  lat: number
  lng: number
}

const GLIDE_DURATION_MS = 700

function easeOutCubic(t: number): number {
  return 1 - (1 - t) ** 3
}

export interface EmployeeMarkerOverlayInstance {
  setName(name: string): void
  setColor(color: string): void
  moveTo(position: EmployeeMarkerPosition): void
  destroy(): void
}

type EmployeeMarkerOverlayCtor = new (
  map: google.maps.Map,
  position: EmployeeMarkerPosition,
  name: string,
  onSelect: () => void,
) => EmployeeMarkerOverlayInstance

let cachedCtor: EmployeeMarkerOverlayCtor | null = null

/**
 * `google.maps.OverlayView` only exists once the Maps JS API script has finished
 * loading, so this class can't be declared at module scope (`class X extends
 * google.maps.OverlayView` would throw ReferenceError on import, before the
 * script tag has run). Building it lazily from the loaded `google` namespace is
 * the fix — call this once after `useGoogleMaps().load()` resolves.
 *
 * The overlay always shows a name label (not hover-only) and glides between
 * position updates instead of snapping, so GPS noise around a stationary point
 * reads as a smooth micro-drift rather than a visible jitter. The coordinate
 * driving the glide is always the exact reported position — nothing here
 * rounds or discards accuracy.
 */
export function getEmployeeMarkerOverlayCtor(mapsApi: typeof google): EmployeeMarkerOverlayCtor {
  if (cachedCtor) return cachedCtor

  class EmployeeMarkerOverlay extends mapsApi.maps.OverlayView implements EmployeeMarkerOverlayInstance {
    private div: HTMLDivElement | null = null
    private dot: HTMLDivElement | null = null
    private label: HTMLSpanElement | null = null
    private current: google.maps.LatLng
    private animationFrame: number | null = null

    constructor(
      map: google.maps.Map,
      position: EmployeeMarkerPosition,
      private name: string,
      private onSelect: () => void,
    ) {
      super()
      this.current = new mapsApi.maps.LatLng(position.lat, position.lng)
      this.setMap(map)
    }

    override onAdd(): void {
      this.div = document.createElement('div')
      this.div.className = 'group absolute -translate-x-1/2 -translate-y-1/2 cursor-pointer select-none'
      this.div.style.zIndex = '10'

      this.dot = document.createElement('div')
      this.dot.className = 'h-4 w-4 rounded-full border-2 border-white shadow-[0_2px_8px_rgba(0,0,0,0.35)]'

      this.label = document.createElement('span')
      this.label.textContent = this.name
      this.label.className =
        'pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-ink shadow-raised ring-1 ring-hairline'

      this.div.append(this.dot, this.label)
      this.div.addEventListener('click', () => this.onSelect())

      this.getPanes()?.overlayMouseTarget.appendChild(this.div)
    }

    override draw(): void {
      const projection = this.getProjection()
      if (!projection || !this.div) return

      const point = projection.fromLatLngToDivPixel(this.current)
      if (!point) return

      this.div.style.left = `${point.x}px`
      this.div.style.top = `${point.y}px`
    }

    override onRemove(): void {
      if (this.animationFrame !== null) cancelAnimationFrame(this.animationFrame)
      this.div?.remove()
      this.div = null
      this.dot = null
      this.label = null
    }

    setName(name: string): void {
      this.name = name
      if (this.label) this.label.textContent = name
    }

    setColor(color: string): void {
      if (this.dot) this.dot.style.backgroundColor = color
    }

    moveTo(position: EmployeeMarkerPosition): void {
      const start = this.current
      const target = new mapsApi.maps.LatLng(position.lat, position.lng)

      if (this.animationFrame !== null) cancelAnimationFrame(this.animationFrame)

      const startLat = start.lat()
      const startLng = start.lng()
      const endLat = target.lat()
      const endLng = target.lng()
      const startedAt = performance.now()

      const tick = (now: number) => {
        const t = Math.min(1, (now - startedAt) / GLIDE_DURATION_MS)
        const eased = easeOutCubic(t)
        this.current = new mapsApi.maps.LatLng(startLat + (endLat - startLat) * eased, startLng + (endLng - startLng) * eased)
        this.draw()

        if (t < 1) {
          this.animationFrame = requestAnimationFrame(tick)
        } else {
          this.animationFrame = null
        }
      }

      this.animationFrame = requestAnimationFrame(tick)
    }

    destroy(): void {
      if (this.animationFrame !== null) cancelAnimationFrame(this.animationFrame)
      this.setMap(null)
    }
  }

  cachedCtor = EmployeeMarkerOverlay
  return EmployeeMarkerOverlay
}

/** Initial bearing in degrees (0 = north, clockwise) from one point to another. */
export function bearingBetween(from: EmployeeMarkerPosition, to: EmployeeMarkerPosition): number {
  const phi1 = (from.lat * Math.PI) / 180
  const phi2 = (to.lat * Math.PI) / 180
  const deltaLambda = ((to.lng - from.lng) * Math.PI) / 180

  const y = Math.sin(deltaLambda) * Math.cos(phi2)
  const x = Math.cos(phi1) * Math.sin(phi2) - Math.sin(phi1) * Math.cos(phi2) * Math.cos(deltaLambda)
  const theta = Math.atan2(y, x)

  return ((theta * 180) / Math.PI + 360) % 360
}

export const SHIFT_PALETTE = ['#2563eb', '#db2777', '#16a34a', '#d97706', '#7c3aed', '#0891b2', '#dc2626', '#0d9488']

export function shiftColor(index: number): string {
  return SHIFT_PALETTE[index % SHIFT_PALETTE.length]!
}
