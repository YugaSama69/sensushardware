CREATE DATABASE IF NOT EXISTS sensus_hardware CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sensus_hardware;

DROP TABLE IF EXISTS histori_barang;
DROP TABLE IF EXISTS barang;
DROP TABLE IF EXISTS komputer_client;
DROP TABLE IF EXISTS ruangan;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL
);

CREATE TABLE ruangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_ruangan VARCHAR(100) NOT NULL,
    lokasi VARCHAR(150) NOT NULL,
    penanggung_jawab VARCHAR(100) NOT NULL
);

CREATE TABLE barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_barang VARCHAR(50) NOT NULL UNIQUE,
    nama_barang VARCHAR(150) NOT NULL,
    ruangan_id INT NOT NULL,
    tahun_inventaris YEAR NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    kondisi ENUM('Baik', 'Rusak', 'Perbaikan') NOT NULL DEFAULT 'Baik',
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_barang_ruangan FOREIGN KEY (ruangan_id) REFERENCES ruangan(id) ON UPDATE CASCADE
);

CREATE TABLE histori_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id INT NOT NULL,
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
    ruangan VARCHAR(100) NOT NULL,
    petugas VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_komputer_identity (hostname, mac_address),
    INDEX idx_komputer_ruangan (ruangan),
    INDEX idx_komputer_tanggal (tanggal)
);

INSERT INTO users (username, password, nama) VALUES
('admin', '$2y$10$acAk9s9I27.9zsHDSx/P6OTRv.jAy/YVtYDBTPZCFe/GM/0k25gnW', 'Administrator');

INSERT INTO ruangan (nama_ruangan, lokasi, penanggung_jawab) VALUES
('Lab Komputer', 'Lantai 2 Gedung A', 'Budi Santoso'),
('Ruang Server', 'Lantai 1 Gedung A', 'Rina Lestari'),
('Ruang Multimedia', 'Lantai 3 Gedung B', 'Ahmad Fadli');

INSERT INTO barang (kode_barang, nama_barang, ruangan_id, tahun_inventaris, qty, kondisi, keterangan) VALUES
('BRG-0001', 'PC All in One Lenovo', 1, 2024, 12, 'Baik', 'Digunakan untuk praktikum siswa.'),
('BRG-0002', 'Router Mikrotik RB750', 2, 2023, 4, 'Baik', 'Perangkat distribusi jaringan utama.'),
('BRG-0003', 'Proyektor Epson EB-X06', 3, 2022, 3, 'Perbaikan', 'Satu unit sedang servis ringan.');

INSERT INTO histori_barang (barang_id, tipe_transaksi, qty, stok_sebelum, stok_sesudah, nama_pengguna, tujuan, keterangan, tanggal, jam) VALUES
(1, 'masuk', 12, 0, 12, 'Administrator', '-', 'Input stok awal inventaris.', CURDATE(), CURTIME()),
(2, 'masuk', 4, 0, 4, 'Administrator', '-', 'Input stok awal inventaris.', CURDATE(), CURTIME()),
(3, 'masuk', 3, 0, 3, 'Administrator', '-', 'Input stok awal inventaris.', CURDATE(), CURTIME());
