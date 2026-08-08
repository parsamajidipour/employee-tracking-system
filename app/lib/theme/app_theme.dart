import 'package:flutter/material.dart';

class AppColors {
  const AppColors({
    required this.primary,
    required this.primaryStrong,
    required this.primarySoft,
    required this.background,
    required this.surface,
    required this.surfaceMuted,
    required this.border,
    required this.textPrimary,
    required this.textSecondary,
    required this.textTertiary,
    required this.success,
    required this.warning,
    required this.danger,
    required this.neutral,
  });

  final Color primary;
  final Color primaryStrong;
  final Color primarySoft;
  final Color background;
  final Color surface;
  final Color surfaceMuted;
  final Color border;
  final Color textPrimary;
  final Color textSecondary;
  final Color textTertiary;
  final Color success;
  final Color warning;
  final Color danger;
  final Color neutral;

  static const light = AppColors(
    primary: Color(0xFF2F9EC0),
    primaryStrong: Color(0xFF1E7F9B),
    primarySoft: Color(0xFFDCF0F6),
    background: Color(0xFFEFF5F8),
    surface: Color(0xFFFFFFFF),
    surfaceMuted: Color(0xFFF6FAFC),
    border: Color(0xFFE2ECF1),
    textPrimary: Color(0xFF13333F),
    textSecondary: Color(0xFF6E8C99),
    textTertiary: Color(0xFF9DB6C1),
    success: Color(0xFF3FA98A),
    warning: Color(0xFFD9973B),
    danger: Color(0xFFD2635E),
    neutral: Color(0xFF8AA5B1),
  );

  static const dark = AppColors(
    primary: Color(0xFF4FBDD8),
    primaryStrong: Color(0xFF2F9EC0),
    primarySoft: Color(0xFF12323D),
    background: Color(0xFF0E1E26),
    surface: Color(0xFF162C36),
    surfaceMuted: Color(0xFF1B333E),
    border: Color(0xFF22404C),
    textPrimary: Color(0xFFEAF4F8),
    textSecondary: Color(0xFF9DB6C1),
    textTertiary: Color(0xFF6E8C99),
    success: Color(0xFF57C3A3),
    warning: Color(0xFFE8AF5C),
    danger: Color(0xFFE2807B),
    neutral: Color(0xFF7E9AA6),
  );

  static const gradientTop = Color(0xFF4FBDD8);
  static const gradientBottom = Color(0xFF228DAD);

  static const brandGradient = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: [gradientTop, gradientBottom],
  );
}

class AppSpacing {
  const AppSpacing._();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
  static const double huge = 40;

  static const double screen = 20;
  static const double card = 20;
  static const double cardGap = 16;
}

class AppRadii {
  const AppRadii._();

  static const double card = 24;
  static const double control = 16;
  static const double small = 12;
  static const double pill = 999;

  static const cardRadius = BorderRadius.all(Radius.circular(card));
  static const controlRadius = BorderRadius.all(Radius.circular(control));
  static const smallRadius = BorderRadius.all(Radius.circular(small));
  static const pillRadius = BorderRadius.all(Radius.circular(pill));
}

class AppShadows {
  const AppShadows._();

  static const card = <BoxShadow>[
    BoxShadow(
      color: Color(0x0F13333F),
      blurRadius: 24,
      offset: Offset(0, 8),
    ),
  ];

  static const raised = <BoxShadow>[
    BoxShadow(
      color: Color(0x1A13333F),
      blurRadius: 32,
      offset: Offset(0, 12),
    ),
  ];

  static const none = <BoxShadow>[];
}

class AppDurations {
  const AppDurations._();

  static const fast = Duration(milliseconds: 150);
  static const base = Duration(milliseconds: 220);
  static const slow = Duration(milliseconds: 320);

  static const stagger = Duration(milliseconds: 40);
  static const maxStaggeredItems = 6;
}

class AppTheme {
  const AppTheme._();

  static ThemeData light() => _build(AppColors.light, Brightness.light);

  static ThemeData dark() => _build(AppColors.dark, Brightness.dark);

