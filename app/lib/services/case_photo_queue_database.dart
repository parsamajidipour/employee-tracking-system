import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

const casePhotoQueueTable = 'case_photo_queue';

Future<Database> openCasePhotoQueueDatabase() async {
  final databasesPath = await getDatabasesPath();
  final path = p.join(databasesPath, 'case_photo_queue.db');

  return openDatabase(
    path,
    version: 1,
    onCreate: (db, version) => db.execute('''
      CREATE TABLE $casePhotoQueueTable (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        case_id INTEGER NOT NULL,
        file_path TEXT NOT NULL,
        lat REAL NOT NULL,
        lng REAL NOT NULL,
        accuracy_m REAL,
        captured_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        failure_reason TEXT,
        created_at TEXT NOT NULL
      )
    '''),
  );
}
