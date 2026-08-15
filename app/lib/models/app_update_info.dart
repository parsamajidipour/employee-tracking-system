class AppUpdateInfo {
  final int versionCode;
  final String versionName;
  final String? releaseNotes;
  final bool isMandatory;
  final int fileSize;
  final String downloadUrl;

  AppUpdateInfo({
    required this.versionCode,
    required this.versionName,
    this.releaseNotes,
    required this.isMandatory,
    required this.fileSize,
    required this.downloadUrl,
  });

  factory AppUpdateInfo.fromJson(Map<String, dynamic> json) => AppUpdateInfo(
        versionCode: json['version_code'] as int,
        versionName: json['version_name'] as String,
        releaseNotes: json['release_notes'] as String?,
        isMandatory: json['is_mandatory'] as bool? ?? false,
        fileSize: json['file_size'] as int? ?? 0,
        downloadUrl: json['download_url'] as String,
      );
}
