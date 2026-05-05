USE sensus_hardware;

CREATE TABLE IF NOT EXISTS master_label_barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_label VARCHAR(100) NOT NULL UNIQUE,
    warna_label CHAR(7) NOT NULL DEFAULT '#64748B',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO master_label_barang (nama_label, warna_label) VALUES
('PC Desktop', '#2563EB'),
('AIO', '#0F766E'),
('Laptop', '#7C3AED'),
('Printer', '#B45309'),
('Monitor', '#0891B2'),
('Network', '#1D4ED8'),
('Sparepart', '#64748B'),
('Peripheral', '#059669'),
('Lainnya', '#475569');

INSERT IGNORE INTO master_label_barang (nama_label, warna_label)
SELECT DISTINCT label_barang, '#64748B'
FROM barang
WHERE label_barang IS NOT NULL AND TRIM(label_barang) <> '';
