import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/api_exception.dart';
import '../l10n/l10n.dart';
import '../state/auth_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/app_card.dart';
import '../widgets/brand_logo.dart';
import '../widgets/fade_slide_in.dart';
import '../widgets/language_switcher.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.authController});

  final AuthController authController;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _identifierController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  final _passwordFocus = FocusNode();

  bool _submitting = false;
  bool _obscurePassword = true;
  String? _errorMessage;
  bool _deviceWasRevoked = false;

  @override
  void initState() {
    super.initState();

    _deviceWasRevoked = widget.authController.deviceWasRevoked;
    widget.authController.clearRevokedMessage();
  }

  @override
  void dispose() {
    _identifierController.dispose();
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
        _identifierController.text.trim(),
        _passwordController.text,
      );
    } on ApiException catch (e) {
      if (mounted) {
        HapticFeedback.lightImpact();
        setState(() => _errorMessage = e.message);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _errorMessage = context.l10n.serverUnreachable);
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
    final l10n = context.l10n;

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
                        const Align(
                          alignment: AlignmentDirectional.centerEnd,
                          child: LanguageSwitcher(),
                        ),
                        const SizedBox(height: AppSpacing.lg),
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
                                  Text(l10n.welcomeBack,
                                      style: context.text.titleMedium),
                                  const SizedBox(height: AppSpacing.xs),
                                  Text(
                                    l10n.loginInstruction,
                                    style: context.text.bodyMedium,
                                  ),
                                  const SizedBox(height: AppSpacing.xl),
                                  if (_deviceWasRevoked) ...[
                                    _MessageBanner(
                                      message:
                                          l10n.deviceDeactivatedContactAdmin,
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
                                    controller: _identifierController,
                                    decoration: InputDecoration(
                                      labelText: l10n.emailOrPhone,
                                      hintText: l10n.emailExample,
                                      prefixIcon:
                                          const Icon(Icons.person_outline),
                                    ),
                                    keyboardType: TextInputType.emailAddress,
                                    textInputAction: TextInputAction.next,
                                    autocorrect: false,
                                    enableSuggestions: false,
                                    autofillHints: const [],
                                    enabled: !_submitting,
                                    onFieldSubmitted: (_) =>
                                        _passwordFocus.requestFocus(),
                                    validator: (value) =>
                                        (value == null || value.trim().isEmpty)
                                            ? l10n.enterEmailOrPhone
                                            : null,
                                  ),
                                  const SizedBox(height: AppSpacing.md),
                                  TextFormField(
                                    controller: _passwordController,
                                    focusNode: _passwordFocus,
                                    decoration: InputDecoration(
                                      labelText: l10n.password,
                                      hintText: l10n.enterPassword,
                                      prefixIcon:
                                          const Icon(Icons.lock_outline),
                                      suffixIcon: IconButton(
                                        onPressed: () => setState(
                                          () => _obscurePassword =
                                              !_obscurePassword,
                                        ),
                                        icon: Icon(
                                          _obscurePassword
                                              ? Icons.visibility_outlined
                                              : Icons.visibility_off_outlined,
                                        ),
                                        tooltip: _obscurePassword
                                            ? l10n.showPassword
                                            : l10n.hidePassword,
                                      ),
                                    ),
                                    obscureText: _obscurePassword,
                                    textInputAction: TextInputAction.done,
                                    autofillHints: const [
                                      AutofillHints.password
                                    ],
                                    enabled: !_submitting,
                                    onFieldSubmitted: (_) => _submit(),
                                    validator: (value) =>
                                        (value == null || value.isEmpty)
                                            ? l10n.enterPassword
                                            : null,
                                  ),
                                  const SizedBox(height: AppSpacing.xl),
                                  FilledButton(
                                    onPressed: _submitting ? null : _submit,
                                    child: AnimatedSwitcher(
                                      duration:
                                          context.motion(AppDurations.fast),
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
                                          : Text(
                                              l10n.signIn,
                                              key: const ValueKey('label'),
                                            ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
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
