import 'package:flutter/widgets.dart';

import '../services/auth_storage.dart';
import 'app_localizations.dart';

Future<AppLocalizations> storedLocalizations(AuthStorage storage) async {
  final code = await storage.locale();
  return lookupAppLocalizations(Locale(code == 'ar' ? 'ar' : 'en'));
}
