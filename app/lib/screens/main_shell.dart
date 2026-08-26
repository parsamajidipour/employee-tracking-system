import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/app_notification.dart';
import '../services/app_update_service.dart';
import '../services/local_notification_service.dart';
import '../services/tracking_service_controller.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/update_dialog.dart';
import 'case_detail_screen.dart';
import 'cases_screen.dart';
import 'home_screen.dart';
import 'notifications_screen.dart';
import 'profile_screen.dart';

class MainShell extends StatefulWidget {
  const MainShell({
    super.key,
    required this.authController,
    required this.trackingServiceController,
  });

  final AuthController authController;
  final TrackingServiceController trackingServiceController;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;
  String? _casesFilter;
  late final AppUpdateService _updateService =
      AppUpdateService(apiClient: widget.authController.apiClient);
  StreamSubscription<String?>? _notificationTapSubscription;
  StreamSubscription<AppNotification>? _notificationArrivalSubscription;
  bool _checkingForUpdate = false;
  bool _updateDialogVisible = false;

  void _goToCases(String? statusFilter) {
    setState(() {
      _casesFilter = statusFilter;
      _index = 1;
    });
  }

  @override
  void initState() {
    super.initState();
    _notificationTapSubscription =
        LocalNotificationService.taps.listen(_handleNotificationPayload);
    _notificationArrivalSubscription =
        widget.authController.liveUpdates.arrivals.listen((notification) {
      if (notification.type == 'app-release.published') {
        unawaited(_showUpdateIfNeeded());
      }
    });

    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_handleLaunchNotification());
      unawaited(_showUpdateIfNeeded());
    });
  }

  @override
  void dispose() {
    unawaited(_notificationTapSubscription?.cancel());
    unawaited(_notificationArrivalSubscription?.cancel());
    super.dispose();
  }

  Future<void> _showUpdateIfNeeded() async {
    if (_checkingForUpdate || _updateDialogVisible || !mounted) return;
    _checkingForUpdate = true;

    try {
      final info = await _updateService.checkForUpdate();
      if (info == null || !mounted) return;

      _updateDialogVisible = true;
      await showUpdateDialog(
        context,
        info: info,
        updateService: _updateService,
      );
    } finally {
      _checkingForUpdate = false;
      _updateDialogVisible = false;
    }
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => NotificationsScreen(
        authController: widget.authController,
        onUpdateNotificationTap: _showUpdateIfNeeded,
      ),
    ));
  }

  Future<void> _openCase(int id) async {
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CaseDetailScreen(
        authController: widget.authController,
        caseId: id,
      ),
    ));
  }

  Future<void> _handleLaunchNotification() async {
    await LocalNotificationService.initialize();
    _handleNotificationPayload(LocalNotificationService.takeLaunchPayload());
  }

  void _handleNotificationPayload(String? payload) {
    if (payload == null || payload.isEmpty) return;

    try {
      final decoded = jsonDecode(payload);
      if (decoded is! Map<String, dynamic>) return;
      final type = decoded['type'] as String?;

      if (type == 'app-release.published') {
        unawaited(_showUpdateIfNeeded());
        return;
      }

      final caseId = (decoded['case_id'] as num?)?.toInt();
      if (caseId != null) {
        unawaited(_openCase(caseId));
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Scaffold(
      body: IndexedStack(
        index: _index,
        children: [
          HomeScreen(
            authController: widget.authController,
            trackingServiceController: widget.trackingServiceController,
            onOpenCases: _goToCases,
            onOpenNotifications: _openNotifications,
          ),
          CasesScreen(
            authController: widget.authController,
            initialStatusFilter: _casesFilter,
          ),
          ProfileScreen(authController: widget.authController),
        ],
      ),
      bottomNavigationBar: NavigationBarTheme(
        data: NavigationBarThemeData(
          backgroundColor: colors.surface,
          indicatorColor: colors.primarySoft,
          labelTextStyle: WidgetStateProperty.resolveWith(
            (states) => context.text.labelSmall?.copyWith(
              color: states.contains(WidgetState.selected)
                  ? colors.primaryStrong
                  : colors.textTertiary,
              fontWeight: FontWeight.w600,
            ),
          ),
          iconTheme: WidgetStateProperty.resolveWith(
            (states) => IconThemeData(
              color: states.contains(WidgetState.selected)
                  ? colors.primaryStrong
                  : colors.textTertiary,
            ),
          ),
        ),
        child: NavigationBar(
          height: 64,
          selectedIndex: _index,
          onDestinationSelected: (i) => setState(() {
            if (i == 1 && _index != 1) _casesFilter = null;
            _index = i;
          }),
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.today_outlined),
              selectedIcon: Icon(Icons.today),
              label: 'Today',
            ),
            NavigationDestination(
              icon: Icon(Icons.assignment_outlined),
              selectedIcon: Icon(Icons.assignment),
              label: 'Inspections',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Me',
            ),
          ],
        ),
      ),
    );
  }
}
