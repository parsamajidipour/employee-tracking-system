import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

enum TrackingDisplayState { active, off, unknownOffline }

class TrackingStatusBanner extends StatelessWidget {
  const TrackingStatusBanner({super.key, required this.state});

  final TrackingDisplayState state;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    final (tint, icon, title, subtitle) = switch (state) {
      TrackingDisplayState.active => (
          colors.success,
          Icons.location_on_outlined,
          'Tracking active',
          'You are inside your working-hours window.',
        ),
      TrackingDisplayState.off => (
          colors.neutral,
          Icons.location_off_outlined,
          'Tracking off',
          'Outside working hours. No location is being recorded.',
        ),
      TrackingDisplayState.unknownOffline => (
          colors.warning,
          Icons.wifi_off_outlined,
          'Tracking state unknown',
          'Offline. Cannot confirm whether tracking is on right now.',
        ),
    };

    return AnimatedContainer(
      duration: context.motion(AppDurations.base),
      curve: Curves.easeOutCubic,
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.card),
      decoration: BoxDecoration(
        color: tint.withValues(alpha: 0.10),
        borderRadius: AppRadii.cardRadius,
        border: Border.all(color: tint.withValues(alpha: 0.22)),
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: tint.withValues(alpha: 0.16),
              borderRadius: AppRadii.controlRadius,
            ),
            child: Icon(icon, color: tint, size: 24),
          ),
          const SizedBox(width: AppSpacing.lg),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: context.text.titleMedium?.copyWith(
                    color: colors.textPrimary,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(subtitle, style: context.text.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
