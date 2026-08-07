import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import '../config.dart';
import 'api_client.dart';
import 'api_exception.dart';
import 'auth_storage.dart';
import 'location_queue_repository.dart';

/// Sent to the main isolate when an upload discovers the device has been
/// revoked — see main.dart's addTaskDataCallback listener, which turns this
/// into AuthController.handleUnauthorized().
const unauthorizedMarker = '__unauthorized__';

/// Drains the queue every ~30s (the same tick TrackingTaskHandler already
/// runs for the window self-check — not a second timer). Rejection is not
/// an error: the server always answers 202 regardless of accept/reject
/// mix, so reaching a response at all means the whole batch is done with —
/// delete it. Any failure to even get a response (network, 5xx, ...)
/// leaves the batch queued for the next tick, no error surfaced, matching
/// this app's established resilience pattern (MeRepository's own
/// cache-fallback-on-any-failure). A 401 is the one exception: it means
/// this device's token no longer exists at all, so retrying is pointless —
/// clear the token, tell the main isolate, and stop the service.
class TrackUploadService {
  static const _batchLimit = 500;
  static const _maxPointAge = Duration(hours: 48);

  final LocationQueueRepository _repository;
  final AuthStorage _storage;
  late final ApiClient _apiClient;

  TrackUploadService({
    LocationQueueRepository? repository,
    AuthStorage? storage,
  })  : _repository = repository ?? LocationQueueRepository(),
        _storage = storage ?? AuthStorage() {
    _apiClient = ApiClient(
      baseUrl: apiBaseUrl,
      storage: _storage,
      // A no-op here, deliberately: ApiClient already throws ApiException
      // with isUnauthorized true on a 401, which runUploadCycle below
      // catches and acts on directly. Duplicating the token-clear here too
      // would just race the same clearToken() call against itself.
      onUnauthorized: () async {},
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
    } on ApiException catch (e) {
      if (e.isUnauthorized) {
        await _storage.clearToken();
        FlutterForegroundTask.sendDataToMain(unauthorizedMarker);
        await FlutterForegroundTask.stopService();
      }
      // Any other failure: left queued — see class docblock.
    } catch (_) {
      // Left queued — see class docblock.
    }
  }
}
