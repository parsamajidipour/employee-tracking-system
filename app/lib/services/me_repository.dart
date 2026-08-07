import 'dart:convert';

import '../models/shift_window.dart';
import '../models/window_snapshot.dart';
import 'api_client.dart';
import 'api_exception.dart';
import 'auth_storage.dart';

/// GET /me/window, with the offline fallback SPEC section 6 calls for: if
/// the network attempt fails for any reason other than the device having
/// been revoked, fall back to the last successfully-fetched response
/// rather than showing nothing. A 401 is never treated this way — that
/// means the session itself is gone, not that the network is flaky, and
/// showing stale "you're fine" data right after that would defeat the
/// point of the deactivation notice.
class MeRepository {
  final ApiClient apiClient;
  final AuthStorage storage;

  MeRepository({required this.apiClient, required this.storage});

  Future<WindowSnapshot> fetchWindow() async {
    try {
      final json = await apiClient.getJson('/api/v1/me/window?date=${_todayDateParam()}');
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

  String _todayDateParam() {
    final now = DateTime.now();
    final y = now.year.toString().padLeft(4, '0');
    final m = now.month.toString().padLeft(2, '0');
    final d = now.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }
}
