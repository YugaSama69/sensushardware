CREATE DATABASE IF NOT EXISTS sensus_hardware CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sensus_hardware;

DROP TABLE IF EXISTS histori_barang;
DROP TABLE IF EXISTS mutasi_komputer;
DROP TABLE IF EXISTS master_label_barang;
DROP TABLE IF EXISTS barang;
DROP TABLE IF EXISTS master_bidang_unit_pengembangan;
DROP TABLE IF EXISTS laporan_pengembangan_aplikasi;
DROP TABLE IF EXISTS komputer_client;
DROP TABLE IF EXISTS ruangan;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin', 'pengembangan') NOT NULL DEFAULT 'admin'
);

CREATE TABLE ruangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_ruangan VARCHAR(100) NOT NULL,
    lokasi VARCHAR(150) NOT NULL,
    penanggung_jawab VARCHAR(100) NOT NULL
);

CREATE TABLE master_label_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_label VARCHAR(100) NOT NULL UNIQUE,
    warna_label CHAR(7) NOT NULL DEFAULT '#64748B',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_barang VARCHAR(50) NOT NULL UNIQUE,
    nama_barang VARCHAR(150) NOT NULL,
    ruangan_id INT NULL,
    tahun_inventaris YEAR NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    label_barang VARCHAR(100) NOT NULL DEFAULT 'Lainnya',
    kondisi ENUM('Baik', 'Rusak', 'Perbaikan') NOT NULL DEFAULT 'Baik',
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_barang_ruangan FOREIGN KEY (ruangan_id) REFERENCES ruangan(id) ON UPDATE CASCADE
);

CREATE TABLE histori_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id INT NOT NULL,
    ruangan_id INT NULL,
    ruangan_nama VARCHAR(100) NULL,
    tipe_transaksi ENUM('masuk', 'keluar') NOT NULL,
    qty INT NOT NULL,
    stok_sebelum INT NOT NULL,
    stok_sesudah INT NOT NULL,
    nama_pengguna VARCHAR(100) NOT NULL,
    tujuan VARCHAR(255) NULL,
    keterangan TEXT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_histori_barang FOREIGN KEY (barang_id) REFERENCES barang(id) ON UPDATE CASCADE
);

CREATE TABLE komputer_client (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(100) NOT NULL,
    username VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    mac_address VARCHAR(50) NOT NULL,
    merk VARCHAR(150) NULL,
    model VARCHAR(150) NULL,
    processor VARCHAR(255) NULL,
    core INT NULL,
    ram VARCHAR(50) NULL,
    ssd TEXT NULL,
    hdd TEXT NULL,
    vga TEXT NULL,
    motherboard VARCHAR(255) NULL,
    os_name VARCHAR(150) NULL,
    os_version VARCHAR(100) NULL,
    architecture VARCHAR(50) NULL,
    tahun_inventaris YEAR NULL,
    kondisi ENUM('Baik', 'Rusak', 'Perbaikan') NOT NULL DEFAULT 'Baik',
    ruangan VARCHAR(100) NOT NULL,
    petugas VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_komputer_identity (hostname, mac_address),
    INDEX idx_komputer_ruangan (ruangan),
    INDEX idx_komputer_tanggal (tanggal)
);

CREATE TABLE mutasi_komputer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id INT NOT NULL,
    histori_barang_id INT NOT NULL,
    ruangan_id INT NOT NULL,
    ruangan_nama VARCHAR(100) NOT NULL,
    komputer_client_id INT NULL,
    hostname_referensi VARCHAR(100) NULL,
    jenis_mutasi ENUM('komputer_baru', 'pergantian_komputer_rusak') NOT NULL,
    qty INT NOT NULL,
    stok_sebelum INT NOT NULL,
    stok_sesudah INT NOT NULL,
    nama_petugas VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mutasi_histori (histori_barang_id),
    INDEX idx_mutasi_barang (barang_id),
    INDEX idx_mutasi_ruangan (ruangan_id),
    INDEX idx_mutasi_tanggal (tanggal)
);

CREATE TABLE master_bidang_unit_pengembangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_unit VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE laporan_pengembangan_aplikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulan_tahun DATE NOT NULL,
    nama_kegiatan VARCHAR(255) NOT NULL,
    bidang_unit VARCHAR(150) NOT NULL,
    keterangan TEXT NULL,
    capaian TEXT NOT NULL,
    input_user VARCHAR(100) NOT NULL DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, nama, role) VALUES
('admin', '$2y$10$acAk9s9I27.9zsHDSx/P6OTRv.jAy/YVtYDBTPZCFe/GM/0k25gnW', 'Administrator', 'admin'),
('fadli', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'fadli', 'pengembangan'),
('rian', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'rian', 'pengembangan'),
('fahmi', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'fahmi', 'pengembangan'),
('chris', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'chris', 'pengembangan'),
('amal', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'amal', 'pengembangan'),
('akbar', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'akbar', 'pengembangan'),
('noval', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'noval', 'pengembangan'),
('temy', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'temy', 'pengembangan'),
('deni', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'deni', 'pengembangan'),
('aisriasmara', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'aisriasmara', 'admin'),
('yuga', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'yuga', 'admin'),
('bayu', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'bayu', 'admin'),
('fachrie', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'fachrie', 'admin'),
('manaf', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'manaf', 'admin');

