const Database = require('better-sqlite3');
const path = require('path');

const dbPath = process.env.DB_PATH || path.join(__dirname, '../../shift_crm.db');
const db = new Database(dbPath);

// Enable WAL mode for better performance
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

// Initialize tables
db.exec(`
  CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    nik TEXT UNIQUE,
    position TEXT,
    department TEXT,
    phone TEXT,
    email TEXT,
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    company TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'prospect')),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS shifts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS shift_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    shift_id INTEGER NOT NULL,
    schedule_date TEXT NOT NULL,
    customer_id INTEGER,
    notes TEXT,
    status TEXT DEFAULT 'scheduled' CHECK(status IN ('scheduled', 'completed', 'absent', 'leave')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
  );

  CREATE TABLE IF NOT EXISTS activities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER,
    customer_id INTEGER,
    type TEXT NOT NULL,
    description TEXT NOT NULL,
    activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
  );
`);

// Seed default shifts if none exist
const shiftCount = db.prepare('SELECT COUNT(*) as count FROM shifts').get();
if (shiftCount.count === 0) {
  const insertShift = db.prepare(
    'INSERT INTO shifts (name, start_time, end_time, description) VALUES (?, ?, ?, ?)'
  );
  insertShift.run('Shift Pagi', '07:00', '15:00', 'Shift kerja pagi hari');
  insertShift.run('Shift Siang', '11:00', '19:00', 'Shift kerja siang hari');
  insertShift.run('Shift Malam', '19:00', '07:00', 'Shift kerja malam hari');
}

module.exports = db;
