/// Mirrors App\ValueObjects\ShiftWindow::toApiArray() on the server exactly
/// — start/end are already grace-adjusted ("effective" times), and there
/// is deliberately no client-side window math anywhere in this app (see
/// CLAUDE.md invariant 8: one resolver, never a second implementation).
/// This class only ever displays what the server already decided.
class ShiftWindow {
  final DateTime start;
  final DateTime end;
  final String source;

  ShiftWindow({required this.start, required this.end, required this.source});

  factory ShiftWindow.fromJson(Map<String, dynamic> json) => ShiftWindow(
        start: DateTime.parse(json['start'] as String),
        end: DateTime.parse(json['end'] as String),
        source: json['source'] as String,
      );

  Map<String, dynamic> toJson() => {
        'start': start.toIso8601String(),
        'end': end.toIso8601String(),
        'source': source,
      };
}

/// GET /api/v1/me/window's whole response body.
class MeWindowResponse {
  final ShiftWindow? current;
  final ShiftWindow? next;
  final DateTime serverTime;

  MeWindowResponse({this.current, this.next, required this.serverTime});

  factory MeWindowResponse.fromJson(Map<String, dynamic> json) => MeWindowResponse(
        current: json['current'] == null
            ? null
            : ShiftWindow.fromJson(json['current'] as Map<String, dynamic>),
        next: json['next'] == null
            ? null
            : ShiftWindow.fromJson(json['next'] as Map<String, dynamic>),
        serverTime: DateTime.parse(json['server_time'] as String),
      );

  Map<String, dynamic> toJson() => {
        'current': current?.toJson(),
        'next': next?.toJson(),
        'server_time': serverTime.toIso8601String(),
      };
}
