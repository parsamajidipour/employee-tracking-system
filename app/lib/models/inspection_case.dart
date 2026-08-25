DateTime? _parseNullableDate(dynamic value) =>
    value == null ? null : DateTime.parse(value as String);

class CaseStatusEvent {
  final int id;
  final String actorName;
  final String? fromStatus;
  final String toStatus;
  final String? note;
  final DateTime createdAt;

  CaseStatusEvent({
    required this.id,
    required this.actorName,
    required this.fromStatus,
    required this.toStatus,
    required this.note,
    required this.createdAt,
  });

  factory CaseStatusEvent.fromJson(Map<String, dynamic> json) =>
      CaseStatusEvent(
        id: json['id'] as int,
        actorName: json['actor_name'] as String? ?? 'System',
        fromStatus: json['from_status'] as String?,
        toStatus: json['to_status'] as String,
        note: json['note'] as String?,
        createdAt: DateTime.parse(json['created_at'] as String),
      );
}

class CasePhoto {
  final int id;
  final String url;
  final bool isGpsVerified;
  final double? distanceFromCaseM;
  final DateTime capturedAt;

  CasePhoto({
    required this.id,
    required this.url,
    required this.isGpsVerified,
    required this.distanceFromCaseM,
    required this.capturedAt,
  });

  factory CasePhoto.fromJson(Map<String, dynamic> json) => CasePhoto(
        id: json['id'] as int,
        url: json['url'] as String,
        isGpsVerified: json['is_gps_verified'] as bool,
        distanceFromCaseM: (json['distance_from_case_m'] as num?)?.toDouble(),
        capturedAt: DateTime.parse(json['captured_at'] as String),
      );
}

class InspectionCase {
  final int id;
  final String referenceNo;
  final String title;
  final String propertyAddress;
  final double lat;
  final double lng;
  final String status;
  final String priority;
  final int? assignedTo;
  final String? assigneeName;
  final DateTime? assignedAt;
  final DateTime? acceptedAt;
  final DateTime? plannedAt;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final String? notes;
  final DateTime createdAt;
  final List<CaseStatusEvent>? statusEvents;
  final List<CasePhoto>? photos;

  InspectionCase({
    required this.id,
    required this.referenceNo,
    required this.title,
    required this.propertyAddress,
    required this.lat,
    required this.lng,
    required this.status,
    required this.priority,
    required this.assignedTo,
    required this.assigneeName,
    required this.assignedAt,
    required this.acceptedAt,
    required this.plannedAt,
    required this.startedAt,
    required this.completedAt,
    required this.notes,
    required this.createdAt,
    this.statusEvents,
    this.photos,
  });

  factory InspectionCase.fromJson(Map<String, dynamic> json) => InspectionCase(
        id: json['id'] as int,
        referenceNo: json['reference_no'] as String,
        title: json['title'] as String,
        propertyAddress: json['property_address'] as String? ??
            'Property address not provided',
        lat: (json['lat'] as num).toDouble(),
        lng: (json['lng'] as num).toDouble(),
        status: json['status'] as String,
        priority: json['priority'] as String,
        assignedTo: json['assigned_to'] as int?,
        assigneeName: json['assignee_name'] as String?,
        assignedAt: _parseNullableDate(json['assigned_at']),
        acceptedAt: _parseNullableDate(json['accepted_at']),
        plannedAt: _parseNullableDate(json['planned_at']),
        startedAt: _parseNullableDate(json['started_at']),
        completedAt: _parseNullableDate(json['completed_at']),
        notes: json['notes'] as String?,
        createdAt: DateTime.parse(json['created_at'] as String),
        statusEvents: (json['status_events'] as List<dynamic>?)
            ?.map((e) => CaseStatusEvent.fromJson(e as Map<String, dynamic>))
            .toList(),
        photos: (json['photos'] as List<dynamic>?)
            ?.map((e) => CasePhoto.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class CaseUnseenCount {
  final int pending;
  final int unreadNotifications;

  CaseUnseenCount({required this.pending, required this.unreadNotifications});

  factory CaseUnseenCount.fromJson(Map<String, dynamic> json) =>
      CaseUnseenCount(
        pending: json['pending'] as int,
        unreadNotifications: json['unread_notifications'] as int,
      );
}
