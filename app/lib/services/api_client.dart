import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:mime/mime.dart';
import 'package:path/path.dart' as path_util;

import 'api_exception.dart';
import 'auth_storage.dart';

const _requestTimeout = Duration(seconds: 15);
const _uploadTimeout = Duration(minutes: 2);

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

  Future<List<dynamic>> getJsonList(String path) async {
    final response = await _send('GET', path, authenticated: true);
    final decoded = _decodeRaw(response);
    return decoded is List<dynamic> ? decoded : <dynamic>[];
  }

  Future<Map<String, dynamic>> getJsonUnauthenticated(String path) async {
    final response = await _send('GET', path, authenticated: false);
    return _decode(response);
  }

  Future<Map<String, dynamic>> postJson(
      String path, Map<String, dynamic> body) async {
    final response = await _send('POST', path, body: body, authenticated: true);
    return _decode(response);
  }

  Future<Map<String, dynamic>> postJsonUnauthenticated(
    String path,
    Map<String, dynamic> body,
  ) async {
    final response =
        await _send('POST', path, body: body, authenticated: false);
    return _decode(response);
  }

  Future<String?> authorizeChannel(String socketId, String channelName) async {
    try {
      final headers = <String, String>{
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      };
      final token = await storage.token();
      if (token != null) headers['Authorization'] = 'Bearer $token';

      final response = await http
          .post(
            Uri.parse('$baseUrl/broadcasting/auth'),
            headers: headers,
            body: jsonEncode({
              'socket_id': socketId,
              'channel_name': channelName,
            }),
          )
          .timeout(_requestTimeout);

      if (response.statusCode < 200 || response.statusCode >= 300) return null;

      final decoded = jsonDecode(response.body);
      return decoded is Map && decoded['auth'] is String
          ? decoded['auth'] as String
          : null;
    } catch (_) {
      return null;
    }
  }

  Future<Map<String, dynamic>> postMultipart(
    String path,
    Map<String, String> fields, {
    required String filePath,
    required String fileField,
  }) async {
    final headers = <String, String>{'Accept': 'application/json'};
    final token = await storage.token();
    if (token != null) headers['Authorization'] = 'Bearer $token';

    final uri = Uri.parse('$baseUrl$path');
    final mimeType = lookupMimeType(filePath) ?? 'image/jpeg';
    final request = http.MultipartRequest('POST', uri)
      ..headers.addAll(headers)
      ..fields.addAll(fields)
      ..files.add(await http.MultipartFile.fromPath(
        fileField,
        filePath,
        filename: path_util.basename(filePath),
        contentType: MediaType.parse(mimeType),
      ));

    final streamedResponse = await request.send().timeout(_uploadTimeout);
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode == 401) {
      await onUnauthorized();
      throw ApiException(401, 'This device was deactivated.');
    }

    return _decode(response);
  }

  Future<List<int>> getBytes(String urlOrPath) async {
    final headers = <String, String>{'Accept': '*/*'};
    final token = await storage.token();
    if (token != null) headers['Authorization'] = 'Bearer $token';

    final uri = urlOrPath.startsWith('http')
        ? Uri.parse(urlOrPath)
        : Uri.parse('$baseUrl$urlOrPath');

    final response =
        await http.get(uri, headers: headers).timeout(_requestTimeout);

    if (response.statusCode == 401) {
      await onUnauthorized();
      throw ApiException(401, 'This device was deactivated.');
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(response.statusCode, _extractMessage(response));
    }

    return response.bodyBytes;
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
    final response = await switch (method) {
      'GET' => http.get(uri, headers: headers),
      'POST' => http.post(uri,
          headers: headers, body: body == null ? null : jsonEncode(body)),
      _ => throw UnsupportedError('Unsupported method $method'),
    }
        .timeout(_requestTimeout);

    if (response.statusCode == 401 && authenticated) {
      await onUnauthorized();
      throw ApiException(401, 'This device was deactivated.');
    }

    return response;
  }

  Map<String, dynamic> _decode(http.Response response) {
    final decoded = _decodeRaw(response);
    return decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
  }

  dynamic _decodeRaw(http.Response response) {
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(response.statusCode, _extractMessage(response));
    }
    if (response.body.isEmpty) return <String, dynamic>{};
    return jsonDecode(response.body);
  }

  String _extractMessage(http.Response response) {
    try {
      final decoded = jsonDecode(response.body);
      if (decoded is Map && decoded['message'] is String) {
        return decoded['message'] as String;
      }
    } catch (_) {}
    return 'Something went wrong (${response.statusCode}).';
  }
}
