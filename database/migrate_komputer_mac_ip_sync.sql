USE sensus_hardware;

ALTER TABLE komputer_client
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

SET @idx_komputer_mac_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'komputer_client'
      AND index_name = 'idx_komputer_mac_address'
);
SET @idx_komputer_mac_sql := IF(
    @idx_komputer_mac_exists = 0,
    'ALTER TABLE komputer_client ADD INDEX idx_komputer_mac_address (mac_address)',
    'SELECT 1'
);
PREPARE stmt FROM @idx_komputer_mac_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
