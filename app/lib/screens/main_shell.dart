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

  void _goToCases() => setState(() => _index = 1);

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    final screens = [
      HomeScreen(
        authController: widget.authController,
        trackingServiceController: widget.trackingServiceController,
        onOpenCases: _goToCases,
      ),
      CasesScreen(authController: widget.authController),
      ProfileScreen(authController: widget.authController),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: screens),
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
          onDestinationSelected: (i) => setState(() => _index = i),
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.space_dashboard_outlined),
              selectedIcon: Icon(Icons.space_dashboard),
              label: 'Home',
            ),
            NavigationDestination(
              icon: Icon(Icons.assignment_outlined),
              selectedIcon: Icon(Icons.assignment),
              label: 'Cases',
            ),
            NavigationDestination(
              icon: Icon(Icons.person_outline),
              selectedIcon: Icon(Icons.person),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }
}
