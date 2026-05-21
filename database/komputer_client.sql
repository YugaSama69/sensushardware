USE sensus_hardware;

CREATE TABLE IF NOT EXISTS komputer_client (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(100) NOT NULL,
    device_type ENUM('CLIENT', 'SERVER') NOT NULL DEFAULT 'CLIENT',
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
    serial_number VARCHAR(150) NULL,
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
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_komputer_identity (hostname, mac_address),
    UNIQUE KEY uq_komputer_mac_address (mac_address),
    INDEX idx_komputer_device_type (device_type),
    INDEX idx_komputer_ip_address (ip_address),
    INDEX idx_komputer_ruangan (ruangan),
    INDEX idx_komputer_tanggal (tanggal),
    INDEX idx_komputer_tahun_inventaris (tahun_inventaris)
);

ALTER TABLE komputer_client
    ADD COLUMN IF NOT EXISTS tahun_inventaris YEAR NULL AFTER architecture;

ALTER TABLE komputer_client
    ADD COLUMN IF NOT EXISTS kondisi ENUM('Baik', 'Rusak', 'Perbaikan') NOT NULL DEFAULT 'Baik' AFTER tahun_inventaris;

UPDATE komputer_client
SET kondisi = 'Baik'
WHERE kondisi IS NULL OR kondisi = '';

CREATE TABLE IF NOT EXISTS server_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    komputer_id INT NOT NULL,
    virtualization VARCHAR(120) NULL,
    raid VARCHAR(120) NULL,
    hypervisor VARCHAR(120) NULL,
    uptime VARCHAR(120) NULL,
    total_thread INT NULL,
    multiple_nic TEXT NULL,
    multiple_ip TEXT NULL,
    domain_joined VARCHAR(10) NULL,
    server_role VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_server_detail_komputer (komputer_id),
    INDEX idx_server_detail_role (server_role),
    INDEX idx_server_detail_virtualization (virtualization)
);

CREATE TABLE IF NOT EXISTS client_ip_addresses (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    adapter_name VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_ip_address (client_id, ip_address),
    INDEX idx_client_ip_client (client_id),
    INDEX idx_client_ip_status (status),
    INDEX idx_client_ip_address (ip_address)
);
