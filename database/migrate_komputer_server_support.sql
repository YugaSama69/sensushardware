USE sensus_hardware;

SET @has_device_type := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'komputer_client'
      AND COLUMN_NAME = 'device_type'
);
SET @sql := IF(
    @has_device_type = 0,
    "ALTER TABLE komputer_client ADD COLUMN device_type ENUM('CLIENT', 'SERVER') NOT NULL DEFAULT 'CLIENT' AFTER hostname",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_device_type_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'komputer_client'
      AND INDEX_NAME = 'idx_komputer_device_type'
);
SET @sql := IF(
    @has_device_type_index = 0,
    'ALTER TABLE komputer_client ADD INDEX idx_komputer_device_type (device_type)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

UPDATE komputer_client
SET device_type = 'CLIENT'
WHERE device_type IS NULL OR device_type = '';
