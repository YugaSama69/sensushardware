# Sistem Sensus Hardware & Inventaris Elektronik

Aplikasi web inventaris berbasis PHP native + MySQL untuk mencatat data barang, ruangan, transaksi stok masuk/keluar, histori, laporan, dan export.

## Fitur

- Login admin dengan session
- Dashboard ringkasan inventaris
- CRUD data barang dan ruangan
- Transaksi barang masuk dan keluar dengan histori stok
- Alert stok menipis
- History lengkap dan laporan filter
- Export Excel, Export PDF sederhana, dan print
- Bootstrap 5, DataTables, responsive
- Pendataan komputer client via PowerShell agent

## Struktur Folder

```text
sensushardware/
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── api/
│   └── komputer_client.php
├── client-agent/
│   ├── pendataan_komputer.bat
│   └── scan-komputer.ps1
├── config/
│   ├── app.php
│   └── database.php
├── database/
│   └── sensus_hardware.sql
├── includes/
│   ├── auth.php
│   ├── helpers.php
│   ├── layout_bottom.php
│   └── layout_top.php
├── modules/
│   ├── barang/index.php
│   ├── komputer/
│   │   ├── export_excel.php
│   │   └── index.php
│   ├── dashboard/index.php
│   ├── laporan/
│   │   ├── export_excel.php
│   │   ├── export_pdf.php
│   │   ├── index.php
│   │   └── print.php
│   ├── ruangan/index.php
│   └── transaksi/
│       ├── history.php
│       ├── keluar.php
│       └── masuk.php
├── pendataan/
│   ├── download_agent.php
│   └── index.php
├── index.php
├── login.php
├── logout.php
└── README.md
```

## Cara Install di XAMPP

1. Simpan project di `C:\xampp\htdocs\sensushardware`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Buat database `sensus_hardware` jika belum ada.
5. Import file [database/sensus_hardware.sql](/c:/xampp/htdocs/sensushardware/database/sensus_hardware.sql).
6. Sesuaikan koneksi database di [config/database.php](/c:/xampp/htdocs/sensushardware/config/database.php) jika username/password MySQL berbeda.
7. Akses aplikasi di `http://localhost/sensushardware`.

Jika aplikasi sudah pernah diinstall sebelum modul pendataan komputer ditambahkan, import file tambahan [database/komputer_client.sql](/c:/xampp/htdocs/sensushardware/database/komputer_client.sql) untuk membuat tabel `komputer_client`.

## Login Default

- Username: `admin`
- Password: `admin123`

## Catatan Pengembangan

- Semua query utama menggunakan PDO prepared statement.
- Routing masih sederhana berbasis file PHP sehingga mudah dikembangkan.
- Export PDF dibuat tanpa library tambahan agar tetap siap pakai di XAMPP standar.
- Asset Bootstrap 5 dan DataTables menggunakan CDN.

## Catatan Ruangan Barang

- Master `Data Barang` tidak lagi mengunci barang ke satu ruangan.
- Ruangan sekarang dipilih saat transaksi `Barang Masuk` dan `Barang Keluar`.
- Riwayat transaksi menyimpan snapshot ruangan pada saat transaksi dibuat.

Jika aplikasi sudah terpasang sebelumnya, jalankan migrasi [database/migrate_barang_tanpa_ruangan.sql](/c:/xampp/htdocs/sensushardware/database/migrate_barang_tanpa_ruangan.sql) agar histori lama mengambil snapshot ruangan dan relasi barang ke ruangan dilepas.

## Pendataan Komputer Client

Halaman client:

```text
http://IP-SERVER/sensushardware/pendataan
```

Contoh sesuai IP PC saat ini:

```text
http://192.168.2.69/sensushardware/pendataan
```

Cara pakai:

1. Client membuka halaman `/pendataan`.
2. Pilih ruangan, isi tahun inventaris, dan isi nama user.
3. Klik tombol `Pendataan Komputer Ini`.
4. Browser akan mengunduh `pendataan-komputer-rs.bat`.
5. Jalankan file BAT tersebut di komputer client.
6. PowerShell membaca spesifikasi perangkat via WMI/CIM, membawa tahun inventaris dan nama user dari form, lalu mengirim data ke `api/komputer_client.php`.
7. Admin dapat melihat data di menu `Komputer Client`.

Catatan penting: browser modern tidak boleh menjalankan BAT/PowerShell otomatis langsung dari halaman web tanpa izin pengguna. Ini adalah batasan keamanan Windows/browser. Karena itu sistem memakai launcher BAT yang diunduh dan dijalankan oleh petugas/client.

Script manual juga tersedia di:

- [client-agent/scan-komputer.ps1](/c:/xampp/htdocs/sensushardware/client-agent/scan-komputer.ps1)
- [client-agent/pendataan_komputer.bat](/c:/xampp/htdocs/sensushardware/client-agent/pendataan_komputer.bat)

## Cara Jalankan di LAN Rumah Sakit

1. Pastikan Apache dan MySQL XAMPP aktif di PC server.
2. Pastikan PC client satu jaringan dengan server.
3. Akses dari client memakai IP server, misalnya `http://192.168.2.69/sensushardware`.
4. Jika tidak bisa diakses, izinkan Apache HTTP Server di Windows Firewall atau buka port `80`.
5. Untuk pendataan client, buka `http://192.168.2.69/sensushardware/pendataan`.
