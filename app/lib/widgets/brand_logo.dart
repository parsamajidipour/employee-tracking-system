import 'package:flutter/material.dart';

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
      semanticLabel: 'Smart Inspection',
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
        Text('Smart Inspection', style: context.text.titleLarge),
        const SizedBox(height: AppSpacing.xs),
        Text(
          'Working hours tracking',
          style: context.text.bodyMedium,
        ),
      ],
    );
  }
}
