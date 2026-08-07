import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

/// Everything persisted on-device. flutter_secure_storage only — never
/// SharedPreferences, even for the non-sensitive cached-window fields
/// below, so there's exactly one storage mechanism to reason about rather
/// than a sensitive one and a casual one.
class AuthStorage {
  static const _deviceIdentifierKey = 'device_identifier';
  static const _tokenKey = 'auth_token';
  static const _cachedWindowKey = 'cached_window_json';
  static const _cachedWindowSyncedAtKey = 'cached_window_synced_at';

  final FlutterSecureStorage _storage;

  AuthStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  /// Generated once, the first time the app ever needs it, and never
  /// regenerated after — including across a revoke-then-log-back-in on the
  /// same physical device, which the server treats as a fresh device row
  /// but the same device_identifier value; nothing about this value is
  /// tied to any one login session.
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

  /// Only the token is cleared on logout/revoke — never the device
  /// identifier (see deviceIdentifier() above) and never the cached window
  /// (harmless stale data, overwritten on the next successful fetch).
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
}
