

String formatTime(DateTime dateTime) {
  final local = dateTime.toLocal();
  final hh = local.hour.toString().padLeft(2, '0');
  final mm = local.minute.toString().padLeft(2, '0');
  return '$hh:$mm';
}

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

String formatDuration(Duration duration) {
  if (duration.isNegative || duration.inMinutes < 1) return 'less than a minute';
  if (duration.inMinutes < 60) return '${duration.inMinutes} min';

  final hours = duration.inHours;
  final minutes = duration.inMinutes % 60;
  if (hours < 24) {
    return minutes == 0 ? '${hours}h' : '${hours}h ${minutes}m';
  }

  return '${duration.inDays}d';
}

String formatCountdown(DateTime dateTime, {DateTime? now}) {
  final reference = now ?? DateTime.now();
  final diff = dateTime.difference(reference);
  if (diff.isNegative) return 'now';
  return 'in ${formatDuration(diff)}';
}

String formatRelative(DateTime dateTime, {DateTime? now}) {
  final reference = now ?? DateTime.now();
  final diff = reference.difference(dateTime);

  if (diff.isNegative || diff.inSeconds < 5) return 'just now';
  if (diff.inMinutes < 1) return '${diff.inSeconds}s ago';
  if (diff.inHours < 1) return '${diff.inMinutes}m ago';
  if (diff.inDays < 1) return '${diff.inHours}h ago';
  return formatDateTime(dateTime);
}
