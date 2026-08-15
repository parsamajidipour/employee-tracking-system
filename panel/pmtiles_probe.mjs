import { PMTiles, FetchSource } from 'pmtiles'

const url = 'http://164.90.163.27:8000/api/basemap/oman.pmtiles'
const p = new PMTiles(url)

const header = await p.getHeader()
console.log('HEADER:', JSON.stringify(header, null, 2))

const metadata = await p.getMetadata()
console.log('METADATA keys:', Object.keys(metadata || {}))

for (const [z, x, y] of [[10, 692, 380], [14, 11080, 6096], [16, 44320, 24384]]) {
  try {
    const tile = await p.getZxy(z, x, y)
    console.log(`tile z${z}/${x}/${y}:`, tile ? `${tile.data.byteLength} bytes` : 'null (no data)')
  } catch (e) {
    console.log(`tile z${z}/${x}/${y}: ERROR`, e.message)
  }
}
