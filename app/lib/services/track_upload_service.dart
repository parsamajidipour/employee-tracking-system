import '../config.dart';
import 'api_client.dart';
import 'auth_storage.dart';
import 'location_queue_repository.dart';

/// Drains the queue every ~30s (the same tick TrackingTaskHandler already
/// runs for the window self-check — not a second timer). Rejection is not
/// an error: the server always answers 202 regardless of accept/reject
/// mix, so reaching a response at all means the whole batch is done with —
/// delete it. Any failure to even get a response (network, 5xx, ...)
/// leaves the batch queued for the next tick, no error surfaced, matching
/// this app's established resilience pattern (MeRepository's own
/// cache-fallback-on-any-failure).
class TrackUploadService {
  static const _batchLimit = 500;
  static const _maxPointAge = Duration(hours: 48);

  final LocationQueueRepository _repository;
  final AuthStorage _storage;
  late final ApiClient _apiClient;

  /// onUnauthorized is a no-op placeholder here — step 5 wires this to the
  /// real "stop the service, clear the token, signal the main isolate"
  /// path. Kept as a constructor parameter (not hardcoded) so that step's
  /// change is a one-line edit at the call site, not a rewrite of this
  /// class.
  TrackUploadService({
    LocationQueueRepository? repository,
    AuthStorage? storage,
    Future<void> Function()? onUnauthorized,
  })  : _repository = repository ?? LocationQueueRepository(),
        _storage = storage ?? AuthStorage() {
    _apiClient = ApiClient(
      baseUrl: apiBaseUrl,
      storage: _storage,
      onUnauthorized: onUnauthorized ?? (() async {}),
    );
  }

  Future<void> runUploadCycle() async {
    await _repository.purgeOlderThan(DateTime.now().subtract(_maxPointAge));

    final batch = await _repository.nextBatch(limit: _batchLimit);
    if (batch.isEmpty) return;

    try {
      await _apiClient.postJson('/api/v1/track', {
        'points': batch.map((point) => point.toApiJson()).toList(),
      });
      await _repository.deleteIds(batch.map((point) => point.id!).toList());
      await _storage.saveLastUploadAt(DateTime.now());
    } catch (_) {
      // Left queued — see class docblock.
    }
  }
}
