<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/module.php';

$rooms = fetch_all($pdo, 'SELECT nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC');
$secureTransportReady = device_inventory_secure_transport_ready();
$secureTransportStrict = device_inventory_is_https_request();

function pendataan_theme_icon(string $name): string
{
    $icons = [
        'device' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5.5h16v10H4zM9 19h6M12 15.5V19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'room' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s6-4.35 6-10a6 6 0 1 0-12 0c0 5.65 6 10 6 10Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="11" r="2.2" stroke="currentColor" stroke-width="1.8"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.5v3M17 3.5v3M4 8h16M5.5 5.5h13A1.5 1.5 0 0 1 20 7v11.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5V7a1.5 1.5 0 0 1 1.5-1.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="1.8"/><path d="M5.5 19.2c1.5-3 4-4.2 6.5-4.2s5 1.2 6.5 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'pulse' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12h4l2.1-4.2L13 17l2.2-5H21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7.5 10V8a4.5 4.5 0 1 1 9 0v2M6.5 10.5h11a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.8"/><path d="M12 14v2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3l7 3v5c0 4.8-2.85 8.55-7 10-4.15-1.45-7-5.2-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.8"/><path d="M12 9.2v3.4M12 15.7h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'scan' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 4H6a2 2 0 0 0-2 2v2M16 4h2a2 2 0 0 1 2 2v2M8 20H6a2 2 0 0 1-2-2v-2M16 20h2a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="3.8" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="1.1" fill="currentColor"/></svg>',
        'sync' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 12a8 8 0 0 0-13.7-5.6L4.5 8.2M4.5 8.2V4.5M4.5 8.2h3.7M4 12a8 8 0 0 0 13.7 5.6l1.8-1.8M19.5 15.8v3.7M19.5 19.5h-3.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="m8.4 12.3 2.5 2.5 4.8-5.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ];

    return $icons[$name] ?? $icons['device'];
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pendataan Inventaris Device - <?= e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= e(url(APP_FAVICON_PATH)); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="client-scan-body cyber-inventory-body">
    <main class="container-xxl py-4 py-lg-5 client-scan-shell cyber-inventory-shell">
        <section class="cyber-inventory-topbar">
            <div class="cyber-topbar-cell">
                <div class="cyber-brand-logo-wrap">
                    <img src="<?= e(url(APP_LOGO_PATH)); ?>" alt="Logo SiAEGIS" class="cyber-brand-logo">
                </div>
                <div>
                    <div class="cyber-brand-name">SiAEGIS</div>
                    <div class="cyber-brand-subtitle">Inventory System</div>
                </div>
            </div>
            <div class="cyber-topbar-cell cyber-topbar-center">
                <div class="cyber-topbar-kicker">Sistem Inventaris IT</div>
                <!-- <div class="cyber-topbar-title">RSUD Welas Asih</div> -->
            </div>
            <div class="cyber-topbar-cell cyber-topbar-security">
                <div class="cyber-topbar-icon"><?= pendataan_theme_icon('lock'); ?></div>
                <div>
                    <div class="cyber-topbar-kicker">Secure System</div>
                    <div class="cyber-topbar-meta">Encrypted connection</div>
                </div>
            </div>
        </section>

        <section class="cyber-inventory-panel">
            <div class="cyber-panel-hero">
                <div>
                    <span class="cyber-section-kicker">Form Pendataan</span>
                    <h1 class="cyber-panel-title">Pendataan Inventaris Device RSUD Welas Asih</h1>
                    <div class="cyber-panel-divider"></div>
                </div>
                <div class="cyber-panel-hero-visual" aria-hidden="true">
                    <div class="cyber-hero-shield"><?= pendataan_theme_icon('shield'); ?></div>
                </div>
            </div>

            <?php if (!$secureTransportStrict): ?>
                <div class="alert <?= $secureTransportReady ? 'alert-warning' : 'alert-danger'; ?> border-0 shadow-sm mb-4">
                    <strong><?= $secureTransportReady ? 'Mode jaringan internal:' : 'HTTPS diperlukan:'; ?></strong>
                    <?= $secureTransportReady
                        ? 'akses launcher saat ini berasal dari jaringan internal. Untuk production tetap disarankan menggunakan HTTPS penuh agar token dan file launcher lebih aman.'
                        : 'fitur launcher dinamis hanya akan bekerja bila halaman ini dibuka melalui HTTPS.'; ?>
                </div>
            <?php endif; ?>

            <div class="alert d-none mb-4" data-launcher-feedback></div>

            <form method="post" action="<?= e(url('inventory/request_launcher.php')); ?>" class="scan-form cyber-form-shell" data-launcher-form novalidate>
                <?= csrf_field(); ?>

                <div class="cyber-form-section">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Tipe Device</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('device'); ?></span>
                                <select name="device_type" class="form-select form-select-lg" data-device-type-select required>
                                    <?php foreach (device_inventory_device_type_options() as $deviceType => $label): ?>
                                        <option value="<?= e($deviceType); ?>" <?= $deviceType === 'CLIENT' ? 'selected' : ''; ?>><?= e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lokasi Ruangan</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('room'); ?></span>
                                <input type="text" name="ruangan" class="form-control form-control-lg" list="daftar-ruangan" placeholder="Cari atau ketik ruangan" autocomplete="off" required>
                            </div>
                            <datalist id="daftar-ruangan">
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= e($room['nama_ruangan']); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <!-- <div class="form-text">Pilih dari data ruangan yang ada atau ketik manual bila belum tersedia.</div> -->
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tahun Inventaris</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('calendar'); ?></span>
                                <input type="number" name="tahun_inventaris" class="form-control form-control-lg" min="2000" max="<?= date('Y') + 1; ?>" value="<?= date('Y'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama User</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('user'); ?></span>
                                <input type="text" name="nama_user" class="form-control form-control-lg" placeholder="Nama pengguna atau petugas" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kondisi Device</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('pulse'); ?></span>
                                <select name="kondisi" class="form-select form-select-lg" required>
                                    <?php foreach (device_inventory_condition_options() as $conditionOption): ?>
                                        <option value="<?= e($conditionOption); ?>"><?= e($conditionOption); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cyber-form-section d-none" data-server-fields>
                    <div class="cyber-form-section-title">Informasi Server</div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Server</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('device'); ?></span>
                                <input type="text" name="jenis_server" class="form-control form-control-lg" placeholder="Contoh: Database Server" data-server-input>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fungsi Server</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('scan'); ?></span>
                                <input type="text" name="fungsi_server" class="form-control form-control-lg" placeholder="Contoh: SIMRS, PACS, AD, Backup" data-server-input>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Virtual / Fisik</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('shield'); ?></span>
                                <select name="virtual_fisik" class="form-select form-select-lg" data-server-input>
                                    <option value="">Pilih tipe</option>
                                    <?php foreach (device_inventory_virtual_mode_options() as $modeValue => $modeLabel): ?>
                                        <option value="<?= e($modeValue); ?>"><?= e($modeLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Operating System</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('device'); ?></span>
                                <input type="text" name="operating_system" class="form-control form-control-lg" placeholder="Contoh: Windows Server 2022" data-server-input>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lokasi Rack</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('room'); ?></span>
                                <input type="text" name="lokasi_rack" class="form-control form-control-lg" placeholder="Contoh: Rack A2 U12" data-server-input>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IP Utama</label>
                            <div class="cyber-input-wrap">
                                <span class="cyber-input-icon"><?= pendataan_theme_icon('device'); ?></span>
                                <input type="text" name="ip_utama" class="form-control form-control-lg" placeholder="Contoh: 192.168.2.10" data-server-input>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cyber-action-grid">
                    <div class="cyber-action-card">
                        <div class="cyber-action-icon"><?= pendataan_theme_icon('scan'); ?></div>
                        <div>
                            <strong>Scan Otomatis dengan Launcher BAT</strong>
                            <span>Launcher akan mengambil data perangkat dan menyiapkannya untuk dikirim ke sistem inventaris.</span>
                        </div>
                    </div>

                    <button type="submit" class="btn cyber-submit-button" data-launcher-submit <?= !$secureTransportReady ? 'disabled' : ''; ?>>
                        <span><?= pendataan_theme_icon('shield'); ?></span>
                        <span>Pendataan Device Ini</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="cyber-bottom-steps">
            <div class="cyber-steps-title">Tahapan Pendataan Device</div>
            <div class="cyber-steps-grid">
                <div class="cyber-step-card">
                    <div class="cyber-step-icon"><?= pendataan_theme_icon('device'); ?></div>
                    <strong>Jalankan Launcher</strong>
                    <span>Jalankan file launcher BAT sebagai admin.</span>
                </div>
                <div class="cyber-step-card">
                    <div class="cyber-step-icon"><?= pendataan_theme_icon('scan'); ?></div>
                    <strong>Scan &amp; Ambil Data</strong>
                    <span>Launcher akan melakukan scan device otomatis.</span>
                </div>
                <div class="cyber-step-card">
                    <div class="cyber-step-icon"><?= pendataan_theme_icon('sync'); ?></div>
                    <strong>Sinkronisasi Data</strong>
                    <span>Data device dikirim ke sistem inventaris.</span>
                </div>
                <div class="cyber-step-card">
                    <div class="cyber-step-icon"><?= pendataan_theme_icon('check'); ?></div>
                    <strong>Data Tersimpan</strong>
                    <span>Data berhasil tersimpan dan siap digunakan.</span>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-launcher-form]');
            if (!form) {
                return;
            }

            const deviceTypeSelect = form.querySelector('[data-device-type-select]');
            const serverSection = form.querySelector('[data-server-fields]');
            const serverInputs = form.querySelectorAll('[data-server-input]');
            const feedback = document.querySelector('[data-launcher-feedback]');
            const submitButton = form.querySelector('[data-launcher-submit]');

            const setFeedback = function (type, message) {
                if (!feedback) {
                    return;
                }

                feedback.className = 'alert mb-4 alert-' + type;
                feedback.textContent = message;
            };

            const syncServerFields = function () {
                const isServer = deviceTypeSelect && deviceTypeSelect.value === 'SERVER';

                if (serverSection) {
                    serverSection.classList.toggle('d-none', !isServer);
                }

                serverInputs.forEach(function (input) {
                    input.disabled = !isServer;
                    if (!isServer) {
                        input.removeAttribute('required');
                    } else {
                        input.setAttribute('required', 'required');
                    }
                });
            };

            if (deviceTypeSelect) {
                deviceTypeSelect.addEventListener('change', syncServerFields);
                syncServerFields();
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Membuat Launcher...';
                }

                if (feedback) {
                    feedback.className = 'alert d-none mb-4';
                    feedback.textContent = '';
                }

                const formData = new FormData(form);

                fetch(form.getAttribute('action'), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return {
                                ok: response.ok,
                                data: data
                            };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok || !result.data || result.data.success !== true) {
                            throw new Error((result.data && result.data.message) || 'Launcher belum berhasil dibuat.');
                        }

                        setFeedback('success', result.data.message || 'Launcher berhasil dibuat. Download akan dimulai.');

                        if (result.data.download_url) {
                            const link = document.createElement('a');
                            link.href = result.data.download_url;
                            link.style.display = 'none';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                    })
                    .catch(function (error) {
                        setFeedback('danger', error.message || 'Launcher belum berhasil dibuat.');
                    })
                    .finally(function () {
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Pendataan Device Ini';
                        }
                    });
            });
        });
    </script>
</body>
</html>
