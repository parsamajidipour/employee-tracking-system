import 'package:flutter/material.dart';

import '../services/tracking_service_controller.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import 'cases_screen.dart';
import 'home_screen.dart';
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

  void _goToCases(String? statusFilter) {
    setState(() {
      _casesFilter = statusFilter;
      _index = 1;
    });
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
