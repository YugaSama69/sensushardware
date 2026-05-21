USE sensus_hardware;

ALTER TABLE komputer_client
    ADD COLUMN IF NOT EXISTS serial_number VARCHAR(150) NULL AFTER motherboard;

SET @idx_komputer_ip_address_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'komputer_client'
      AND index_name = 'idx_komputer_ip_address'
);
SET @idx_komputer_ip_address_sql := IF(
    @idx_komputer_ip_address_exists = 0,
    'ALTER TABLE komputer_client ADD INDEX idx_komputer_ip_address (ip_address)',
    'SELECT 1'
);
PREPARE stmt FROM @idx_komputer_ip_address_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_komputer_tahun_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'komputer_client'
      AND index_name = 'idx_komputer_tahun_inventaris'
);
SET @idx_komputer_tahun_sql := IF(
    @idx_komputer_tahun_exists = 0,
    'ALTER TABLE komputer_client ADD INDEX idx_komputer_tahun_inventaris (tahun_inventaris)',
    'SELECT 1'
);
PREPARE stmt FROM @idx_komputer_tahun_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE server_detail
    ADD COLUMN IF NOT EXISTS total_thread INT NULL AFTER uptime,
    ADD COLUMN IF NOT EXISTS multiple_nic TEXT NULL AFTER total_thread,
    ADD COLUMN IF NOT EXISTS domain_joined VARCHAR(10) NULL AFTER multiple_ip;

CREATE TABLE IF NOT EXISTS inventory_upload_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    payload_encrypted LONGTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    request_ip VARCHAR(45) NULL,
    request_user_agent VARCHAR(255) NULL,
    upload_ip VARCHAR(45) NULL,
    payload_hash CHAR(64) NULL,
    UNIQUE KEY uq_inventory_upload_token_hash (token_hash),
    INDEX idx_inventory_upload_tokens_expires_at (expires_at),
    INDEX idx_inventory_upload_tokens_used_at (used_at)
);
