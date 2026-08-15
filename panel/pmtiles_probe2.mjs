import { PMTiles } from 'pmtiles'

function lonLatToTile(lon, lat, z) {
  const n = 2 ** z
  const x = Math.floor((lon + 180) / 360 * n)
  const latRad = lat * Math.PI / 180
  const y = Math.floor((1 - Math.log(Math.tan(latRad) + 1 / Math.cos(latRad)) / Math.PI) / 2 * n)
  return [x, y]
}

const url = 'http://164.90.163.27:8000/api/basemap/oman.pmtiles'
const p = new PMTiles(url)

const lon = 58.5922, lat = 23.6144
for (const z of [5, 8, 10, 12, 14, 15, 16]) {
  const [x, y] = lonLatToTile(lon, lat, z)
  try {
    const tile = await p.getZxy(z, x, y)
    console.log(`z${z} (${x},${y}):`, tile ? `${tile.data.byteLength} bytes` : 'null (no data)')
  } catch (e) {
    console.log(`z${z} (${x},${y}): ERROR`, e.message)
  }
}
