import 'shift_window.dart';

class WindowSnapshot {
  final MeWindowResponse response;
  final DateTime syncedAt;
  final bool stale;

  WindowSnapshot({required this.response, required this.syncedAt, required this.stale});
}
