<h1 align="center">📚 E-Absensi</h1>
<p align="center">
  <b>Sistem Absensi Online Sekolah Berbasis Web</b>
</p>

<p align="center">
  Aplikasi absensi online berbasis web untuk membantu sekolah mengelola kehadiran siswa secara lebih cepat, rapi, dan modern.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-Native-777BB4?logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-Database-4479A1?logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?logo=javascript&logoColor=black" />
  <img src="https://img.shields.io/badge/Status-Active-success" />
  <img src="https://img.shields.io/badge/Open%20Source-Yes-brightgreen" />
</p>

---

## ✨ Tentang Project

**E-Absensi** adalah sistem absensi online sekolah berbasis web yang dirancang untuk mempermudah proses pencatatan kehadiran siswa.

Project ini mendukung **multi-role user** untuk **Admin**, **Guru**, dan **Siswa**, serta dilengkapi dengan fitur **QR Code**, **scan absensi masuk/pulang**, dan **export laporan** agar pengelolaan absensi menjadi lebih efisien.

---

## 🚀 Fitur Utama

- 🔐 Login terpisah untuk **Siswa** dan **Guru/Admin**
- 👥 Dashboard sesuai **role pengguna**
- 🏫 Manajemen data **Siswa**, **Guru**, dan **Kelas**
- 🪪 **Kartu digital siswa** dengan **QR Code**
- 📷 **Scan absensi** masuk dan pulang
- ⏰ Penentuan status **Hadir**, **Terlambat**, dan **Pulang**
- 📊 Rekap data kehadiran
- 📤 Export laporan ke **XLS** dan **CSV**
- 🛡️ Validasi login dengan **CAPTCHA**
- 🚫 Pembatasan percobaan login untuk meningkatkan keamanan

---

## 🖼️ Tampilan Aplikasi

<p align="center">
  <img src="./Screenshot%20Web%20Absensi%20Online/Screenshot%202026-04-06%20173646.png" width="45%" />
  <img src="./Screenshot%20Web%20Absensi%20Online/Screenshot%202026-04-06%20173716.png" width="45%" />
</p>

<p align="center">
  <img src="./Screenshot%20Web%20Absensi%20Online/Screenshot%202026-04-06%20181958.png" width="45%" />
  <img src="./Screenshot%20Web%20Absensi%20Online/Screenshot%202026-04-06%20181508.png" width="45%" />
</p>

---

## 👤 Role Pengguna

### 👑 Admin
Admin memiliki akses penuh untuk:
- Mengelola data guru
- Mengelola data siswa
- Mengelola data kelas
- Mengelola absensi
- Melakukan scan absensi
- Melihat laporan dan export data

### 👨‍🏫 Guru
Guru dapat:
- Melihat dashboard guru
- Memantau absensi siswa
- Melakukan scan absensi siswa
- Melihat data absensi

### 🎓 Siswa
Siswa dapat:
- Login ke dashboard siswa
- Melihat kartu digital
- Menampilkan QR Code untuk proses absensi

---

## 🛠️ Teknologi yang Digunakan

- 🐘 **PHP Native**
- 🗄️ **MySQL / MariaDB**
- 🌐 **HTML**
- 🎨 **CSS**
- ⚡ **JavaScript**
- 🔒 **Session Authentication**
- ✅ **CAPTCHA**
- 📷 **QR Code**

---

## 📁 Struktur Folder

```bash
absensi-online/
├── admin/                # Modul admin
├── assets/               # File CSS, JS, gambar, dan aset frontend
├── config/               # Konfigurasi aplikasi dan database
├── database/             # File SQL database
├── guru/                 # Modul guru
├── includes/             # Helper, auth, template, dan fungsi umum
├── scan/                 # Proses scan absensi
├── siswa/                # Modul siswa
├── upload/siswa/         # Upload foto siswa
├── index.php             # Redirect dashboard berdasarkan role
├── login.php             # Halaman login
└── logout.php            # Logout session
