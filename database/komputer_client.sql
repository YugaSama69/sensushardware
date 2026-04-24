USE sensus_hardware;

CREATE TABLE IF NOT EXISTS komputer_client (
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

ALTER TABLE komputer_client
    ADD COLUMN IF NOT EXISTS tahun_inventaris YEAR NULL AFTER architecture;
