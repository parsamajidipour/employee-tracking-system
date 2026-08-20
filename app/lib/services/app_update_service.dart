import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';

import '../models/app_update_info.dart';
import 'api_client.dart';

class AppUpdateService {
  final ApiClient apiClient;

  AppUpdateService({required this.apiClient});

  Future<AppUpdateInfo?> checkForUpdate() async {
    try {
      final packageInfo = await PackageInfo.fromPlatform();
      final installedBuild = int.tryParse(packageInfo.buildNumber) ?? 0;

      final json = await apiClient.getJsonUnauthenticated('/api/v1/app/latest-version');
      final info = AppUpdateInfo.fromJson(json);

      if (info.versionCode <= installedBuild) return null;
      return info;
    } catch (_) {
      return null;
    }
  }

  Future<File> downloadApk(AppUpdateInfo info, {void Function(double progress)? onProgress}) async {
    final request = http.Request('GET', Uri.parse(info.downloadUrl));
    final response = await http.Client().send(request);

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw Exception('Download failed (${response.statusCode}).');
    }

    final total = response.contentLength;
    var received = 0;

    final directory = await getTemporaryDirectory();
    final file = File('${directory.path}/smart-inspection-${info.versionName}.apk');
    final sink = file.openWrite();

    await response.stream.map((chunk) {
      received += chunk.length;
      if (total != null && total > 0) {
        onProgress?.call(received / total);
      }
      return chunk;
    }).pipe(sink);
    await sink.close();

    return file;
  }
}
