import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_exception.dart';
import 'auth_storage.dart';

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

  Future<Map<String, dynamic>> postJson(String path, Map<String, dynamic> body) async {
    final response = await _send('POST', path, body: body, authenticated: true);
    return _decode(response);
  }

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
    }
    return 'Something went wrong (${response.statusCode}).';
  }
}
