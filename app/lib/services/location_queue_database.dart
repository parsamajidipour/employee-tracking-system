import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

const locationPointsTable = 'location_points';

Future<Database> openLocationQueueDatabase() async {
  final databasesPath = await getDatabasesPath();
  final path = p.join(databasesPath, 'location_queue.db');

  return openDatabase(
    path,
    version: 1,
    onCreate: (db, version) => db.execute('''
      CREATE TABLE $locationPointsTable (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lat REAL NOT NULL,
        lng REAL NOT NULL,
        accuracy_m REAL,
        speed_mps REAL,
        heading_deg REAL,
        battery_pct INTEGER,
        is_mocked INTEGER NOT NULL,
        recorded_at TEXT NOT NULL
      )
    '''),
  );
}
