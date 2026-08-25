import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import '../models/inspection_case.dart';
import '../theme/app_theme.dart';

class CaseLocationScreen extends StatefulWidget {
  const CaseLocationScreen({super.key, required this.inspectionCase});

  final InspectionCase inspectionCase;

  @override
  State<CaseLocationScreen> createState() => _CaseLocationScreenState();
}

class _CaseLocationScreenState extends State<CaseLocationScreen> {
  final MapController _mapController = MapController();
  StreamSubscription<Position>? _positionSubscription;
  Position? _position;
  String? _locationError;
  bool _mapReady = false;

  LatLng get _casePoint =>
      LatLng(widget.inspectionCase.lat, widget.inspectionCase.lng);

  @override
  void initState() {
    super.initState();
    _startLocation();
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    _mapController.dispose();
    super.dispose();
  }

  Future<void> _startLocation() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      if (mounted) {
        setState(() => _locationError = 'Turn on GPS to show your location.');
      }
      return;
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      if (mounted) {
        setState(() => _locationError =
            'Location permission is required to show where you are.');
      }
      return;
    }

    try {
      final current = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );
      if (!mounted) return;
      setState(() {
        _position = current;
        _locationError = null;
      });
      _fitBoth();

      _positionSubscription = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: 10,
        ),
      ).listen((position) {
        if (!mounted) return;
        setState(() => _position = position);
      });
    } catch (_) {
      if (mounted) {
        setState(() => _locationError =
            'Your location is temporarily unavailable. The property is still shown.');
      }
    }
  }

  void _fitBoth() {
    if (!_mapReady) return;
    final points = <LatLng>[_casePoint];
    if (_position case final position?) {
      points.add(LatLng(position.latitude, position.longitude));
    }
    if (points.length == 1) {
      _mapController.move(_casePoint, 16);
      return;
    }
    _mapController.fitCamera(CameraFit.bounds(
      bounds: LatLngBounds.fromPoints(points),
      padding: const EdgeInsets.fromLTRB(44, 80, 44, 190),
      maxZoom: 16,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final position = _position;
    final distance = position == null
        ? null
        : Geolocator.distanceBetween(
            position.latitude,
            position.longitude,
            widget.inspectionCase.lat,
            widget.inspectionCase.lng,
          );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Property map'),
        actions: [
          IconButton(
            tooltip: 'Fit property and my location',
            onPressed: _fitBoth,
            icon: const Icon(Icons.center_focus_strong_outlined),
          ),
        ],
      ),
      body: Stack(
        children: [
          FlutterMap(
            mapController: _mapController,
            options: MapOptions(
              initialCenter: _casePoint,
              initialZoom: 16,
              minZoom: 4,
              maxZoom: 19,
              onMapReady: () {
                _mapReady = true;
                _fitBoth();
              },
            ),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName:
                    'ranjbarali.parsamajidipour.smartinspection',
                maxZoom: 19,
              ),
              MarkerLayer(markers: [
                Marker(
                  point: _casePoint,
                  width: 54,
                  height: 64,
                  alignment: Alignment.topCenter,
                  child: _MapPin(
                    color: colors.primary,
                    icon: Icons.home_work_outlined,
                  ),
                ),
                if (position != null)
                  Marker(
                    point: LatLng(position.latitude, position.longitude),
                    width: 46,
                    height: 46,
                    child: _MapPin(
                      color: colors.success,
                      icon: Icons.navigation_rounded,
                      compact: true,
                    ),
                  ),
              ]),
              const RichAttributionWidget(attributions: [
                TextSourceAttribution('OpenStreetMap contributors'),
              ]),
            ],
          ),
          Positioned(
            left: AppSpacing.screen,
            right: AppSpacing.screen,
            bottom: AppSpacing.screen,
            child: Material(
              color: colors.surface,
              elevation: 8,
              shadowColor: Colors.black26,
              borderRadius: BorderRadius.circular(18),
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(widget.inspectionCase.title,
                        style: context.text.titleMedium),
                    const SizedBox(height: 4),
                    Text(widget.inspectionCase.propertyAddress,
                        style: context.text.bodyMedium),
                    const SizedBox(height: AppSpacing.md),
                    Row(children: [
                      _LegendDot(color: colors.primary, label: 'Property'),
                      const SizedBox(width: AppSpacing.lg),
                      _LegendDot(color: colors.success, label: 'You'),
                      const Spacer(),
                      Text(
                        distance == null
                            ? 'Locating…'
                            : distance < 1000
                                ? '${distance.round()} m away'
                                : '${(distance / 1000).toStringAsFixed(1)} km away',
                        style: context.text.labelMedium,
                      ),
                    ]),
                    if (_locationError != null) ...[
                      const SizedBox(height: AppSpacing.sm),
                      Text(_locationError!,
                          style: context.text.bodySmall
                              ?.copyWith(color: colors.warning)),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MapPin extends StatelessWidget {
  const _MapPin({
    required this.color,
    required this.icon,
    this.compact = false,
  });

  final Color color;
  final IconData icon;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: color,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 3),
        boxShadow: const [BoxShadow(color: Colors.black26, blurRadius: 9)],
      ),
      child: Icon(icon, size: compact ? 20 : 24, color: Colors.white),
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(children: [
        Container(
          width: 9,
          height: 9,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 5),
        Text(label, style: context.text.bodySmall),
      ]);
}
