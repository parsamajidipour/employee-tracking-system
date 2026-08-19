export const DARK_MAP_STYLE: google.maps.MapTypeStyle[] = [
  { elementType: 'geometry', stylers: [{ color: '#16161d' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#0b0b10' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#7a7a8a' }] },
  { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#26262f' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#1e1e27' }] },
  { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#16161d' }] },
  { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#6b6b7a' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#26262f' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0b0b10' }] },
  { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#4a4a58' }] },
]
