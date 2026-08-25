class AppNotification {
  final String id;
  final String type;
  final String title;
  final String message;
  final int? caseId;
  final String? referenceNo;
  final DateTime createdAt;
  final bool read;

  AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    required this.caseId,
    required this.referenceNo,
    required this.createdAt,
    required this.read,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final type = (json['type'] as String?) ?? 'update';

    return AppNotification(
      id: json['id'].toString(),
      type: type,
      title: titleFor(type),
      message: (json['message'] as String?) ?? '',
      caseId: (json['case_id'] as num?)?.toInt(),
      referenceNo: json['reference_no'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String).toLocal(),
      read: json['read_at'] != null,
    );
  }

  static String titleFor(String type) => switch (type) {
        'case.assigned' => 'New case assigned',
        'case.created' => 'New case created',
        'case.status-changed' => 'Case status changed',
        'schedule.changed' => 'Your schedule changed',
        'device.revoked' => 'Device access revoked',
        'app-release.published' => 'App update available',
        _ => 'Update from the office',
      };
}

class NotificationInbox {
  final List<AppNotification> notifications;
  final int unreadCount;

  const NotificationInbox({
    required this.notifications,
    required this.unreadCount,
  });

  static const empty = NotificationInbox(notifications: [], unreadCount: 0);

  factory NotificationInbox.fromJson(Map<String, dynamic> json) =>
      NotificationInbox(
        notifications: ((json['data'] as List<dynamic>?) ?? const [])
            .map((e) => AppNotification.fromJson(e as Map<String, dynamic>))
            .toList(),
        unreadCount: (json['unread_count'] as num?)?.toInt() ?? 0,
      );
}
