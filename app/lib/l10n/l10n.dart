import 'package:flutter/widgets.dart';

import 'app_localizations.dart';

extension AppLocalizationsContext on BuildContext {
  AppLocalizations get l10n =>
      Localizations.of<AppLocalizations>(this, AppLocalizations) ??
      lookupAppLocalizations(
          Localizations.maybeLocaleOf(this) ?? const Locale('en'));
}
