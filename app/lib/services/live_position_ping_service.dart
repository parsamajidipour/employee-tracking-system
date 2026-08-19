import 'package:geolocator/geolocator.dart';

import '../config.dart';
import 'api_client.dart';
import 'auth_storage.dart';

class LivePositionPingService {
  late final ApiClient _apiClient;

  LivePositionPingService({AuthStorage? storage}) {
    _apiClient = ApiClient(
      baseUrl: apiBaseUrl,
      storage: storage ?? AuthStorage(),
      onUnauthorized: () async {},
    );
  }

  Future<void> ping(Position position, int? batteryPct) async {
    try {
      await _apiClient.postJson('/api/v1/track/ping', {
        'lat': position.latitude,
        'lng': position.longitude,
        'accuracy_m': position.accuracy,
        'battery_pct': batteryPct,
        'recorded_at': position.timestamp.toIso8601String(),
      });
    } catch (_) {
    }
  }
}
