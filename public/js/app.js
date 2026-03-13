// ============================================================
// Shift CRM - Frontend Application
// ============================================================

const API = '/api';
let currentPage = 'dashboard';
let deleteCallback = null;

// ── Utilities ──────────────────────────────────────────────
async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json', ...options.headers },
    ...options
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.message || 'Terjadi kesalahan');
  return data;
}

function showToast(message, type = 'success') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = (type === 'success' ? '✓ ' : '✗ ') + message;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function confirmDelete(message, callback) {
  document.getElementById('delete-message').textContent = message;
  deleteCallback = callback;
  openModal('delete-modal');
}
document.getElementById('delete-confirm-btn').addEventListener('click', () => {
  if (deleteCallback) { deleteCallback(); deleteCallback = null; }
  closeModal('delete-modal');
});

function loading() {
  return '<div class="loading"><div class="spinner"></div> Memuat data...</div>';
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(dtStr) {
  if (!dtStr) return '-';
  const d = new Date(dtStr);
  return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function statusBadge(status) {
  const map = {
    active: ['badge-success', 'Aktif'],
    inactive: ['badge-secondary', 'Tidak Aktif'],
    prospect: ['badge-warning', 'Prospek'],
    scheduled: ['badge-primary', 'Terjadwal'],
    completed: ['badge-success', 'Selesai'],
    absent: ['badge-danger', 'Tidak Hadir'],
    leave: ['badge-warning', 'Cuti']
  };
  const [cls, label] = map[status] || ['badge-secondary', status];
  return `<span class="badge ${cls}">${label}</span>`;
}

function escapeHtml(text) {
  if (!text) return '-';
  return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Navigation ─────────────────────────────────────────────
function navigate(page) {
  currentPage = page;
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.getAttribute('onclick')?.includes(`'${page}'`));
  });
  const titles = {
    dashboard: 'Dashboard', employees: 'Manajemen Karyawan',
    customers: 'Manajemen Pelanggan', shifts: 'Jadwal Shift',
    calendar: 'Kalender Shift', reports: 'Laporan'
  };
  document.getElementById('page-title').textContent = titles[page] || page;
  const content = document.getElementById('page-content');
  content.innerHTML = loading();
  const pages = { dashboard: renderDashboard, employees: renderEmployees,
    customers: renderCustomers, shifts: renderShifts,
    calendar: renderCalendar, reports: renderReports };
  if (pages[page]) pages[page]();
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ── Dashboard ───────────────────────────────────────────────
async function renderDashboard() {
  const el = document.getElementById('page-content');
  try {
    const { data } = await apiFetch(`${API}/reports/summary`);
    el.innerHTML = `
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">👥</div>
          <div>
            <div class="stat-value">${data.totalEmployees}</div>
            <div class="stat-label">Karyawan Aktif</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">🏢</div>
          <div>
            <div class="stat-value">${data.totalCustomers}</div>
            <div class="stat-label">Total Pelanggan</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">📅</div>
          <div>
            <div class="stat-value">${data.todaySchedules}</div>
            <div class="stat-label">Jadwal Hari Ini</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">📊</div>
          <div>
            <div class="stat-value">${data.thisMonthSchedules}</div>
            <div class="stat-label">Shift Bulan Ini</div>
          </div>
        </div>
      </div>
      <div class="dashboard-grid">
        <div class="card">
          <div class="card-header">
            <h3>🏆 Top Karyawan Bulan Ini</h3>
            <button class="btn btn-primary btn-sm" onclick="navigate('shifts')">+ Jadwal</button>
          </div>
          <div class="card-body">
            ${data.topEmployees.length ? `
            <div class="table-wrap">
              <table>
                <thead><tr><th>Karyawan</th><th class="text-right">Jumlah Shift</th></tr></thead>
                <tbody>
                  ${data.topEmployees.map((e, i) => `
                    <tr>
                      <td>${['🥇','🥈','🥉','4️⃣','5️⃣'][i] || ''} ${escapeHtml(e.name)}</td>
                      <td class="text-right"><strong>${e.shift_count}</strong></td>
                    </tr>`).join('')}
                </tbody>
              </table>
            </div>` : '<div class="empty-state"><p>Belum ada data shift bulan ini</p></div>'}
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h3>📋 Aktivitas Terbaru</h3></div>
          <div class="card-body" style="max-height:300px;overflow-y:auto">
            ${data.recentActivities.length ? data.recentActivities.map(a => `
              <div class="activity-item">
                <div>${escapeHtml(a.description)}</div>
                <div class="activity-time">${formatDateTime(a.activity_date)}</div>
              </div>`).join('') : '<div class="empty-state"><p>Belum ada aktivitas</p></div>'}
          </div>
        </div>
      </div>
      ${data.statusBreakdown.length ? `
      <div class="card mt-3">
        <div class="card-header"><h3>📈 Status Shift Bulan Ini</h3></div>
        <div class="card-body">
          <div class="stats-grid">
            ${data.statusBreakdown.map(s => `
              <div class="stat-card">
                <div>${statusBadge(s.status)}</div>
                <div><div class="stat-value" style="font-size:1.5rem">${s.count}</div></div>
              </div>`).join('')}
          </div>
        </div>
      </div>` : ''}
    `;
  } catch (err) {
    el.innerHTML = `<div class="alert alert-danger">⚠️ ${escapeHtml(err.message)}</div>`;
  }
}

// ── Employees ───────────────────────────────────────────────
let employeeSearch = '';
let employeeStatusFilter = '';

async function renderEmployees() {
  const el = document.getElementById('page-content');
  try {
    let url = `${API}/employees?`;
    if (employeeSearch) url += `search=${encodeURIComponent(employeeSearch)}&`;
    if (employeeStatusFilter) url += `status=${employeeStatusFilter}`;
    const { data } = await apiFetch(url);
    el.innerHTML = `
      <div class="page-header">
        <h2>Karyawan (${data.length})</h2>
        <button class="btn btn-primary" onclick="openEmployeeModal()">+ Tambah Karyawan</button>
      </div>
      <div class="card">
        <div class="card-body" style="padding-bottom:0">
          <div class="search-bar">
            <input class="search-input" type="text" placeholder="🔍 Cari nama, NIK, departemen..."
              value="${escapeHtml(employeeSearch)}"
              oninput="employeeSearch=this.value; debounce(renderEmployees, 400)()">
            <select class="filter-select" onchange="employeeStatusFilter=this.value; renderEmployees()">
              <option value="" ${!employeeStatusFilter?'selected':''}>Semua Status</option>
              <option value="active" ${employeeStatusFilter==='active'?'selected':''}>Aktif</option>
              <option value="inactive" ${employeeStatusFilter==='inactive'?'selected':''}>Tidak Aktif</option>
            </select>
          </div>
        </div>
        <div class="table-wrap">
          ${data.length ? `
          <table>
            <thead><tr><th>#</th><th>Nama</th><th>NIK</th><th>Jabatan</th><th>Departemen</th><th>Telepon</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              ${data.map((e, i) => `
                <tr>
                  <td class="text-muted">${i+1}</td>
                  <td><strong>${escapeHtml(e.name)}</strong></td>
                  <td>${escapeHtml(e.nik)}</td>
                  <td>${escapeHtml(e.position)}</td>
                  <td>${escapeHtml(e.department)}</td>
                  <td>${escapeHtml(e.phone)}</td>
                  <td>${statusBadge(e.status)}</td>
                  <td>
                    <button class="btn btn-secondary btn-sm" onclick="editEmployee(${e.id})">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteEmployee(${e.id}, '${escapeHtml(e.name)}')">🗑️</button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>` : `<div class="empty-state"><div class="icon">👥</div><p>Belum ada data karyawan</p></div>`}
        </div>
      </div>
    `;
  } catch (err) {
    el.innerHTML = `<div class="alert alert-danger">⚠️ ${escapeHtml(err.message)}</div>`;
  }
}

function openEmployeeModal(data = null) {
  document.getElementById('employee-modal-title').textContent = data ? 'Edit Karyawan' : 'Tambah Karyawan';
  document.getElementById('employee-id').value = data?.id || '';
  document.getElementById('emp-name').value = data?.name || '';
  document.getElementById('emp-nik').value = data?.nik || '';
  document.getElementById('emp-position').value = data?.position || '';
  document.getElementById('emp-department').value = data?.department || '';
  document.getElementById('emp-phone').value = data?.phone || '';
  document.getElementById('emp-email').value = data?.email || '';
  document.getElementById('emp-status').value = data?.status || 'active';
  openModal('employee-modal');
}

async function editEmployee(id) {
  try {
    const { data } = await apiFetch(`${API}/employees/${id}`);
    openEmployeeModal(data);
  } catch (err) { showToast(err.message, 'error'); }
}

async function saveEmployee(e) {
  e.preventDefault();
  const id = document.getElementById('employee-id').value;
  const payload = {
    name: document.getElementById('emp-name').value,
    nik: document.getElementById('emp-nik').value,
    position: document.getElementById('emp-position').value,
    department: document.getElementById('emp-department').value,
    phone: document.getElementById('emp-phone').value,
    email: document.getElementById('emp-email').value,
    status: document.getElementById('emp-status').value
  };
  try {
    if (id) {
      await apiFetch(`${API}/employees/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
      showToast('Karyawan berhasil diperbarui');
    } else {
      await apiFetch(`${API}/employees`, { method: 'POST', body: JSON.stringify(payload) });
      showToast('Karyawan berhasil ditambahkan');
    }
    closeModal('employee-modal');
    renderEmployees();
  } catch (err) { showToast(err.message, 'error'); }
}

