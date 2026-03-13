const express = require('express');
const router = express.Router();
const db = require('../models/db');

// Dashboard summary
router.get('/summary', (req, res) => {
  try {
    const totalEmployees = db.prepare("SELECT COUNT(*) as count FROM employees WHERE status = 'active'").get().count;
    const totalCustomers = db.prepare("SELECT COUNT(*) as count FROM customers WHERE status != 'inactive'").get().count;
    const todaySchedules = db.prepare(
      "SELECT COUNT(*) as count FROM shift_schedules WHERE schedule_date = date('now')"
    ).get().count;
    const thisMonthSchedules = db.prepare(
      "SELECT COUNT(*) as count FROM shift_schedules WHERE strftime('%Y-%m', schedule_date) = strftime('%Y-%m', 'now')"
    ).get().count;

    const statusBreakdown = db.prepare(
      "SELECT status, COUNT(*) as count FROM shift_schedules WHERE strftime('%Y-%m', schedule_date) = strftime('%Y-%m', 'now') GROUP BY status"
    ).all();

    const recentActivities = db.prepare(
      `SELECT a.*, e.name as employee_name, c.name as customer_name
       FROM activities a
       LEFT JOIN employees e ON a.employee_id = e.id
       LEFT JOIN customers c ON a.customer_id = c.id
       ORDER BY a.activity_date DESC LIMIT 10`
    ).all();

    const topEmployees = db.prepare(
      `SELECT e.name, COUNT(ss.id) as shift_count
       FROM employees e
       LEFT JOIN shift_schedules ss ON e.id = ss.employee_id
         AND strftime('%Y-%m', ss.schedule_date) = strftime('%Y-%m', 'now')
       WHERE e.status = 'active'
       GROUP BY e.id ORDER BY shift_count DESC LIMIT 5`
    ).all();

    res.json({
      success: true,
      data: {
        totalEmployees,
        totalCustomers,
        todaySchedules,
        thisMonthSchedules,
        statusBreakdown,
        recentActivities,
        topEmployees
      }
    });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

// Monthly report
router.get('/monthly', (req, res) => {
  try {
    const { month, year } = req.query;
    const m = (month || new Date().getMonth() + 1).toString().padStart(2, '0');
    const y = year || new Date().getFullYear().toString();

    const schedules = db.prepare(`
      SELECT ss.*, e.name as employee_name, e.department, s.name as shift_name,
             s.start_time, s.end_time, c.name as customer_name
      FROM shift_schedules ss
      JOIN employees e ON ss.employee_id = e.id
      JOIN shifts s ON ss.shift_id = s.id
      LEFT JOIN customers c ON ss.customer_id = c.id
      WHERE strftime('%m', ss.schedule_date) = ? AND strftime('%Y', ss.schedule_date) = ?
      ORDER BY ss.schedule_date ASC, e.name ASC
    `).all(m, y);

    const byEmployee = db.prepare(`
      SELECT e.id, e.name, e.department,
             COUNT(ss.id) as total_shifts,
             SUM(CASE WHEN ss.status = 'completed' THEN 1 ELSE 0 END) as completed,
             SUM(CASE WHEN ss.status = 'absent' THEN 1 ELSE 0 END) as absent,
             SUM(CASE WHEN ss.status = 'leave' THEN 1 ELSE 0 END) as leave_count
      FROM employees e
      LEFT JOIN shift_schedules ss ON e.id = ss.employee_id
        AND strftime('%m', ss.schedule_date) = ? AND strftime('%Y', ss.schedule_date) = ?
      WHERE e.status = 'active'
      GROUP BY e.id ORDER BY e.name ASC
    `).all(m, y);

    res.json({
      success: true,
      data: { schedules, byEmployee, month: m, year: y }
    });
  } catch (err) {
    res.status(500).json({ success: false, message: err.message });
  }
});

module.exports = router;
