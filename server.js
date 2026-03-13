require('dotenv').config();
const express = require('express');
const cors = require('cors');
const path = require('path');
const rateLimit = require('express-rate-limit');

const employeesRouter = require('./src/routes/employees');
const customersRouter = require('./src/routes/customers');
const shiftsRouter = require('./src/routes/shifts');
const reportsRouter = require('./src/routes/reports');

const app = express();
const PORT = process.env.PORT || 3000;

// Rate limiting
const apiLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 300,
  standardHeaders: true,
  legacyHeaders: false,
  message: { success: false, message: 'Terlalu banyak permintaan, coba lagi nanti.' }
});

const spaLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 500,
  standardHeaders: true,
  legacyHeaders: false
});

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, 'public')));

// Apply rate limiting to all API routes
app.use('/api/', apiLimiter);

// API Routes
app.use('/api/employees', employeesRouter);
app.use('/api/customers', customersRouter);
app.use('/api/shifts', shiftsRouter);
app.use('/api/reports', reportsRouter);

// Health check
app.get('/api/health', (req, res) => {
  res.json({ success: true, message: 'Shift CRM berjalan normal', version: '1.0.0' });
});

// Serve frontend for all other routes
app.get('/{*splat}', spaLimiter, (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Error handler
app.use((err, req, res, _next) => {
  console.error(err.stack);
  res.status(500).json({ success: false, message: 'Terjadi kesalahan pada server' });
});

app.listen(PORT, () => {
  console.log(`Shift CRM berjalan di http://localhost:${PORT}`);
});

module.exports = app;
