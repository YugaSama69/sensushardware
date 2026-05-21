USE sensus_hardware;

UPDATE komputer_client
SET mac_address = UPPER(
    CASE
        WHEN LENGTH(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), '.', '')) = 12 THEN CONCAT(
            SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), '.', ''), 1, 2), ':',
            SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), '.', ''), 3, 2), ':',
            SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), '.', ''), 5, 2), ':',
            SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), '.', ''), 7, 2), ':',
            SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), '.', ''), 9, 2), ':',
            SUBSTRING(REPLACE(REPLACE(REPLACE(TRIM(mac_address), ':', ''), '-', ''), '.', ''), 11, 2)
        )
        ELSE UPPER(TRIM(mac_address))
    END
)
WHERE mac_address IS NOT NULL
  AND TRIM(mac_address) <> '';

SET @duplicate_mac_total := (
    SELECT COUNT(*)
    FROM (
        SELECT mac_address
        FROM komputer_client
        WHERE mac_address IS NOT NULL
          AND TRIM(mac_address) <> ''
        GROUP BY mac_address
        HAVING COUNT(*) > 1
    ) duplicate_mac_rows
);

SET @uq_komputer_mac_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'komputer_client'
      AND index_name = 'uq_komputer_mac_address'
);

SET @uq_komputer_mac_sql := IF(
    @duplicate_mac_total = 0 AND @uq_komputer_mac_exists = 0,
    'ALTER TABLE komputer_client ADD UNIQUE KEY uq_komputer_mac_address (mac_address)',
    'SELECT 1'
);
PREPARE stmt FROM @uq_komputer_mac_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