INSERT INTO ruangan (nama_ruangan, lokasi, penanggung_jawab) VALUES
('Lab Komputer', 'Lantai 2 Gedung A', 'Budi Santoso'),
('Ruang Server', 'Lantai 1 Gedung A', 'Rina Lestari'),
('Ruang Multimedia', 'Lantai 3 Gedung B', 'Ahmad Fadli');

INSERT INTO master_bidang_unit_pengembangan (nama_unit) VALUES
('IT / SIMRS'),
('IT / Manajemen');

INSERT INTO master_label_barang (nama_label, warna_label) VALUES
('PC Desktop', '#2563EB'),
('AIO', '#0F766E'),
('Laptop', '#7C3AED'),
('Printer', '#B45309'),
('Monitor', '#0891B2'),
('Network', '#1D4ED8'),
('Sparepart', '#64748B'),
('Peripheral', '#059669'),
('Lainnya', '#475569');

INSERT INTO barang (id, kode_barang, nama_barang, ruangan_id, tahun_inventaris, qty, label_barang, kondisi, keterangan) VALUES
(20, 'RAMPC-001', 'RAM Samsung 8 GB 2Rx8 PC3-12800U', NULL, YEAR(CURDATE()), 6, 'Sparepart', 'Baik', NULL),
(21, 'UTL-001', 'USB to LAN BAFO', NULL, YEAR(CURDATE()), 3, 'Peripheral', 'Baik', NULL),
(22, 'NVME-001', 'NVME Hyper VGEN 256 GB', NULL, YEAR(CURDATE()), 3, 'Sparepart', 'Baik', NULL),
(23, 'UAW-001', 'USB Adapter Wi-Fi 6 TP-Link AX1800', NULL, YEAR(CURDATE()), 2, 'Peripheral', 'Baik', NULL),
(24, 'UAB-001', 'USB Adapter Bluetooth TP-Link UB500', NULL, YEAR(CURDATE()), 3, 'Peripheral', 'Baik', NULL),
(25, 'UAW-002', 'USB Adapter Wi-Fi 5 D-Link DW-131', NULL, YEAR(CURDATE()), 8, 'Peripheral', 'Baik', NULL),
(26, 'HDDLP-001', 'Harddisk Laptop 1 TB - WD Blue', NULL, YEAR(CURDATE()), 2, 'Sparepart', 'Baik', NULL),
(27, 'RAMPC-002', 'RAM CORSAIR VENGEANCE 1x16 16 GB PC 4', NULL, YEAR(CURDATE()), 2, 'Sparepart', 'Baik', NULL),
(28, 'WL-001', 'Webcam Logitech - BRIO 100', NULL, YEAR(CURDATE()), 4, 'Peripheral', 'Baik', NULL),
(29, 'RAMLP-001', 'RAM Laptop Samsung Sodim 4 GB 1Rx8 PC4', NULL, YEAR(CURDATE()), 3, 'Sparepart', 'Baik', NULL),
(30, 'RAMLP-002', 'RAM Laptop Kingstone Sodim 4 GB PC 4', NULL, YEAR(CURDATE()), 2, 'Sparepart', 'Baik', NULL),
(31, 'RAMLP-003', 'RAM Laptop corsair Sodim 4 GB PC 4', NULL, YEAR(CURDATE()), 2, 'Sparepart', 'Baik', NULL),
(32, 'TP-001', 'Thermal Pad', NULL, YEAR(CURDATE()), 7, 'Sparepart', 'Baik', NULL),
(33, 'ECU-001', 'Expansion Card USB 3.0 - Orico', NULL, YEAR(CURDATE()), 2, 'Sparepart', 'Baik', NULL),
(34, 'UCTHA-001', 'USB C to HDMI A - Vention', NULL, YEAR(CURDATE()), 3, 'Peripheral', 'Baik', NULL),
(35, 'PCIEC-001', 'PCI-Express Card USB 3.0 - Vention', NULL, YEAR(CURDATE()), 5, 'Sparepart', 'Baik', NULL),
(36, 'RBM-001', 'Routerboard Mikrotik - RB450Gx4', NULL, YEAR(CURDATE()), 1, 'Network', 'Baik', NULL),
(37, 'PCIEN-001', 'PCI-Express Network Adapter - TPLINK', NULL, YEAR(CURDATE()), 5, 'Sparepart', 'Baik', NULL),
(38, 'KMW-001', 'Keyboard Mouse Wireless - Logitech MK540', NULL, YEAR(CURDATE()), 3, 'Peripheral', 'Baik', NULL),
(39, 'WR-001', 'Wireless Router - Ruijie RG-EW1200', NULL, YEAR(CURDATE()), 9, 'Network', 'Baik', NULL),
(40, 'RJ', 'RJ 45 Belden', NULL, YEAR(CURDATE()), 20, 'Sparepart', 'Baik', NULL);

INSERT INTO laporan_pengembangan_aplikasi (bulan_tahun, nama_kegiatan, bidang_unit, keterangan, capaian, input_user) VALUES
(DATE_FORMAT(CURDATE(), '%Y-%m-01'), 'Pengembangan Sistem Inventaris SIINTEL', 'IT / SIMRS', 'Pengembangan modul inventaris inti telah selesai.', '100', 'Administrator'),
(DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01'), 'Evaluasi Kebutuhan Pengembangan Aplikasi', 'IT / Manajemen', NULL, '85', 'Administrator');
