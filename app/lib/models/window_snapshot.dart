import 'shift_window.dart';

/// What the home screen actually renders: a MeWindowResponse plus whether
/// it came fresh off the network just now, or out of the on-device cache
/// because the network attempt failed.
class WindowSnapshot {
  final MeWindowResponse response;
  final DateTime syncedAt;
  final bool stale;

  WindowSnapshot({required this.response, required this.syncedAt, required this.stale});
}
