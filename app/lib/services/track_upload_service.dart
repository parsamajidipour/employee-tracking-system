import 'package:flutter_foreground_task/flutter_foreground_task.dart';

import '../config.dart';
import 'api_client.dart';
import 'api_exception.dart';
import 'auth_storage.dart';
import 'location_queue_repository.dart';

const unauthorizedMarker = '__unauthorized__';

class TrackUploadService {
  static const _batchLimit = 500;
  static const _maxPointAge = Duration(hours: 48);

  final LocationQueueRepository _repository;
  final AuthStorage _storage;
  late final ApiClient _apiClient;
  bool _running = false;

  TrackUploadService({
    LocationQueueRepository? repository,
    AuthStorage? storage,
  })  : _repository = repository ?? LocationQueueRepository(),
        _storage = storage ?? AuthStorage() {
    _apiClient = ApiClient(
      baseUrl: apiBaseUrl,
      storage: _storage,

      onUnauthorized: () async {},
    );
  }

  Future<void> runUploadCycle() async {
    if (_running) return;
    _running = true;

    try {
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
      } catch (_) {
      }
    } finally {
      _running = false;
    }
  }
}
