# CI3 Porting Checklist and Legacy SQL Mapping

## Purpose
This document captures the legacy table usage and SQL patterns from the current `sensushardware` app, and maps them to the CodeIgniter 3 HMVC target architecture.

> Use this as a non-destructive port checklist. Do not make production schema changes in the legacy app.

---

## 1. High-level port plan

1. Preserve legacy database and app behavior.
2. Add a separate safe database connection group in the CI3 project (`admin_simrs`).
3. Port one module at a time, converting raw SQL and helper functions into CI models.
4. Use the new test harness to verify connectivity without modifying data.

---

## 2. Core legacy tables and queries

| Legacy table | Purpose | Sample legacy SQL | CI3 target model/method |
| --- | --- | --- | --- |
| `barang` | Inventory master data | `SELECT b.* FROM barang b WHERE 1=1` | `Barang_model::get_items($filters)` |
| `histori_barang` | Transaction history | `SELECT h.*, b.nama_barang ... FROM histori_barang h ...` | `Histori_model::get_report_rows($filters)` |
| `ruangan` | Room master data | `SELECT * FROM ruangan ORDER BY nama_ruangan ASC` | `Ruangan_model::get_all()` |
| `komputer_client` | Computer inventory / client devices | `SELECT * FROM komputer_client WHERE 1=1` | `Komputer_model::get_devices($filters)` |
| `server_detail` | Server-specific device details | `INSERT INTO server_detail ... ON DUPLICATE KEY UPDATE ...` | `Server_detail_model::upsert($data)` |
| `mutasi_komputer` | Computer mutation records | `SELECT m.*, b.kode_barang ... FROM mutasi_komputer m ...` | `Mutasi_model::get_all()` |
| `laporan_pengembangan_aplikasi` | Development reports | `SELECT * FROM laporan_pengembangan_aplikasi WHERE 1=1` | `Pengembangan_model::get_rows($filters)` |
| `monitoring_ruangan` | Room monitoring records | `SELECT COUNT(*) FROM monitoring_ruangan WHERE tanggal = CURDATE()` | `Monitoring_ruangan_model::get_dashboard_stats()` |
| `master_label_barang` | Master item labels | `SELECT id, nama_label FROM master_label_barang ORDER BY nama_label ASC` | `Label_barang_model::get_all()` |
| `komputer_client` | IP and device reporting | `SELECT DISTINCT merk AS value FROM komputer_client ...` | `Komputer_model::get_filter_options()` |

---

## 3. Module-by-module port checklist

### `dashboard`
- Legacy files:
  - `modules/dashboard/index.php`
  - `modules/dashboard/module.php`
- Tables referenced: `komputer_client`, `barang`, `histori_barang`, `ruangan`, `monitoring_ruangan`
- Key datasets:
  - total counts by device type
  - monthly `histori_barang` sums for `masuk` / `keluar`
  - computer spec breakdown from `komputer_client`
- CI3 implementation:
  - `Dashboard_model::get_stats()`
  - `Dashboard_model::get_device_summary()`
  - `Dashboard_model::get_history_chart_data()`

### `barang`
- Legacy file: `modules/barang/index.php`
- Tables referenced: `barang`, `histori_barang`, `master_label_barang`, `ruangan`
- Critical SQL patterns:
  - `INSERT INTO barang (...) VALUES (...)`
  - `UPDATE barang SET ... WHERE id = :id`
  - `DELETE FROM barang WHERE id = :id`
  - `SELECT COUNT(*) FROM histori_barang WHERE barang_id = :id`
  - `SELECT COUNT(*) FROM barang WHERE label_barang = :label`
- CI3 implementation:
  - `Barang_model::insert_item($data)`
  - `Barang_model::update_item($id, $data)`
  - `Barang_model::delete_item($id)`
  - `Barang_model::count_history($barangId)`
  - `Label_barang_model::update_label_name($oldName, $newName)`

### `ruangan`
- Legacy file: `modules/ruangan/index.php`
- Tables referenced: `ruangan`, `barang`
- Critical queries:
  - `SELECT COUNT(*) FROM barang WHERE ruangan_id = :id`
  - `DELETE FROM ruangan WHERE id = :id`
- CI3 implementation:
  - `Ruangan_model::get_all()`
  - `Ruangan_model::get_by_id($id)`
  - `Ruangan_model::delete_if_unused($id)`

### `komputer`
- Legacy files: `modules/komputer/index.php`, `modules/komputer/module.php`
- Tables referenced: `komputer_client`, `server_detail`, `client_ip_addresses` (optional)
- Key patterns:
  - `SELECT DISTINCT ... FROM komputer_client`
  - `UPDATE komputer_client SET ... WHERE id = :id`
  - `INSERT INTO komputer_client (...) VALUES (...)`
  - `LEFT JOIN server_detail ON sd.komputer_id = kc.id`