  static ThemeData _build(AppColors c, Brightness brightness) {
    final scheme = ColorScheme(
      brightness: brightness,
      primary: c.primary,
      onPrimary: Colors.white,
      primaryContainer: c.primarySoft,
      onPrimaryContainer: c.primaryStrong,
      secondary: c.primaryStrong,
      onSecondary: Colors.white,
      error: c.danger,
      onError: Colors.white,
      surface: c.surface,
      onSurface: c.textPrimary,
      surfaceContainerLowest: c.surface,
      surfaceContainerLow: c.surfaceMuted,
      surfaceContainer: c.surfaceMuted,
      outline: c.border,
      outlineVariant: c.border,
    );

    final text = _textTheme(c);

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: c.background,
      splashFactory: InkSparkle.splashFactory,
      textTheme: text,
      extensions: <ThemeExtension<dynamic>>[AppPalette(c)],
      appBarTheme: AppBarTheme(
        backgroundColor: c.background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: text.titleLarge,
        iconTheme: IconThemeData(color: c.textPrimary, size: 24),
      ),
      cardTheme: CardThemeData(
        color: c.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: const RoundedRectangleBorder(borderRadius: AppRadii.cardRadius),
      ),
      dividerTheme: DividerThemeData(color: c.border, thickness: 1, space: 1),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: c.primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: c.border,
          disabledForegroundColor: c.textTertiary,
          minimumSize: const Size.fromHeight(52),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xl),
          elevation: 0,
          textStyle: text.labelLarge,
          shape: const RoundedRectangleBorder(
            borderRadius: AppRadii.controlRadius,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: c.primaryStrong,
          minimumSize: const Size.fromHeight(52),
          side: BorderSide(color: c.border),
          textStyle: text.labelLarge,
          shape: const RoundedRectangleBorder(
            borderRadius: AppRadii.controlRadius,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: c.primaryStrong,
          minimumSize: const Size(44, 44),
          textStyle: text.labelLarge,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: c.surfaceMuted,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.lg,
        ),
        hintStyle: text.bodyMedium?.copyWith(color: c.textTertiary),
        labelStyle: text.labelMedium?.copyWith(color: c.textSecondary),
        border: OutlineInputBorder(
          borderRadius: AppRadii.controlRadius,
          borderSide: BorderSide(color: c.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadii.controlRadius,
          borderSide: BorderSide(color: c.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: AppRadii.controlRadius,
          borderSide: BorderSide(color: c.primary, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: AppRadii.controlRadius,
          borderSide: BorderSide(color: c.danger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: AppRadii.controlRadius,
          borderSide: BorderSide(color: c.danger, width: 1.6),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: c.textPrimary,
        contentTextStyle: text.bodyMedium?.copyWith(color: c.surface),
        behavior: SnackBarBehavior.floating,
        shape: const RoundedRectangleBorder(
          borderRadius: AppRadii.controlRadius,
        ),
      ),
    );
  }

  static TextTheme _textTheme(AppColors c) {
    return TextTheme(
      displaySmall: TextStyle(
        fontSize: 32,
        height: 38 / 32,
        fontWeight: FontWeight.w700,
        color: c.textPrimary,
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
      titleLarge: TextStyle(
        fontSize: 22,
        height: 28 / 22,
        fontWeight: FontWeight.w700,
        color: c.textPrimary,
      ),
      titleMedium: TextStyle(
        fontSize: 17,
        height: 22 / 17,
        fontWeight: FontWeight.w600,
        color: c.textPrimary,
      ),
      bodyLarge: TextStyle(
        fontSize: 15,
        height: 21 / 15,
        fontWeight: FontWeight.w400,
        color: c.textPrimary,
      ),
      bodyMedium: TextStyle(
        fontSize: 15,
        height: 21 / 15,
        fontWeight: FontWeight.w400,
        color: c.textSecondary,
      ),
      labelLarge: const TextStyle(
        fontSize: 15,
        height: 20 / 15,
        fontWeight: FontWeight.w600,
      ),
      labelMedium: TextStyle(
        fontSize: 13,
        height: 17 / 13,
        fontWeight: FontWeight.w500,
        color: c.textSecondary,
      ),
      labelSmall: TextStyle(
        fontSize: 11,
        height: 14 / 11,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.8,
        color: c.textSecondary,
      ),
      bodySmall: TextStyle(
        fontSize: 12,
        height: 16 / 12,
        fontWeight: FontWeight.w500,
        color: c.textSecondary,
        fontFeatures: const [FontFeature.tabularFigures()],
      ),
    );
  }
}

class AppPalette extends ThemeExtension<AppPalette> {
  const AppPalette(this.colors);

  final AppColors colors;

  @override
  AppPalette copyWith({AppColors? colors}) => AppPalette(colors ?? this.colors);

  @override
  AppPalette lerp(ThemeExtension<AppPalette>? other, double t) {
    if (other is! AppPalette) {
      return this;
    }
    return t < 0.5 ? this : other;
  }
}

extension AppThemeContext on BuildContext {
  AppColors get colors =>
      Theme.of(this).extension<AppPalette>()?.colors ?? AppColors.light;

  TextTheme get text => Theme.of(this).textTheme;

  bool get reduceMotion => MediaQuery.maybeDisableAnimationsOf(this) ?? false;

  Duration motion(Duration duration) =>
      reduceMotion ? Duration.zero : duration;
}
