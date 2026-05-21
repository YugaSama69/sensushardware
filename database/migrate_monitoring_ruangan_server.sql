USE sensus_hardware;

CREATE TABLE IF NOT EXISTS monitoring_master_ruangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_ruangan VARCHAR(120) NOT NULL UNIQUE,
    lokasi VARCHAR(180) NOT NULL,
    status_aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_monitoring_master_ruangan_status (status_aktif)
);

CREATE TABLE IF NOT EXISTS monitoring_master_petugas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(120) NOT NULL,
    nip_nik VARCHAR(50) NOT NULL UNIQUE,
    jabatan VARCHAR(120) NOT NULL,
    status_aktif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_monitoring_master_petugas_status (status_aktif)
);

CREATE TABLE IF NOT EXISTS monitoring_ruangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    jam_monitoring TIME NOT NULL,
    ruangan_id INT NOT NULL,
    suhu ENUM('20_21', 'gt_20_21') NOT NULL,
    akses_masuk ENUM('terkunci', 'tidak_terkunci') NOT NULL,
    petugas_id INT NOT NULL,
    catatan TEXT NULL,
    signature_base64 LONGTEXT NULL,
    status ENUM('normal', 'warning', 'kritikal') NOT NULL DEFAULT 'normal',
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    device_info VARCHAR(255) NULL,
    CONSTRAINT fk_monitoring_ruangan_master_ruangan FOREIGN KEY (ruangan_id) REFERENCES monitoring_master_ruangan(id) ON UPDATE CASCADE,
    CONSTRAINT fk_monitoring_ruangan_master_petugas FOREIGN KEY (petugas_id) REFERENCES monitoring_master_petugas(id) ON UPDATE CASCADE,
    INDEX idx_monitoring_tanggal (tanggal),
    INDEX idx_monitoring_ruangan_id (ruangan_id),
    INDEX idx_monitoring_petugas_id (petugas_id),
    INDEX idx_monitoring_status (status)
);

INSERT IGNORE INTO monitoring_master_ruangan (nama_ruangan, lokasi, status_aktif) VALUES
('Ruang Server Utama', 'Gedung Utama - Lantai 1', 1),
('Ruang NOC', 'Gedung Utama - Lantai 2', 1),
('Ruang UPS', 'Gedung Utilitas', 1),
('Data Center', 'Gedung Data Center', 1);

INSERT IGNORE INTO monitoring_master_petugas (nama_lengkap, nip_nik, jabatan, status_aktif) VALUES
('Petugas Monitoring 1', 'MON-001', 'Staf IT', 1),
('Petugas Monitoring 2', 'MON-002', 'NOC Engineer', 1),
('Petugas Monitoring 3', 'MON-003', 'Teknisi Infrastruktur', 1);
