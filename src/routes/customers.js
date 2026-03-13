const express = require('express');
const router = express.Router();
const db = require('../models/db');

// GET all customers
router.get('/', (req, res) => {
  try {
    const { status, search } = req.query;
    let query = 'SELECT * FROM customers WHERE 1=1';
    const params = [];

    if (status) {
      query += ' AND status = ?';
      params.push(status);
    }
    if (search) {
      query += ' AND (name LIKE ? OR company LIKE ? OR phone LIKE ?)';
      const like = `%${search}%`;
      params.push(like, like, like);
    }

    query += ' ORDER BY name ASC';
    const customers = db.prepare(query).all(...params);
    res.json({ success: true, data: customers });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// GET single customer
router.get('/:id', (req, res) => {
  try {
    const customer = db.prepare('SELECT * FROM customers WHERE id = ?').get(req.params.id);
    if (!customer) {
      return res.status(404).json({ success: false, message: 'Pelanggan tidak ditemukan' });
    }
    res.json({ success: true, data: customer });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// CREATE customer
router.post('/', (req, res) => {
  try {
    const { name, company, phone, email, address, status, notes } = req.body;
    if (!name) {
      return res.status(400).json({ success: false, message: 'Nama pelanggan wajib diisi' });
    }
    const stmt = db.prepare(
      `INSERT INTO customers (name, company, phone, email, address, status, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?)`
    );
    const result = stmt.run(name, company || null, phone || null, email || null,
      address || null, status || 'active', notes || null);

    db.prepare(
      `INSERT INTO activities (customer_id, type, description) VALUES (?, ?, ?)`
    ).run(result.lastInsertRowid, 'customer', `Pelanggan baru ditambahkan: ${name}`);

    const customer = db.prepare('SELECT * FROM customers WHERE id = ?').get(result.lastInsertRowid);
    res.status(201).json({ success: true, data: customer, message: 'Pelanggan berhasil ditambahkan' });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// UPDATE customer
router.put('/:id', (req, res) => {
  try {
    const existing = db.prepare('SELECT * FROM customers WHERE id = ?').get(req.params.id);
    if (!existing) {
      return res.status(404).json({ success: false, message: 'Pelanggan tidak ditemukan' });
    }
    const { name, company, phone, email, address, status, notes } = req.body;
    db.prepare(
      `UPDATE customers SET name=?, company=?, phone=?, email=?, address=?, status=?, notes=?, updated_at=CURRENT_TIMESTAMP
       WHERE id=?`
    ).run(
      name || existing.name, company || null, phone || null, email || null,
      address || null, status || existing.status, notes || null, req.params.id
    );
    const customer = db.prepare('SELECT * FROM customers WHERE id = ?').get(req.params.id);
    res.json({ success: true, data: customer, message: 'Pelanggan berhasil diperbarui' });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// DELETE customer
router.delete('/:id', (req, res) => {
  try {
    const existing = db.prepare('SELECT * FROM customers WHERE id = ?').get(req.params.id);
    if (!existing) {
      return res.status(404).json({ success: false, message: 'Pelanggan tidak ditemukan' });
    }
    db.prepare('DELETE FROM customers WHERE id = ?').run(req.params.id);
    res.json({ success: true, message: 'Pelanggan berhasil dihapus' });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

module.exports = router;
