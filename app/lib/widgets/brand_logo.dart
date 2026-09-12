import 'package:flutter/material.dart';

import '../l10n/l10n.dart';
import '../theme/app_theme.dart';

class BrandLogo extends StatelessWidget {
  const BrandLogo({super.key, this.size = 72});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Image.asset(
      'assets/brand/logo.png',
      width: size,
      height: size,
      filterQuality: FilterQuality.medium,
      semanticLabel: context.l10n.appName,
    );
  }
}

class BrandWordmark extends StatelessWidget {
  const BrandWordmark({super.key, this.logoSize = 56});

  final double logoSize;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        BrandLogo(size: logoSize),
        const SizedBox(height: AppSpacing.md),
        Text(context.l10n.appName, style: context.text.titleLarge),
        const SizedBox(height: AppSpacing.xs),
        Text(
          context.l10n.workingHoursTracking,
          style: context.text.bodyMedium,
        ),
      ],
    );
  }
}
