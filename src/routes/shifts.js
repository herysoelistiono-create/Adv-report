const express = require('express');
const router = express.Router();
const db = require('../models/db');

// GET all shifts (shift types)
router.get('/types', (req, res) => {
  try {
    const shifts = db.prepare('SELECT * FROM shifts ORDER BY start_time ASC').all();
    res.json({ success: true, data: shifts });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET shift schedules
router.get('/', (req, res) => {
  try {
    const { date, empFilter, month, year } = req.query;
    let query = `
      SELECT ss.*, e.name as employee_name, e.department, s.name as shift_name,
             s.start_time, s.end_time, c.name as customer_name
      FROM shift_schedules ss
      JOIN employees e ON ss.employee_id = e.id
      JOIN shifts s ON ss.shift_id = s.id
      LEFT JOIN customers c ON ss.customer_id = c.id
      WHERE 1=1
    `;
    const params = [];

    if (date) {
      query += ' AND ss.schedule_date = ?';
      params.push(date);
    }
    if (empFilter) {
      query += ' AND ss.employee_id = ?';
      params.push(empFilter);
    }
    if (month && year) {
      query += " AND strftime('%m', ss.schedule_date) = ? AND strftime('%Y', ss.schedule_date) = ?";
      params.push(month.toString().padStart(2, '0'), year.toString());
    } else if (year) {
      query += " AND strftime('%Y', ss.schedule_date) = ?";
      params.push(year.toString());
    }

    query += ' ORDER BY ss.schedule_date DESC, s.start_time ASC';
    const schedules = db.prepare(query).all(...params);
    res.json({ success: true, data: schedules });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET single schedule
router.get('/:id', (req, res) => {
  try {
    const schedule = db.prepare(`
      SELECT ss.*, e.name as employee_name, s.name as shift_name,
             s.start_time, s.end_time, c.name as customer_name
      FROM shift_schedules ss
      JOIN employees e ON ss.employee_id = e.id
      JOIN shifts s ON ss.shift_id = s.id
      LEFT JOIN customers c ON ss.customer_id = c.id
      WHERE ss.id = ?
    `).get(req.params.id);
    if (!schedule) {
      return res.status(404).json({ success: false, message: 'Jadwal tidak ditemukan' });
    }
    res.json({ success: true, data: schedule });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// CREATE schedule
router.post('/', (req, res) => {
  try {
    const { employee_id, shift_id, schedule_date, customer_id, notes, status } = req.body;
    if (!employee_id || !shift_id || !schedule_date) {
      return res.status(400).json({
        success: false,
        message: 'Karyawan, shift, dan tanggal wajib diisi'
      });
    }

    // Check if employee exists
    const employee = db.prepare('SELECT id, name FROM employees WHERE id = ?').get(employee_id);
    if (!employee) {
      return res.status(404).json({ success: false, message: 'Karyawan tidak ditemukan' });
    }

    const stmt = db.prepare(
      `INSERT INTO shift_schedules (employee_id, shift_id, schedule_date, customer_id, notes, status)
       VALUES (?, ?, ?, ?, ?, ?)`
    );
    const result = stmt.run(
      employee_id, shift_id, schedule_date,
      customer_id || null, notes || null, status || 'scheduled'
    );

    db.prepare(
      `INSERT INTO activities (employee_id, type, description) VALUES (?, ?, ?)`
    ).run(employee_id, 'shift', `Jadwal shift dibuat untuk ${employee.name} pada ${schedule_date}`);

    const schedule = db.prepare(`
      SELECT ss.*, e.name as employee_name, s.name as shift_name, s.start_time, s.end_time
      FROM shift_schedules ss
      JOIN employees e ON ss.employee_id = e.id
      JOIN shifts s ON ss.shift_id = s.id
      WHERE ss.id = ?
    `).get(result.lastInsertRowid);

    res.status(201).json({ success: true, data: schedule, message: 'Jadwal berhasil ditambahkan' });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// UPDATE schedule
router.put('/:id', (req, res) => {
  try {
    const existing = db.prepare('SELECT * FROM shift_schedules WHERE id = ?').get(req.params.id);
    if (!existing) {
      return res.status(404).json({ success: false, message: 'Jadwal tidak ditemukan' });
    }
    const { employee_id, shift_id, schedule_date, customer_id, notes, status } = req.body;
    db.prepare(
      `UPDATE shift_schedules SET employee_id=?, shift_id=?, schedule_date=?,
       customer_id=?, notes=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?`
    ).run(
      employee_id || existing.employee_id,
      shift_id || existing.shift_id,
      schedule_date || existing.schedule_date,
      customer_id !== undefined ? (customer_id || null) : existing.customer_id,
      notes !== undefined ? (notes || null) : existing.notes,
      status || existing.status,
      req.params.id
    );
    const schedule = db.prepare(`
      SELECT ss.*, e.name as employee_name, s.name as shift_name, s.start_time, s.end_time
      FROM shift_schedules ss
      JOIN employees e ON ss.employee_id = e.id
      JOIN shifts s ON ss.shift_id = s.id
      WHERE ss.id = ?
    `).get(req.params.id);
    res.json({ success: true, data: schedule, message: 'Jadwal berhasil diperbarui' });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// DELETE schedule
router.delete('/:id', (req, res) => {
  try {
    const existing = db.prepare('SELECT * FROM shift_schedules WHERE id = ?').get(req.params.id);
    if (!existing) {
      return res.status(404).json({ success: false, message: 'Jadwal tidak ditemukan' });
    }
    db.prepare('DELETE FROM shift_schedules WHERE id = ?').run(req.params.id);
    res.json({ success: true, message: 'Jadwal berhasil dihapus' });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

module.exports = router;
