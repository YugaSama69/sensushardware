USE sensus_hardware;

CREATE TABLE IF NOT EXISTS mutasi_komputer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id INT NOT NULL,
    histori_barang_id INT NOT NULL,
    ruangan_id INT NOT NULL,
    ruangan_nama VARCHAR(100) NOT NULL,
    komputer_client_id INT NULL,
    hostname_referensi VARCHAR(100) NULL,
    jenis_mutasi ENUM('komputer_baru', 'pergantian_komputer_rusak') NOT NULL,
    qty INT NOT NULL,
    stok_sebelum INT NOT NULL,
    stok_sesudah INT NOT NULL,
    nama_petugas VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    jam TIME NOT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mutasi_histori (histori_barang_id),
    INDEX idx_mutasi_barang (barang_id),
    INDEX idx_mutasi_ruangan (ruangan_id),
    INDEX idx_mutasi_tanggal (tanggal)
);
