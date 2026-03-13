# Shift CRM

Aplikasi manajemen shift karyawan dan CRM (Customer Relationship Management) berbasis web.

## Fitur

- **Dashboard** – Statistik ringkas: karyawan aktif, total pelanggan, jadwal hari ini, dan aktivitas terbaru
- **Manajemen Karyawan** – Tambah, edit, hapus, dan cari data karyawan
- **Manajemen Pelanggan** – Kelola data pelanggan/klien dengan status (aktif, prospek, tidak aktif)
- **Jadwal Shift** – Atur jadwal shift karyawan dengan tipe shift pagi/siang/malam
- **Kalender Shift** – Tampilan kalender bulanan untuk melihat jadwal shift
- **Laporan** – Rekap bulanan per karyawan: total shift, selesai, tidak hadir, dan cuti

## Teknologi

- **Backend**: Node.js + Express
- **Database**: SQLite (via better-sqlite3)
- **Frontend**: HTML5 + CSS3 + Vanilla JavaScript (SPA)

## Instalasi

```bash
# Clone repositori
git clone https://github.com/herysoelistiono-create/Adv-report.git
cd Adv-report

# Install dependensi
npm install

# Salin file konfigurasi
cp .env.example .env

# Jalankan aplikasi
npm start
```

Aplikasi akan berjalan di `http://localhost:3000`

## Konfigurasi

Edit file `.env` untuk mengubah konfigurasi:

```env
PORT=3000
NODE_ENV=development
DB_PATH=./shift_crm.db
```

## Struktur Proyek

```
├── server.js           # Entry point aplikasi
├── src/
│   ├── models/
│   │   └── db.js       # Inisialisasi database SQLite
│   └── routes/
│       ├── employees.js  # API karyawan
│       ├── customers.js  # API pelanggan
│       ├── shifts.js     # API jadwal shift
│       └── reports.js    # API laporan & dashboard
└── public/
    ├── index.html        # Halaman utama SPA
    ├── css/style.css     # Stylesheet
    └── js/app.js         # Logic frontend
```

## API Endpoints

| Method | URL | Keterangan |
|--------|-----|------------|
| GET | `/api/employees` | Daftar karyawan |
| POST | `/api/employees` | Tambah karyawan |
| PUT | `/api/employees/:id` | Update karyawan |
| DELETE | `/api/employees/:id` | Hapus karyawan |
| GET | `/api/customers` | Daftar pelanggan |
| POST | `/api/customers` | Tambah pelanggan |
| PUT | `/api/customers/:id` | Update pelanggan |
| DELETE | `/api/customers/:id` | Hapus pelanggan |
| GET | `/api/shifts` | Daftar jadwal shift |
| POST | `/api/shifts` | Tambah jadwal |
| PUT | `/api/shifts/:id` | Update jadwal |
| DELETE | `/api/shifts/:id` | Hapus jadwal |
| GET | `/api/shifts/types` | Tipe-tipe shift |
| GET | `/api/reports/summary` | Ringkasan dashboard |
| GET | `/api/reports/monthly` | Laporan bulanan |

