import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

class AuthStorage {
  static const _deviceIdentifierKey = 'device_identifier';
  static const _tokenKey = 'auth_token';
  static const _cachedWindowKey = 'cached_window_json';
  static const _cachedWindowSyncedAtKey = 'cached_window_synced_at';
  static const _onboardingCompletedKey = 'permission_onboarding_completed';
  static const _lastUploadAtKey = 'last_upload_at';
  static const _backgroundNotifiedNotificationsKey =
      'background_notified_notifications';

  final FlutterSecureStorage _storage;

  AuthStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  Future<String> deviceIdentifier() async {
    final existing = await _storage.read(key: _deviceIdentifierKey);
    if (existing != null && existing.isNotEmpty) return existing;

    final generated = const Uuid().v4();
    await _storage.write(key: _deviceIdentifierKey, value: generated);
    return generated;
  }

  Future<String?> token() => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<void> saveCachedWindow(String json) async {
    await _storage.write(key: _cachedWindowKey, value: json);
    await _storage.write(
      key: _cachedWindowSyncedAtKey,
      value: DateTime.now().toIso8601String(),
    );
  }

  Future<(String json, DateTime syncedAt)?> cachedWindow() async {
    final json = await _storage.read(key: _cachedWindowKey);
    final syncedAtRaw = await _storage.read(key: _cachedWindowSyncedAtKey);
    if (json == null || syncedAtRaw == null) return null;
    return (json, DateTime.parse(syncedAtRaw));
  }

  Future<bool> onboardingCompleted() async =>
      await _storage.read(key: _onboardingCompletedKey) == 'true';

  Future<void> markOnboardingCompleted() =>
      _storage.write(key: _onboardingCompletedKey, value: 'true');

  Future<void> saveLastUploadAt(DateTime dateTime) =>
      _storage.write(key: _lastUploadAtKey, value: dateTime.toIso8601String());

  Future<DateTime?> lastUploadAt() async {
    final raw = await _storage.read(key: _lastUploadAtKey);
    return raw == null ? null : DateTime.parse(raw);
  }

  Future<Set<String>> backgroundNotifiedNotifications() async {
    final raw = await _storage.read(key: _backgroundNotifiedNotificationsKey);
    if (raw == null) return <String>{};

    try {
      final decoded = jsonDecode(raw);
      if (decoded is List<dynamic>) {
        return decoded.whereType<String>().toSet();
      }
    } catch (_) {}

    return <String>{};
  }

  Future<void> saveBackgroundNotifiedNotifications(Set<String> ids) {
    final recent = ids.take(100).toList(growable: false);
    return _storage.write(
      key: _backgroundNotifiedNotificationsKey,
      value: jsonEncode(recent),
    );
  }
}
