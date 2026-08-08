import 'dart:convert';

import '../models/shift_window.dart';
import '../models/window_snapshot.dart';
import '../utils/format.dart';
import 'api_client.dart';
import 'api_exception.dart';
import 'auth_storage.dart';

class MeRepository {
  final ApiClient apiClient;
  final AuthStorage storage;

  MeRepository({required this.apiClient, required this.storage});

  Future<WindowSnapshot> fetchWindow() async {
    try {
      final json = await apiClient.getJson('/api/v1/me/window?date=${formatDateParam(DateTime.now())}');
      await storage.saveCachedWindow(jsonEncode(json));
      return WindowSnapshot(
        response: MeWindowResponse.fromJson(json),
        syncedAt: DateTime.now(),
        stale: false,
      );
    } on ApiException catch (e) {
      if (e.isUnauthorized) rethrow;
      return _cachedSnapshotOrRethrow(e);
    } catch (e) {
      return _cachedSnapshotOrRethrow(e);
    }
  }

  Future<WindowSnapshot> _cachedSnapshotOrRethrow(Object error) async {
    final cached = await storage.cachedWindow();
    if (cached == null) throw error;

    final (json, syncedAt) = cached;
    return WindowSnapshot(
      response: MeWindowResponse.fromJson(jsonDecode(json) as Map<String, dynamic>),
      syncedAt: syncedAt,
      stale: true,
    );
  }
}
