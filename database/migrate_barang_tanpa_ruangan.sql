USE sensus_hardware;

ALTER TABLE histori_barang
    ADD COLUMN IF NOT EXISTS ruangan_id INT NULL AFTER barang_id,
    ADD COLUMN IF NOT EXISTS ruangan_nama VARCHAR(100) NULL AFTER ruangan_id;

ALTER TABLE barang
    MODIFY ruangan_id INT NULL;

UPDATE histori_barang h
LEFT JOIN barang b ON b.id = h.barang_id
LEFT JOIN ruangan r ON r.id = b.ruangan_id
SET
    h.ruangan_id = COALESCE(h.ruangan_id, b.ruangan_id),
    h.ruangan_nama = COALESCE(h.ruangan_nama, r.nama_ruangan)
WHERE h.ruangan_id IS NULL OR h.ruangan_nama IS NULL;

UPDATE barang
SET ruangan_id = NULL;
