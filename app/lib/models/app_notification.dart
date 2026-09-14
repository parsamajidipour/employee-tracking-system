import '../l10n/app_localizations.dart';

class AppNotification {
  final String id;
  final String type;
  final String message;
  final int? caseId;
  final String? referenceNo;
  final int? versionCode;
  final String? versionName;
  final bool isMandatoryUpdate;
  final DateTime createdAt;
  final bool read;

  AppNotification({
    required this.id,
    required this.type,
    required this.message,
    required this.caseId,
    required this.referenceNo,
    required this.versionCode,
    required this.versionName,
    required this.isMandatoryUpdate,
    required this.createdAt,
    required this.read,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final type = (json['type'] as String?) ?? 'update';

    return AppNotification(
      id: json['id'].toString(),
      type: type,
      message: (json['message'] as String?) ?? '',
      caseId: (json['case_id'] as num?)?.toInt(),
      referenceNo: json['reference_no'] as String?,
      versionCode: (json['version_code'] as num?)?.toInt(),
      versionName: json['version_name'] as String?,
      isMandatoryUpdate: json['is_mandatory'] as bool? ?? false,
      createdAt: DateTime.parse(json['created_at'] as String).toLocal(),
      read: json['read_at'] != null,
    );
  }

  Map<String, dynamic> toPayload() => {
        'notification_id': id,
        'type': type,
        if (caseId != null) 'case_id': caseId,
        if (versionCode != null) 'version_code': versionCode,
      };

  String localizedTitle(AppLocalizations l10n) => switch (type) {
        'case.assigned' => l10n.newCaseAssigned,
        'case.created' => l10n.newCaseCreated,
        'case.status-changed' => l10n.caseStatusChanged,
        'schedule.changed' => l10n.scheduleChanged,
        'device.revoked' => l10n.deviceAccessRevoked,
        'app-release.published' => l10n.appUpdateAvailable,
        _ => l10n.officeUpdate,
      };
}

class NotificationInbox {
  final List<AppNotification> notifications;
  final int unreadCount;
  final Set<int> visibleCaseIds;

  const NotificationInbox({
    required this.notifications,
    required this.unreadCount,
    required this.visibleCaseIds,
  });

  static const empty = NotificationInbox(
    notifications: [],
    unreadCount: 0,
    visibleCaseIds: {},
  );

  factory NotificationInbox.fromJson(Map<String, dynamic> json) =>
      NotificationInbox(
        notifications: ((json['data'] as List<dynamic>?) ?? const [])
            .map((e) => AppNotification.fromJson(e as Map<String, dynamic>))
            .toList(),
        unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
        visibleCaseIds:
            ((json['visible_case_ids'] as List<dynamic>?) ?? const [])
                .map((id) => (id as num).toInt())
                .toSet(),
      );
}
