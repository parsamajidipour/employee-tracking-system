import { Marker as MapLibreMarker, type Map as MapLibreMap } from 'maplibre-gl'

export interface EmployeeMarkerPosition {
  lat: number
  lng: number
}

export interface EmployeeMarkerOverlayInstance {
  setName(name: string): void
  setColor(color: string): void
  setSelected(selected: boolean): void
  setPulsing(pulsing: boolean): void
  moveTo(position: EmployeeMarkerPosition): void
  destroy(): void
}

/**
 * MapLibre's `Marker` already reprojects its DOM element on every map render,
 * so unlike the old Google `OverlayView` version this needs no manual
 * `draw()`/projection step. Position updates must snap immediately so the
 * marker stays attached to the reported location while the map is dragged or
 * moved.
 */
export function createEmployeeMarker(
  map: MapLibreMap,
  position: EmployeeMarkerPosition,
  name: string,
  onSelect: () => void,
): EmployeeMarkerOverlayInstance {
  const div = document.createElement('div')
  div.className = 'group relative h-6 w-6 cursor-pointer select-none'
  div.style.zIndex = '10'

  const pulse = document.createElement('div')
  pulse.className = 'absolute inset-0 -z-10 rounded-full opacity-0'

  const dot = document.createElement('div')
  dot.className = 'relative h-6 w-6 rounded-full border-[3px] border-white shadow-[0_2px_10px_rgba(0,0,0,0.4)] transition-shadow duration-150'

  const label = document.createElement('span')
  label.textContent = name
  label.className =
    'pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-ink shadow-raised ring-1 ring-hairline'

  div.append(pulse, dot, label)
  div.addEventListener('click', onSelect)

  const marker = new MapLibreMarker({ element: div }).setLngLat([position.lng, position.lat]).addTo(map)

  return {
    setName(newName: string): void {
      label.textContent = newName
    },

    setColor(color: string): void {
      dot.style.backgroundColor = color
      pulse.style.backgroundColor = color
    },

    setSelected(selected: boolean): void {
      div.style.zIndex = selected ? '20' : '10'
      dot.style.boxShadow = selected ? `0 0 0 3px var(--primary), 0 2px 10px rgba(0,0,0,0.4)` : '0 2px 10px rgba(0,0,0,0.4)'
      dot.style.scale = selected ? '1.15' : '1'
    },

    setPulsing(pulsing: boolean): void {
      pulse.className = pulsing ? 'absolute inset-0 -z-10 rounded-full opacity-40 animate-ping' : 'absolute inset-0 -z-10 rounded-full opacity-0'
    },

    moveTo(target: EmployeeMarkerPosition): void {
      marker.setLngLat([target.lng, target.lat])
    },

    destroy(): void {
      marker.remove()
    },
  }
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
