import 'package:app/models/inspection_case.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('case parsing tolerates missing property address and system events', () {
    final inspectionCase = InspectionCase.fromJson({
      'id': 10,
      'reference_no': 'CASE-10',
      'title': 'Site inspection',
      'property_address': null,
      'lat': 23.55,
      'lng': 58.35,
      'status': 'overdue',
      'priority': 'normal',
      'assigned_to': 2,
      'assignee_name': 'Ahmed',
      'assigned_at': '2026-08-25T08:00:00Z',
      'accepted_at': '2026-08-25T08:15:00Z',
      'planned_at': '2026-08-25T09:00:00Z',
      'started_at': null,
      'completed_at': null,
      'notes': null,
      'created_at': '2026-08-25T07:30:00Z',
      'status_events': [
        {
          'id': 1,
          'actor_name': null,
          'from_status': 'accepted',
          'to_status': 'overdue',
          'note': 'Planned inspection time passed.',
          'created_at': '2026-08-25T09:01:00Z',
        },
      ],
      'photos': <dynamic>[],
    });

    expect(inspectionCase.propertyAddress, 'Property address not provided');
    expect(inspectionCase.statusEvents!.single.actorName, 'System');
  });
}
