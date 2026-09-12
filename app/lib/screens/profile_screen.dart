import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../services/auth_storage.dart';
import '../l10n/l10n.dart';
import '../services/permission_service.dart';
import '../models/permission_snapshot.dart';
import '../state/auth_controller.dart';
import '../state/live_refresh.dart';
import '../state/live_updates.dart';
import '../theme/app_theme.dart';
import '../widgets/app_card.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/live_dot.dart';
import '../widgets/status_pill.dart';
import '../widgets/language_switcher.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.authController});

  final AuthController authController;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen>
    with WidgetsBindingObserver, LiveRefresh<ProfileScreen> {
  final AuthStorage _authStorage = AuthStorage();
  final PermissionService _permissionService = PermissionService();

  String? _deviceId;
  String _name = '';
  String _appVersion = '';
  PermissionSnapshot? _permissions;

  @override
  LiveUpdates get liveUpdates => widget.authController.liveUpdates;

  @override
  void onLiveUpdate() {}

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    startLiveRefresh();
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    stopLiveRefresh();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _load();
  }

  Future<void> _load() async {
    final id = await _authStorage.deviceIdentifier();
    final identity = await widget.authController.meRepository.fetchIdentity();
    final info = await PackageInfo.fromPlatform();
    final permissions = await _permissionService.currentSnapshot();

    if (!mounted) return;
    setState(() {
      _deviceId = id;
      _name = identity?.name ?? '';
      _appVersion = '${info.version} (${info.buildNumber})';
      _permissions = permissions;
    });
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final permissions = _permissions;
    final deviceId = _deviceId;
    final l10n = context.l10n;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.profile)),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
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
                      IconTile(icon: Icons.badge_outlined, size: 52),
                      const SizedBox(width: AppSpacing.md),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _name.isEmpty ? l10n.fieldSurveyor : _name,
                              style: context.text.titleLarge,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 2),
                            Text(l10n.fieldSurveyor,
                                style: context.text.bodySmall),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.xxl),
              FadeSlideIn(
                index: 1,
                child: SectionHeader(
                  overline: l10n.privacy,
                  title: l10n.whatIsRecorded,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              FadeSlideIn(
                index: 2,
                child: AppCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _PrivacyLine(
                        icon: Icons.schedule_outlined,
                        text: l10n.privacyWorkingHours,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      _PrivacyLine(
                        icon: Icons.visibility_off_outlined,
                        text: l10n.privacyOutsideWindow,
                      ),
                      const SizedBox(height: AppSpacing.md),
                      _PrivacyLine(
                        icon: Icons.history_toggle_off_outlined,
                        text: l10n.privacyAudit,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.xxl),
              FadeSlideIn(
                index: 3,
                child: SectionHeader(
                  overline: l10n.device,
                  title: l10n.thisPhone,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              FadeSlideIn(
                index: 4,
                child: AppCard(
                  child: Column(
                    children: [
                      _InfoRow(
                        label: l10n.liveUpdates,
                        trailing: LiveDot(state: liveUpdates.connectionState),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      _InfoRow(
                        label: l10n.permissions,
                        trailing: permissions == null
                            ? const SizedBox.shrink()
                            : StatusPill(
                                label: permissions.allGranted
                                    ? l10n.allGranted
                                    : l10n.actionNeeded,
                                tone: permissions.allGranted
                                    ? StatusTone.active
                                    : StatusTone.warning,
                              ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      _InfoRow(
                        label: l10n.appVersion,
                        trailing: Text(
                          _appVersion.isEmpty ? '—' : _appVersion,
                          style: context.text.bodyMedium,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                      _InfoRow(
                        label: l10n.deviceId,
                        trailing: Text(
                          deviceId == null
                              ? '—'
                              : deviceId.substring(0, 8).toUpperCase(),
                          style: context.text.bodyMedium,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              if (permissions != null && !permissions.allGranted) ...[
                const SizedBox(height: AppSpacing.cardGap),
                FadeSlideIn(
                  index: 5,
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      await _permissionService.openSettings();
                      await _load();
                    },
                    icon: const Icon(Icons.settings_outlined),
                    label: Text(l10n.openAppSettings),
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.xxl),
              FadeSlideIn(
                index: 6,
                child: Row(
                  children: [
                    Icon(Icons.info_outline,
                        size: 16, color: colors.textTertiary),
                    const SizedBox(width: AppSpacing.sm),
                    Expanded(
                      child: Text(
                        l10n.deviceSignInInfo,
                        style: context.text.bodySmall
                            ?.copyWith(color: colors.textTertiary),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.cardGap),
              FadeSlideIn(
                index: 7,
                child: AppCard(
                  child: Row(
                    children: [
                      Expanded(child: Text(l10n.language)),
                      const LanguageSwitcher(),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PrivacyLine extends StatelessWidget {
  const _PrivacyLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: colors.primaryStrong),
        const SizedBox(width: AppSpacing.md),
        Expanded(child: Text(text, style: context.text.bodySmall)),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.trailing});

  final String label;
  final Widget trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: Text(label, style: context.text.bodyMedium)),
        const SizedBox(width: AppSpacing.md),
        trailing,
      ],
    );
  }
}
