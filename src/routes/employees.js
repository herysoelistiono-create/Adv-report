const express = require('express');
const router = express.Router();
const db = require('../models/db');

// GET all employees
router.get('/', (req, res) => {
  try {
    const { status, search } = req.query;
    let query = 'SELECT * FROM employees WHERE 1=1';
    const params = [];

    if (status) {
      query += ' AND status = ?';
      params.push(status);
    }
    if (search) {
      query += ' AND (name LIKE ? OR nik LIKE ? OR department LIKE ?)';
      const like = `%${search}%`;
      params.push(like, like, like);
    }

    query += ' ORDER BY name ASC';
    const employees = db.prepare(query).all(...params);
    res.json({ success: true, data: employees });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET single employee
router.get('/:id', (req, res) => {
  try {
    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(req.params.id);
    if (!employee) {
      return res.status(404).json({ success: false, message: 'Karyawan tidak ditemukan' });
    }
    res.json({ success: true, data: employee });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// CREATE employee
router.post('/', (req, res) => {
  try {
    const { name, nik, position, department, phone, email, status } = req.body;
    if (!name) {
      return res.status(400).json({ success: false, message: 'Nama karyawan wajib diisi' });
    }
    const stmt = db.prepare(
      `INSERT INTO employees (name, nik, position, department, phone, email, status)
       VALUES (?, ?, ?, ?, ?, ?, ?)`
    );
    const result = stmt.run(name, nik || null, position || null, department || null,
      phone || null, email || null, status || 'active');

    db.prepare(
      `INSERT INTO activities (employee_id, type, description) VALUES (?, ?, ?)`
    ).run(result.lastInsertRowid, 'employee', `Karyawan baru ditambahkan: ${name}`);

    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(result.lastInsertRowid);
    res.status(201).json({ success: true, data: employee, message: 'Karyawan berhasil ditambahkan' });
  } catch (err) {
    if (err.message.includes('UNIQUE')) {
      return res.status(400).json({ success: false, message: 'NIK sudah terdaftar' });
    }
    res.status(500).json({ success: false, message: err.message });
  }
});

// UPDATE employee
router.put('/:id', (req, res) => {
  try {
    const existing = db.prepare('SELECT * FROM employees WHERE id = ?').get(req.params.id);
    if (!existing) {
      return res.status(404).json({ success: false, message: 'Karyawan tidak ditemukan' });
    }
    const { name, nik, position, department, phone, email, status } = req.body;
    db.prepare(
      `UPDATE employees SET name=?, nik=?, position=?, department=?, phone=?, email=?, status=?, updated_at=CURRENT_TIMESTAMP
       WHERE id=?`
    ).run(
      name || existing.name, nik || null, position || null, department || null,
      phone || null, email || null, status || existing.status, req.params.id
    );
    const employee = db.prepare('SELECT * FROM employees WHERE id = ?').get(req.params.id);
    res.json({ success: true, data: employee, message: 'Karyawan berhasil diperbarui' });
  } catch (err) {
    if (err.message.includes('UNIQUE')) {
      return res.status(400).json({ success: false, message: 'NIK sudah terdaftar' });
    }
    res.status(500).json({ success: false, message: err.message });
  }
});

// DELETE employee
router.delete('/:id', (req, res) => {
  try {
    const existing = db.prepare('SELECT * FROM employees WHERE id = ?').get(req.params.id);
    if (!existing) {
      return res.status(404).json({ success: false, message: 'Karyawan tidak ditemukan' });
    }
    db.prepare('DELETE FROM employees WHERE id = ?').run(req.params.id);
    res.json({ success: true, message: 'Karyawan berhasil dihapus' });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

module.exports = router;
