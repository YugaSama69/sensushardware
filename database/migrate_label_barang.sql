USE sensus_hardware;

ALTER TABLE barang
    ADD COLUMN IF NOT EXISTS label_barang VARCHAR(100) NOT NULL DEFAULT 'Lainnya' AFTER qty;

UPDATE barang
SET label_barang = 'Lainnya'
WHERE label_barang IS NULL OR TRIM(label_barang) = '';
