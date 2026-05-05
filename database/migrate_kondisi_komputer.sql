USE sensus_hardware;

ALTER TABLE komputer_client
    ADD COLUMN IF NOT EXISTS kondisi ENUM('Baik', 'Rusak', 'Perbaikan') NOT NULL DEFAULT 'Baik' AFTER tahun_inventaris;

UPDATE komputer_client
SET kondisi = 'Baik'
WHERE kondisi IS NULL OR kondisi = '';
