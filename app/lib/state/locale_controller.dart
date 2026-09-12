import 'package:flutter/material.dart';

import '../services/auth_storage.dart';

class LocaleController extends ChangeNotifier {
  LocaleController({AuthStorage? storage})
      : _storage = storage ?? AuthStorage();

  final AuthStorage _storage;
  Locale _locale = const Locale('en');

  Locale get locale => _locale;

  Future<void> initialize() async {
    final code = await _storage.locale();
    _locale = Locale(code == 'ar' ? 'ar' : 'en');
    notifyListeners();
  }

  Future<void> setLocale(Locale locale) async {
    final normalized =
        locale.languageCode == 'ar' ? const Locale('ar') : const Locale('en');
    if (_locale == normalized) return;
    _locale = normalized;
    notifyListeners();
    await _storage.saveLocale(normalized.languageCode);
  }
}

class LocaleControllerScope extends InheritedNotifier<LocaleController> {
  const LocaleControllerScope({
    super.key,
    required LocaleController controller,
    required super.child,
  }) : super(notifier: controller);

  static LocaleController of(BuildContext context) {
    return context
        .dependOnInheritedWidgetOfExactType<LocaleControllerScope>()!
        .notifier!;
  }

  static LocaleController? maybeOf(BuildContext context) {
    return context
        .dependOnInheritedWidgetOfExactType<LocaleControllerScope>()
        ?.notifier;
  }
}
