USE sensus_hardware;

CREATE TABLE IF NOT EXISTS inventory_launcher_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    payload_encrypted LONGTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_ip VARCHAR(45) NULL,
    request_user_agent VARCHAR(255) NULL,
    download_ip VARCHAR(45) NULL,
    UNIQUE KEY uq_inventory_launcher_token_hash (token_hash),
    INDEX idx_inventory_launcher_expiry (expires_at),
    INDEX idx_inventory_launcher_used_at (used_at)
);

CREATE TABLE IF NOT EXISTS server_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    komputer_id INT NOT NULL,
    virtualization VARCHAR(120) NULL,
    raid VARCHAR(120) NULL,
    hypervisor VARCHAR(120) NULL,
    uptime VARCHAR(120) NULL,
    multiple_ip TEXT NULL,
    server_role VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_server_detail_komputer (komputer_id),
    INDEX idx_server_detail_role (server_role),
    INDEX idx_server_detail_virtualization (virtualization)
);

SET @has_jenis_server := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'server_detail'
      AND COLUMN_NAME = 'jenis_server'
);
SET @sql := IF(
    @has_jenis_server = 0,
    "ALTER TABLE server_detail ADD COLUMN jenis_server VARCHAR(120) NULL AFTER server_role",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fungsi_server := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'server_detail'
      AND COLUMN_NAME = 'fungsi_server'
);
SET @sql := IF(
    @has_fungsi_server = 0,
    "ALTER TABLE server_detail ADD COLUMN fungsi_server VARCHAR(150) NULL AFTER jenis_server",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_virtual_fisik := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'server_detail'
      AND COLUMN_NAME = 'virtual_fisik'
);
SET @sql := IF(
    @has_virtual_fisik = 0,
    "ALTER TABLE server_detail ADD COLUMN virtual_fisik VARCHAR(20) NULL AFTER fungsi_server",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_lokasi_rack := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'server_detail'
      AND COLUMN_NAME = 'lokasi_rack'
);
SET @sql := IF(
    @has_lokasi_rack = 0,
    "ALTER TABLE server_detail ADD COLUMN lokasi_rack VARCHAR(120) NULL AFTER virtual_fisik",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_ip_utama := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'server_detail'
      AND COLUMN_NAME = 'ip_utama'
);
SET @sql := IF(
    @has_ip_utama = 0,
    "ALTER TABLE server_detail ADD COLUMN ip_utama VARCHAR(64) NULL AFTER lokasi_rack",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
