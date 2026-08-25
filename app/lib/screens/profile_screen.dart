import 'package:flutter/material.dart';

import '../services/auth_storage.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.authController});

  final AuthController authController;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final AuthStorage _authStorage = AuthStorage();
  String? _deviceId;

  @override
  void initState() {
    super.initState();
    _loadDeviceId();
  }

  Future<void> _loadDeviceId() async {
    final id = await _authStorage.deviceIdentifier();
    if (!mounted) return;
    setState(() => _deviceId = id);
  }

  Future<void> _confirmLogout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sign out'),
        content: const Text(
          'You will need to sign in again to resume tracking and view your cases.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Sign out'),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      await widget.authController.logout();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.screen,
            AppSpacing.lg,
            AppSpacing.screen,
            AppSpacing.huge,
          ),
          children: [
            FadeSlideIn(
              child: AppCard(
                child: Row(
                  children: [
                    IconTile(icon: Icons.person_outline),
                    const SizedBox(width: AppSpacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Field surveyor', style: context.text.titleMedium),
                          const SizedBox(height: 2),
                          Text(
                            _deviceId == null
                                ? 'Loading device info…'
                                : 'Device ${_deviceId!.substring(0, 8)}',
                            style: context.text.bodySmall,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.cardGap),
            FadeSlideIn(
              index: 1,
              child: AppCard(
                child: Row(
                  children: [
                    Icon(Icons.shield_outlined, size: 18, color: context.colors.textSecondary),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Text(
                        'Location is only ever recorded during your working hours.',
                        style: context.text.bodySmall,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.xxl),
            FadeSlideIn(
              index: 2,
              child: OutlinedButton.icon(
                onPressed: _confirmLogout,
                icon: Icon(Icons.logout, color: context.colors.danger),
                label: Text('Sign out', style: TextStyle(color: context.colors.danger)),
                style: OutlinedButton.styleFrom(
                  side: BorderSide(color: context.colors.danger.withValues(alpha: 0.4)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
