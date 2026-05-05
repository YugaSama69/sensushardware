USE sensus_hardware;

CREATE TABLE IF NOT EXISTS master_bidang_unit_pengembangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_unit VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS laporan_pengembangan_aplikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulan_tahun DATE NOT NULL,
    nama_kegiatan VARCHAR(255) NOT NULL,
    bidang_unit VARCHAR(150) NOT NULL,
    keterangan TEXT NULL,
    capaian TEXT NOT NULL,
    input_user VARCHAR(100) NOT NULL DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE laporan_pengembangan_aplikasi
    ADD COLUMN IF NOT EXISTS keterangan TEXT NULL AFTER bidang_unit;

ALTER TABLE laporan_pengembangan_aplikasi
    ADD COLUMN IF NOT EXISTS input_user VARCHAR(100) NOT NULL DEFAULT 'Administrator' AFTER capaian;

UPDATE laporan_pengembangan_aplikasi
SET capaian = TRIM(REPLACE(capaian, '%', ''))
WHERE LOCATE('%', capaian) > 0;

INSERT INTO master_bidang_unit_pengembangan (nama_unit)
SELECT DISTINCT bidang_unit
FROM laporan_pengembangan_aplikasi
WHERE bidang_unit <> ''
ON DUPLICATE KEY UPDATE nama_unit = VALUES(nama_unit);
