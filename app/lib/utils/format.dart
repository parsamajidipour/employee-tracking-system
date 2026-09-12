import 'package:intl/intl.dart';

import '../l10n/app_localizations.dart';

String formatTime(DateTime dateTime, {String locale = 'en'}) {
  return DateFormat.Hm(locale).format(dateTime.toLocal());
}

String formatDateParam(DateTime dateTime) {
  final y = dateTime.year.toString().padLeft(4, '0');
  final m = dateTime.month.toString().padLeft(2, '0');
  final d = dateTime.day.toString().padLeft(2, '0');
  return '$y-$m-$d';
}

String formatDateTime(DateTime dateTime, {String locale = 'en'}) {
  return DateFormat.yMd(locale).add_Hm().format(dateTime.toLocal());
}

String formatRelative(
  DateTime dateTime,
  AppLocalizations l10n, {
  DateTime? now,
}) {
  final reference = now ?? DateTime.now();
  final diff = reference.difference(dateTime);
  final number = NumberFormat.decimalPattern(l10n.localeName);

  if (diff.isNegative || diff.inSeconds < 5) return l10n.justNow;
  if (diff.inMinutes < 1) return l10n.secondsAgo(number.format(diff.inSeconds));
  if (diff.inHours < 1) return l10n.minutesAgo(number.format(diff.inMinutes));
  if (diff.inDays < 1) return l10n.hoursAgo(number.format(diff.inHours));
  return formatDateTime(dateTime, locale: l10n.localeName);
}
