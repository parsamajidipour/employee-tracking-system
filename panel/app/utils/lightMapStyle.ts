export const LIGHT_MAP_STYLE: google.maps.MapTypeStyle[] = [
  { elementType: 'geometry', stylers: [{ color: '#f7f7fa' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#ffffff' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#9a9aa6' }] },
  { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#e4e4ea' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
  { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#e4e4ea' }] },
  { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#9a9aa6' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#eef0ff' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#e4e4ea' }] },
  { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#9a9aa6' }] },
]
