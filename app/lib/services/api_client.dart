import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_exception.dart';
import 'auth_storage.dart';

/// The one place every HTTP call goes through. Its job beyond plumbing:
/// catch a 401 on an authenticated call and report it upward as exactly
/// that — the device was revoked — never retried, never swallowed. The
/// caller (AuthController) decides what "revoked" means for app state;
/// this class only recognizes the signal.
class ApiClient {
  final String baseUrl;
  final AuthStorage storage;
  final Future<void> Function() onUnauthorized;

  ApiClient({
    required this.baseUrl,
    required this.storage,
    required this.onUnauthorized,
  });

  Future<Map<String, dynamic>> getJson(String path) async {
    final response = await _send('GET', path, authenticated: true);
    return _decode(response);
  }

  /// Used only for /v1/device/login, which is unauthenticated by
  /// definition (it's how a device gets its first token) — never sends a
  /// stored token, and a 401 from it means "wrong credentials", not
  /// "revoked". authenticated:false is what keeps those two 401 meanings
  /// from ever being confused with each other.
  Future<Map<String, dynamic>> postJsonUnauthenticated(
    String path,
    Map<String, dynamic> body,
  ) async {
    final response = await _send('POST', path, body: body, authenticated: false);
    return _decode(response);
  }

  Future<http.Response> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    required bool authenticated,
  }) async {
    final headers = <String, String>{'Accept': 'application/json'};
    if (body != null) headers['Content-Type'] = 'application/json';

    if (authenticated) {
      final token = await storage.token();
      if (token != null) headers['Authorization'] = 'Bearer $token';
    }

    final uri = Uri.parse('$baseUrl$path');
    final response = switch (method) {
      'GET' => await http.get(uri, headers: headers),
      'POST' => await http.post(uri, headers: headers, body: body == null ? null : jsonEncode(body)),
      _ => throw UnsupportedError('Unsupported method $method'),
    };

    if (response.statusCode == 401 && authenticated) {
      await onUnauthorized();
      throw ApiException(401, 'This device was deactivated.');
    }

    return response;
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(response.statusCode, _extractMessage(response));
    }
    if (response.body.isEmpty) return {};
    return jsonDecode(response.body) as Map<String, dynamic>;
  }

  String _extractMessage(http.Response response) {
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map && decoded['message'] is String) {
        return decoded['message'] as String;
      }
    } catch (_) {
      // Body wasn't JSON (e.g. an HTML error page from a misconfigured
      // base URL) — fall through to the generic message below.
    }
    return 'Something went wrong (${response.statusCode}).';
  }
}