function deleteEmployee(id, name) {
  confirmDelete(`Hapus karyawan "${name}"? Semua jadwal shift terkait juga akan dihapus.`, async () => {
    try {
      await apiFetch(`${API}/employees/${id}`, { method: 'DELETE' });
      showToast('Karyawan berhasil dihapus');
      renderEmployees();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

// ── Customers ───────────────────────────────────────────────
let customerSearch = '';
let customerStatusFilter = '';

async function renderCustomers() {
  const el = document.getElementById('page-content');
  try {
    let url = `${API}/customers?`;
    if (customerSearch) url += `search=${encodeURIComponent(customerSearch)}&`;
    if (customerStatusFilter) url += `status=${customerStatusFilter}`;
    const { data } = await apiFetch(url);
    el.innerHTML = `
      <div class="page-header">
        <h2>Pelanggan (${data.length})</h2>
        <button class="btn btn-primary" onclick="openCustomerModal()">+ Tambah Pelanggan</button>
      </div>
      <div class="card">
        <div class="card-body" style="padding-bottom:0">
          <div class="search-bar">
            <input class="search-input" type="text" placeholder="🔍 Cari nama, perusahaan, telepon..."
              value="${escapeHtml(customerSearch)}"
              oninput="customerSearch=this.value; debounce(renderCustomers, 400)()">
            <select class="filter-select" onchange="customerStatusFilter=this.value; renderCustomers()">
              <option value="" ${!customerStatusFilter?'selected':''}>Semua Status</option>
              <option value="active" ${customerStatusFilter==='active'?'selected':''}>Aktif</option>
              <option value="prospect" ${customerStatusFilter==='prospect'?'selected':''}>Prospek</option>
              <option value="inactive" ${customerStatusFilter==='inactive'?'selected':''}>Tidak Aktif</option>
            </select>
          </div>
        </div>
        <div class="table-wrap">
          ${data.length ? `
          <table>
            <thead><tr><th>#</th><th>Nama</th><th>Perusahaan</th><th>Telepon</th><th>Email</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              ${data.map((c, i) => `
                <tr>
                  <td class="text-muted">${i+1}</td>
                  <td><strong>${escapeHtml(c.name)}</strong></td>
                  <td>${escapeHtml(c.company)}</td>
                  <td>${escapeHtml(c.phone)}</td>
                  <td>${escapeHtml(c.email)}</td>
                  <td>${statusBadge(c.status)}</td>
                  <td>
                    <button class="btn btn-secondary btn-sm" onclick="editCustomer(${c.id})">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCustomer(${c.id}, '${escapeHtml(c.name)}')">🗑️</button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>` : `<div class="empty-state"><div class="icon">🏢</div><p>Belum ada data pelanggan</p></div>`}
        </div>
      </div>
    `;
  } catch (err) {
    el.innerHTML = `<div class="alert alert-danger">⚠️ ${escapeHtml(err.message)}</div>`;
  }
}

function openCustomerModal(data = null) {
  document.getElementById('customer-modal-title').textContent = data ? 'Edit Pelanggan' : 'Tambah Pelanggan';
  document.getElementById('customer-id').value = data?.id || '';
  document.getElementById('cust-name').value = data?.name || '';
  document.getElementById('cust-company').value = data?.company || '';
  document.getElementById('cust-phone').value = data?.phone || '';
  document.getElementById('cust-email').value = data?.email || '';
  document.getElementById('cust-address').value = data?.address || '';
  document.getElementById('cust-status').value = data?.status || 'active';
  document.getElementById('cust-notes').value = data?.notes || '';
  openModal('customer-modal');
}

async function editCustomer(id) {
  try {
    const { data } = await apiFetch(`${API}/customers/${id}`);
    openCustomerModal(data);
  } catch (err) { showToast(err.message, 'error'); }
}

async function saveCustomer(e) {
  e.preventDefault();
  const id = document.getElementById('customer-id').value;
  const payload = {
    name: document.getElementById('cust-name').value,
    company: document.getElementById('cust-company').value,
    phone: document.getElementById('cust-phone').value,
    email: document.getElementById('cust-email').value,
    address: document.getElementById('cust-address').value,
    status: document.getElementById('cust-status').value,
    notes: document.getElementById('cust-notes').value
  };
  try {
    if (id) {
      await apiFetch(`${API}/customers/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
      showToast('Pelanggan berhasil diperbarui');
    } else {
      await apiFetch(`${API}/customers`, { method: 'POST', body: JSON.stringify(payload) });
      showToast('Pelanggan berhasil ditambahkan');
    }
    closeModal('customer-modal');
    renderCustomers();
  } catch (err) { showToast(err.message, 'error'); }
}

function deleteCustomer(id, name) {
  confirmDelete(`Hapus pelanggan "${name}"?`, async () => {
    try {
      await apiFetch(`${API}/customers/${id}`, { method: 'DELETE' });
      showToast('Pelanggan berhasil dihapus');
      renderCustomers();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

// ── Shifts ──────────────────────────────────────────────────
let shiftMonthFilter = new Date().getMonth() + 1;
let shiftYearFilter = new Date().getFullYear();

async function renderShifts() {
  const el = document.getElementById('page-content');
  try {
    const url = `${API}/shifts?month=${shiftMonthFilter}&year=${shiftYearFilter}`;
    const { data } = await apiFetch(url);
    const monthName = new Date(shiftYearFilter, shiftMonthFilter - 1).toLocaleString('id-ID', { month: 'long', year: 'numeric' });
    el.innerHTML = `
      <div class="page-header">
        <h2>Jadwal Shift</h2>
        <button class="btn btn-primary" onclick="openShiftModal()">+ Tambah Jadwal</button>
      </div>
      <div class="card mb-2">
        <div class="card-body" style="padding:12px 20px">
          <div class="d-flex align-center gap-2">
            <button class="btn btn-secondary btn-sm" onclick="changeShiftMonth(-1)">◀</button>
            <strong>${monthName}</strong>
            <button class="btn btn-secondary btn-sm" onclick="changeShiftMonth(1)">▶</button>
            <button class="btn btn-secondary btn-sm" onclick="shiftMonthFilter=new Date().getMonth()+1;shiftYearFilter=new Date().getFullYear();renderShifts()">Bulan Ini</button>
            <span class="text-muted" style="font-size:0.85rem">(${data.length} jadwal)</span>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="table-wrap">
          ${data.length ? `
          <table>
            <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Departemen</th><th>Shift</th><th>Waktu</th><th>Pelanggan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
              ${data.map(s => `
                <tr>
                  <td>${formatDate(s.schedule_date)}</td>
                  <td><strong>${escapeHtml(s.employee_name)}</strong></td>
                  <td>${escapeHtml(s.department)}</td>
                  <td>${escapeHtml(s.shift_name)}</td>
                  <td><span class="text-muted">${s.start_time} - ${s.end_time}</span></td>
                  <td>${escapeHtml(s.customer_name)}</td>
                  <td>${statusBadge(s.status)}</td>
                  <td>
                    <button class="btn btn-secondary btn-sm" onclick="editShift(${s.id})">✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteShift(${s.id})">🗑️</button>
                  </td>
                </tr>`).join('')}
            </tbody>
          </table>` : `<div class="empty-state"><div class="icon">📅</div><p>Belum ada jadwal untuk bulan ini</p></div>`}
        </div>
      </div>
    `;
  } catch (err) {
    el.innerHTML = `<div class="alert alert-danger">⚠️ ${escapeHtml(err.message)}</div>`;
  }
}

function changeShiftMonth(delta) {
  shiftMonthFilter += delta;
  if (shiftMonthFilter > 12) { shiftMonthFilter = 1; shiftYearFilter++; }
  if (shiftMonthFilter < 1) { shiftMonthFilter = 12; shiftYearFilter--; }
  renderShifts();
}

async function openShiftModal(data = null, prefillDate = null) {
  document.getElementById('shift-modal-title').textContent = data ? 'Edit Jadwal Shift' : 'Tambah Jadwal Shift';
  document.getElementById('shift-schedule-id').value = data?.id || '';
  document.getElementById('shift-notes').value = data?.notes || '';
  document.getElementById('shift-status').value = data?.status || 'scheduled';

  // Set date
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('shift-date').value = data?.schedule_date || prefillDate || today;

  // Load employees
  const empSel = document.getElementById('shift-employee');
  empSel.innerHTML = '<option value="">-- Pilih Karyawan --</option>';
  const { data: employees } = await apiFetch(`${API}/employees?status=active`);
  employees.forEach(e => {
    empSel.innerHTML += `<option value="${e.id}" ${data?.employee_id == e.id ? 'selected' : ''}>${escapeHtml(e.name)} (${escapeHtml(e.department) || 'N/A'})</option>`;
  });

  // Load shift types
  const shiftSel = document.getElementById('shift-type');
  shiftSel.innerHTML = '<option value="">-- Pilih Shift --</option>';
  const { data: shiftTypes } = await apiFetch(`${API}/shifts/types`);
  shiftTypes.forEach(s => {
    shiftSel.innerHTML += `<option value="${s.id}" ${data?.shift_id == s.id ? 'selected' : ''}>${escapeHtml(s.name)} (${s.start_time}-${s.end_time})</option>`;
  });

  // Load customers
  const custSel = document.getElementById('shift-customer');
  custSel.innerHTML = '<option value="">-- Pilih Pelanggan (opsional) --</option>';
  const { data: customers } = await apiFetch(`${API}/customers`);
  customers.forEach(c => {
    custSel.innerHTML += `<option value="${c.id}" ${data?.customer_id == c.id ? 'selected' : ''}>${escapeHtml(c.name)}${c.company ? ` (${escapeHtml(c.company)})` : ''}</option>`;
  });

  openModal('shift-modal');
}

async function editShift(id) {
  try {
    const { data } = await apiFetch(`${API}/shifts/${id}`);
    await openShiftModal(data);
  } catch (err) { showToast(err.message, 'error'); }
}

async function saveShift(e) {
  e.preventDefault();
  const id = document.getElementById('shift-schedule-id').value;
  const payload = {
    employee_id: document.getElementById('shift-employee').value,
    shift_id: document.getElementById('shift-type').value,
    schedule_date: document.getElementById('shift-date').value,
    customer_id: document.getElementById('shift-customer').value || null,
    notes: document.getElementById('shift-notes').value,
    status: document.getElementById('shift-status').value
  };
  try {
    if (id) {
      await apiFetch(`${API}/shifts/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
      showToast('Jadwal berhasil diperbarui');
    } else {
      await apiFetch(`${API}/shifts`, { method: 'POST', body: JSON.stringify(payload) });
      showToast('Jadwal berhasil ditambahkan');
    }
    closeModal('shift-modal');
    if (currentPage === 'shifts') renderShifts();
    if (currentPage === 'calendar') renderCalendar();
  } catch (err) { showToast(err.message, 'error'); }
}

function deleteShift(id) {
  confirmDelete('Hapus jadwal shift ini?', async () => {
    try {
      await apiFetch(`${API}/shifts/${id}`, { method: 'DELETE' });
      showToast('Jadwal berhasil dihapus');
      if (currentPage === 'shifts') renderShifts();
      if (currentPage === 'calendar') renderCalendar();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

// ── Calendar ────────────────────────────────────────────────
let calMonth = new Date().getMonth() + 1;
let calYear = new Date().getFullYear();

async function renderCalendar() {
  const el = document.getElementById('page-content');
  try {
    const { data: schedules } = await apiFetch(`${API}/shifts?month=${calMonth}&year=${calYear}`);
    const monthName = new Date(calYear, calMonth - 1).toLocaleString('id-ID', { month: 'long', year: 'numeric' });
    const firstDay = new Date(calYear, calMonth - 1, 1).getDay();
    const daysInMonth = new Date(calYear, calMonth, 0).getDate();
    const today = new Date().toISOString().split('T')[0];

    // Build schedule map
    const scheduleMap = {};
    schedules.forEach(s => {
      if (!scheduleMap[s.schedule_date]) scheduleMap[s.schedule_date] = [];
      scheduleMap[s.schedule_date].push(s);
    });

    // Build calendar grid
    const dayHeaders = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    let cells = '';
    let day = 1;

    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) cells += '<div class="day-cell other-month"></div>';

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${calYear}-${String(calMonth).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const isToday = dateStr === today;
      const daySchedules = scheduleMap[dateStr] || [];
      cells += `
        <div class="day-cell ${isToday ? 'today' : ''}">
          <div class="day-number">${d}</div>
          ${daySchedules.slice(0, 3).map(s => `
            <div class="day-shift ${s.status}" title="${escapeHtml(s.employee_name)} - ${escapeHtml(s.shift_name)}" onclick="editShift(${s.id})">
              ${escapeHtml(s.employee_name?.split(' ')[0])}
            </div>`).join('')}
          ${daySchedules.length > 3 ? `<div class="text-muted" style="font-size:0.7rem">+${daySchedules.length - 3} lagi</div>` : ''}
          <div style="position:absolute;right:2px;bottom:2px;opacity:0.5;font-size:0.65rem" onclick="openShiftModal(null,'${dateStr}')" title="Tambah shift">+</div>
        </div>`;
      day++;
    }

    el.innerHTML = `
      <div class="page-header">
        <h2>Kalender Shift</h2>
        <button class="btn btn-primary" onclick="openShiftModal()">+ Tambah Jadwal</button>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="calendar-nav">
            <button onclick="changeCalMonth(-1)">◀</button>
            <strong style="min-width:180px;text-align:center">${monthName}</strong>
            <button onclick="changeCalMonth(1)">▶</button>
            <button class="btn btn-secondary btn-sm" onclick="calMonth=new Date().getMonth()+1;calYear=new Date().getFullYear();renderCalendar()">Bulan Ini</button>
          </div>
          <div class="d-flex gap-2">
            <span class="badge badge-primary">Terjadwal</span>
            <span class="badge badge-success">Selesai</span>
            <span class="badge badge-danger">Absen</span>
            <span class="badge badge-warning">Cuti</span>
          </div>
        </div>
        <div class="card-body">
          <div class="month-grid">
            ${dayHeaders.map(h => `<div class="day-header">${h}</div>`).join('')}
            ${cells}
          </div>
        </div>
      </div>
    `;
  } catch (err) {
    el.innerHTML = `<div class="alert alert-danger">⚠️ ${escapeHtml(err.message)}</div>`;
  }
}

function changeCalMonth(delta) {
  calMonth += delta;
  if (calMonth > 12) { calMonth = 1; calYear++; }
  if (calMonth < 1) { calMonth = 12; calYear--; }
  renderCalendar();
}

// ── Reports ─────────────────────────────────────────────────
let reportMonth = new Date().getMonth() + 1;
let reportYear = new Date().getFullYear();

async function renderReports() {
  const el = document.getElementById('page-content');
  try {
    const { data } = await apiFetch(`${API}/reports/monthly?month=${reportMonth}&year=${reportYear}`);
    const monthName = new Date(reportYear, reportMonth - 1).toLocaleString('id-ID', { month: 'long', year: 'numeric' });
    el.innerHTML = `
      <div class="page-header">
        <h2>Laporan Bulanan</h2>
        <div class="d-flex gap-2">
          <button class="btn btn-secondary" onclick="changeReportMonth(-1)">◀</button>
          <strong class="d-flex align-center">${monthName}</strong>
          <button class="btn btn-secondary" onclick="changeReportMonth(1)">▶</button>
        </div>
      </div>
      <div class="card mb-2">
        <div class="card-header"><h3>📊 Rekapitulasi Karyawan - ${monthName}</h3></div>
        <div class="table-wrap">
          ${data.byEmployee.length ? `
          <table>
            <thead><tr><th>Nama</th><th>Departemen</th><th>Total Shift</th><th>Selesai</th><th>Tidak Hadir</th><th>Cuti</th></tr></thead>
            <tbody>
              ${data.byEmployee.map(e => `
                <tr>
                  <td><strong>${escapeHtml(e.name)}</strong></td>
                  <td>${escapeHtml(e.department)}</td>
                  <td><strong>${e.total_shifts}</strong></td>
                  <td><span class="badge badge-success">${e.completed}</span></td>
                  <td><span class="badge badge-danger">${e.absent}</span></td>
                  <td><span class="badge badge-warning">${e.leave_count}</span></td>
                </tr>`).join('')}
            </tbody>
          </table>` : '<div class="empty-state"><p>Belum ada data karyawan</p></div>'}
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3>📋 Detail Jadwal - ${monthName} (${data.schedules.length} jadwal)</h3></div>
        <div class="table-wrap">
          ${data.schedules.length ? `
          <table>
            <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Shift</th><th>Waktu</th><th>Pelanggan</th><th>Status</th></tr></thead>
            <tbody>
              ${data.schedules.map(s => `
                <tr>
                  <td>${formatDate(s.schedule_date)}</td>
                  <td>${escapeHtml(s.employee_name)}</td>
                  <td>${escapeHtml(s.shift_name)}</td>
                  <td class="text-muted">${s.start_time} - ${s.end_time}</td>
                  <td>${escapeHtml(s.customer_name)}</td>
                  <td>${statusBadge(s.status)}</td>
                </tr>`).join('')}
            </tbody>
          </table>` : '<div class="empty-state"><div class="icon">📊</div><p>Belum ada jadwal untuk bulan ini</p></div>'}
        </div>
      </div>
    `;
  } catch (err) {
    el.innerHTML = `<div class="alert alert-danger">⚠️ ${escapeHtml(err.message)}</div>`;
  }
}

function changeReportMonth(delta) {
  reportMonth += delta;
  if (reportMonth > 12) { reportMonth = 1; reportYear++; }
  if (reportMonth < 1) { reportMonth = 12; reportYear--; }
  renderReports();
}

// ── Debounce ────────────────────────────────────────────────
const debounceTimers = {};
function debounce(fn, delay) {
  return function(...args) {
    clearTimeout(debounceTimers[fn.name]);
    debounceTimers[fn.name] = setTimeout(() => fn(...args), delay);
  };
}

// ── Init ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Set current date in header
  document.getElementById('current-date').textContent =
    new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  // Close modals on backdrop click
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // Responsive sidebar toggle
  const mq = window.matchMedia('(max-width: 768px)');
  const handleMediaChange = (e) => {
    document.getElementById('menu-toggle').style.display = e.matches ? 'flex' : 'none';
  };
  mq.addEventListener('change', handleMediaChange);
  handleMediaChange(mq);

  navigate('dashboard');
});
