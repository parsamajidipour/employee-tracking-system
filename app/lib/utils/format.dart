/// No `intl` dependency for two small formatters — hand-rolled padding is
/// simpler than pulling in a whole i18n package for this.
String formatTime(DateTime dateTime) {
  final local = dateTime.toLocal();
  final hh = local.hour.toString().padLeft(2, '0');
  final mm = local.minute.toString().padLeft(2, '0');
  return '$hh:$mm';
}

/// "yyyy-MM-dd" — the ?date= query param shape both MeRepository and
/// window_sync_service send, extracted here so the two don't each hand-roll
/// the same padding logic.
String formatDateParam(DateTime dateTime) {
  final y = dateTime.year.toString().padLeft(4, '0');
  final m = dateTime.month.toString().padLeft(2, '0');
  final d = dateTime.day.toString().padLeft(2, '0');
  return '$y-$m-$d';
}

String formatDateTime(DateTime dateTime) {
  final local = dateTime.toLocal();
  final dd = local.day.toString().padLeft(2, '0');
  final mo = local.month.toString().padLeft(2, '0');
  return '$dd/$mo ${formatTime(local)}';
}

/// "3m ago" / "1h ago" style, falling back to a full date once it's more
/// than a day old — exactly the kind of detail that matters for "last
/// synced", where the whole point is making staleness legible at a glance.
String formatRelative(DateTime dateTime, {DateTime? now}) {
  final reference = now ?? DateTime.now();
  final diff = reference.difference(dateTime);

  if (diff.isNegative || diff.inSeconds < 5) return 'just now';
  if (diff.inMinutes < 1) return '${diff.inSeconds}s ago';
  if (diff.inHours < 1) return '${diff.inMinutes}m ago';
  if (diff.inDays < 1) return '${diff.inHours}h ago';
  return formatDateTime(dateTime);
}
