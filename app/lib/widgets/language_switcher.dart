import 'package:flutter/material.dart';

import '../l10n/l10n.dart';
import '../state/locale_controller.dart';

class LanguageSwitcher extends StatelessWidget {
  const LanguageSwitcher({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = LocaleControllerScope.maybeOf(context);
    final l10n = context.l10n;

    return Tooltip(
      message: l10n.selectLanguage,
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: controller?.locale.languageCode ??
              Localizations.localeOf(context).languageCode,
          icon: const Icon(Icons.language_outlined),
          borderRadius: BorderRadius.circular(12),
          items: [
            DropdownMenuItem(value: 'en', child: Text(l10n.english)),
            DropdownMenuItem(value: 'ar', child: Text(l10n.arabic)),
          ],
          onChanged: (value) {
            if (value != null) controller?.setLocale(Locale(value));
          },
        ),
      ),
    );
  }
}