- CI3 implementation:
  - `Komputer_model::filter_devices($filters)`
  - `Komputer_model::get_device_by_id($id)`
  - `Komputer_model::save_device($data)`
  - `Server_detail_model::save_for_device($deviceId, $serverData)`

### `transaksi`
- Legacy files: `modules/transaksi/masuk.php`, `keluar.php`, `history.php`
- Tables referenced: `histori_barang`, `barang`, `ruangan`
- Important workflows:
  - incoming stock `masuk`
  - outgoing stock `keluar`
  - deletion of transaction history
- CI3 implementation:
  - `Transaksi_model::record_masuk($inputs)`
  - `Transaksi_model::record_keluar($inputs)`
  - `Transaksi_model::delete_history($id)`

### `mutasi_komputer`
- Legacy file: `modules/mutasi_komputer/index.php`
- Tables referenced: `mutasi_komputer`, `histori_barang`, `barang`, `ruangan`, `komputer_client`
- Key operations:
  - update `barang.qty`
  - insert `histori_barang` record with `tipe_transaksi = 'keluar'`
  - insert `mutasi_komputer`
  - delete mutasi and restore stock
- CI3 implementation:
  - `Mutasi_model::create($data)`
  - `Mutasi_model::delete($id)`
  - `Mutasi_model::get_all()`

### `pengembangan`
- Legacy file: `modules/pengembangan/index.php`
- Table referenced: `laporan_pengembangan_aplikasi`
- CI3 implementation:
  - `Pengembangan_model::get_rows($filters)`
  - `Pengembangan_model::delete($id)`

### `monitoring_ruangan`
- Legacy files: `modules/monitoring_ruangan/module.php`, `modules/monitoring_ruangan/*.php`
- Tables referenced: `monitoring_ruangan`, `ruangan`, `master_petugas`, `master_ruangan`
- CI3 implementation:
  - `Monitoring_ruangan_model::get_today_stats()`
  - `Monitoring_ruangan_model::list_recent()`
  - `Monitoring_ruangan_model::count_by_room()`

### `ip_komputer`
- Legacy file: `modules/ip_komputer/index.php`
- Table referenced: `komputer_client`
- CI3 implementation:
  - `Ip_komputer_model::get_client_ip_summary()`

### `kondisi_komputer`
- Legacy file: `modules/kondisi_komputer/index.php`
- Table referenced: `komputer_client`
- CI3 implementation:
  - `Kondisi_komputer_model::get_all()`

### `laporan`
- Legacy file: `modules/laporan/index.php`
- Tables referenced: `ruangan`, `barang`, `histori_barang`
- CI3 implementation:
  - `Laporan_model::prepare_filters()`
  - `Laporan_model::get_report_rows($filters)`

---

## 4. Exact legacy SQL snippets to preserve

- `SELECT COUNT(*) FROM barang WHERE label_barang = :label`
- `SELECT * FROM barang WHERE id = :id LIMIT 1`
- `SELECT * FROM ruangan ORDER BY nama_ruangan ASC`
- `SELECT * FROM komputer_client WHERE 1=1`
- `SELECT * FROM komputer_client WHERE kondisi = "Rusak" ORDER BY hostname ASC`
- `SELECT COALESCE(SUM(qty), 0) FROM histori_barang WHERE tipe_transaksi = "masuk" ...`
- `SELECT COUNT(*) FROM monitoring_ruangan WHERE tanggal = CURDATE()`
- `INSERT INTO mutasi_komputer (...) VALUES (...)`
- `UPDATE server_detail ... ON DUPLICATE KEY UPDATE ...`

---

## 5. Safe DB migration notes

- Keep production DB untouched while porting.
- Use a separate CI3 DB connection group called `admin_simrs`.
- In CI3, create helper functions such as `admin_db()` to return `$this->load->database('admin_simrs', true)`.
- Use the new test harness first:
  - `php tools/test_admin_simrs.php --host=127.0.0.1 --port=3306 --user=root --pass= --db=admin_simrs`
- Only migrate logic to CI3 after verifying the test harness can connect and read table metadata.

---

## 6. Practical porting sequence

1. `auth` + session boot
2. `dashboard`
3. `barang`
4. `ruangan`
5. `komputer`
6. `transaksi` (`masuk`, `keluar`, `history`)
7. `mutasi_komputer`
8. `pengembangan`
9. `monitoring_ruangan`
10. `laporan`
11. `ip_komputer` + `kondisi_komputer`

---

## 7. Verification checklist

- [ ] Legacy DB schema available in `admin_simrs` or mirrored dev DB
- [ ] CI3 `config/database.php` has `admin_simrs` group
- [ ] `admin_db()` helper returns the alternate DB object
- [ ] `Barang_model`, `Komputer_model`, `Histori_model`, `Mutasi_model` created
- [ ] Smoke test passes without writes
- [ ] CI3 endpoints render expected HTML for read-only pages
