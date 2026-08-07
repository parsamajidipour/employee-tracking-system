import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:app/screens/login_screen.dart';
import 'package:app/state/auth_controller.dart';

void main() {
  testWidgets('login screen shows username and password fields', (tester) async {
    // AuthController() alone doesn't touch storage or the network —
    // LoginScreen's initState only reads/clears the plain revokedMessage
    // field — so this needs no plugin mocking.
    final authController = AuthController();

    await tester.pumpWidget(MaterialApp(home: LoginScreen(authController: authController)));

    expect(find.text('Username'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
