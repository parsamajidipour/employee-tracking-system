import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/api_exception.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/app_card.dart';
import '../widgets/brand_logo.dart';
import '../widgets/fade_slide_in.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.authController});

  final AuthController authController;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  final _passwordFocus = FocusNode();

  bool _submitting = false;
  bool _obscurePassword = true;
  String? _errorMessage;
  String? _revokedMessage;

  @override
  void initState() {
    super.initState();

    _revokedMessage = widget.authController.revokedMessage;
    widget.authController.clearRevokedMessage();
  }

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    _passwordFocus.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();

    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _submitting = true;
      _errorMessage = null;
    });

    try {
      await widget.authController.login(
        _usernameController.text.trim(),
        _passwordController.text,
      );
    } on ApiException catch (e) {
      if (mounted) {
        HapticFeedback.lightImpact();
        setState(() => _errorMessage = e.message);
      }
    } catch (_) {
      if (mounted) {
        setState(() =>
            _errorMessage = 'Could not reach the server. Check your connection.');
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Scaffold(
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.screen,
                vertical: AppSpacing.xxl,
              ),
              child: ConstrainedBox(
                constraints: BoxConstraints(
                  minHeight: constraints.maxHeight - AppSpacing.xxl * 2,
                ),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 420),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const FadeSlideIn(
                          child: Padding(
                            padding: EdgeInsets.only(bottom: AppSpacing.xxxl),
                            child: BrandWordmark(logoSize: 72),
                          ),
                        ),
                        FadeSlideIn(
                          index: 1,
                          child: AppCard(
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text('Welcome back', style: context.text.titleMedium),
                                  const SizedBox(height: AppSpacing.xs),
                                  Text(
                                    'Use the username your supervisor gave you.',
                                    style: context.text.bodyMedium,
                                  ),
                                  const SizedBox(height: AppSpacing.xl),
                                  if (_revokedMessage != null) ...[
                                    _MessageBanner(
                                      message: _revokedMessage!,
                                      color: colors.warning,
                                      icon: Icons.phonelink_erase_outlined,
                                    ),
                                    const SizedBox(height: AppSpacing.lg),
                                  ],
                                  if (_errorMessage != null) ...[
                                    _MessageBanner(
                                      message: _errorMessage!,
                                      color: colors.danger,
                                      icon: Icons.error_outline,
                                    ),
                                    const SizedBox(height: AppSpacing.lg),
                                  ],
                                  TextFormField(
                                    controller: _usernameController,
                                    decoration: const InputDecoration(
                                      labelText: 'Username',
                                      prefixIcon: Icon(Icons.person_outline),
                                    ),
                                    textInputAction: TextInputAction.next,
                                    autocorrect: false,
                                    enableSuggestions: false,
                                    autofillHints: const [AutofillHints.username],
                                    enabled: !_submitting,
                                    onFieldSubmitted: (_) =>
                                        _passwordFocus.requestFocus(),
                                    validator: (value) =>
                                        (value == null || value.trim().isEmpty)
                                            ? 'Enter your username'
                                            : null,
                                  ),
                                  const SizedBox(height: AppSpacing.md),
                                  TextFormField(
                                    controller: _passwordController,
                                    focusNode: _passwordFocus,
                                    decoration: InputDecoration(
                                      labelText: 'Password',
                                      prefixIcon: const Icon(Icons.lock_outline),
                                      suffixIcon: IconButton(
                                        onPressed: () => setState(
                                          () => _obscurePassword = !_obscurePassword,
                                        ),
                                        icon: Icon(
                                          _obscurePassword
                                              ? Icons.visibility_outlined
                                              : Icons.visibility_off_outlined,
                                        ),
                                        tooltip: _obscurePassword
                                            ? 'Show password'
                                            : 'Hide password',
                                      ),
                                    ),
                                    obscureText: _obscurePassword,
                                    textInputAction: TextInputAction.done,
                                    autofillHints: const [AutofillHints.password],
                                    enabled: !_submitting,
                                    onFieldSubmitted: (_) => _submit(),
                                    validator: (value) =>
                                        (value == null || value.isEmpty)
                                            ? 'Enter your password'
                                            : null,
                                  ),
                                  const SizedBox(height: AppSpacing.xl),
                                  FilledButton(
                                    onPressed: _submitting ? null : _submit,
                                    child: AnimatedSwitcher(
                                      duration: context.motion(AppDurations.fast),
                                      child: _submitting
                                          ? const SizedBox(
                                              key: ValueKey('busy'),
                                              height: 20,
                                              width: 20,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2.4,
                                                color: Colors.white,
                                              ),
                                            )
                                          : const Text(
                                              'Sign in',
                                              key: ValueKey('label'),
                                            ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.xl),
                        FadeSlideIn(
                          index: 2,
                          child: Text(
                            'Location is only recorded during your working hours.',
                            textAlign: TextAlign.center,
                            style: context.text.bodySmall,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _MessageBanner extends StatelessWidget {
  const _MessageBanner({
    required this.message,
    required this.color,
    required this.icon,
  });

  final String message;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: AppRadii.controlRadius,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Text(
              message,
              style: context.text.bodyLarge?.copyWith(color: color),
            ),
          ),
        ],
      ),
    );
  }
}
