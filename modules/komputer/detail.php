<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';

$id = (int) ($_GET['id'] ?? 0);
$device = $id > 0 ? komputer_inventory_find_device($pdo, $id) : null;

if (!$device) {
    set_flash('warning', 'Detail device tidak ditemukan.');
    redirect('modules/komputer/index.php');
}

$pageTitle = 'Detail Device';
$pageSubtitle = 'Informasi perangkat client atau server berikut detail teknis pendukungnya.';
$deviceType = (string) ($device['device_type'] ?? 'CLIENT');
$isServer = $deviceType === 'SERVER';
$deviceIpLines = $isServer ? komputer_inventory_server_ip_lines($device) : komputer_inventory_client_ip_lines($device);
$devicePrimaryIp = $deviceIpLines[0] ?? ((string) ($device['ip_address'] ?? '-'));
$deviceSecondaryIps = count($deviceIpLines) > 1 ? array_slice($deviceIpLines, 1) : [];

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-start flex-wrap gap-3 pt-4">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <h5 class="mb-0"><?= e((string) $device['hostname']); ?></h5>
                        <span class="badge text-bg-<?= komputer_inventory_device_type_badge_class($deviceType); ?>"><?= e($deviceType); ?></span>
                    </div>
                    <p class="text-muted mb-0"><?= e(komputer_inventory_device_type_label($deviceType)); ?> di <?= e((string) ($device['ruangan'] ?? '-')); ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= e(url('modules/komputer/index.php?device_type=' . $deviceType)); ?>" class="btn btn-light">Kembali</a>
                    <a href="<?= e(url('modules/komputer/index.php?device_type=' . $deviceType)); ?>#editComputerModal<?= (int) $device['id']; ?>" class="btn btn-outline-primary">Lihat di List</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="small text-muted mb-2">Identitas Device</div>
                            <div class="mb-2"><strong>Hostname:</strong> <?= e((string) ($device['hostname'] ?? '-')); ?></div>
                            <div class="mb-2"><strong>IP Address:</strong> <?= e($devicePrimaryIp); ?></div>
                            <?php if ($deviceSecondaryIps): ?>
                                <div class="mb-2">
                                    <strong>IP Tambahan:</strong>
                                    <ul class="mb-0 mt-1 ps-3">
                                        <?php foreach ($deviceSecondaryIps as $ipLine): ?>
                                            <li><?= e($ipLine); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <div class="mb-2"><strong>MAC Address:</strong> <?= e((string) ($device['mac_address'] ?? '-')); ?></div>
                            <div><strong>Ruangan:</strong> <?= e((string) ($device['ruangan'] ?? '-')); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="small text-muted mb-2">Sistem & Inventaris</div>
                            <div class="mb-2"><strong>OS:</strong> <?= e((string) ($device['os_name'] ?? '-')); ?></div>
                            <div class="mb-2"><strong>Versi:</strong> <?= e((string) ($device['os_version'] ?? '-')); ?></div>
                            <div class="mb-2"><strong>Arsitektur:</strong> <?= e((string) ($device['architecture'] ?? '-')); ?></div>
                            <div><strong>Tahun Inventaris:</strong> <?= e((string) ($device['tahun_inventaris'] ?? '-')); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="small text-muted mb-2">Hardware</div>
                            <div class="mb-2"><strong>Processor:</strong> <?= e((string) ($device['processor'] ?? '-')); ?></div>
                            <div class="mb-2"><strong>Core:</strong> <?= e((string) ($device['core'] ?? 0)); ?></div>
                            <?php if ($isServer): ?>
                                <div class="mb-2"><strong>Total Thread:</strong> <?= e((string) ($device['total_thread'] ?? 0)); ?></div>
                            <?php endif; ?>
                            <div class="mb-2"><strong>RAM:</strong> <?= e((string) ($device['ram'] ?? '-')); ?></div>
                            <div><strong>Motherboard:</strong> <?= e((string) ($device['motherboard'] ?? '-')); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="small text-muted mb-2">Penyimpanan & Pemakai</div>
                            <div class="mb-2"><strong>SSD:</strong> <?= nl2br(e((string) ($device['ssd'] ?? '-'))); ?></div>
                            <div class="mb-2"><strong>HDD:</strong> <?= nl2br(e((string) ($device['hdd'] ?? '-'))); ?></div>
                            <div class="mb-2"><strong>Serial Number:</strong> <?= e((string) ($device['serial_number'] ?? '-')); ?></div>
                            <div class="mb-2"><strong>User:</strong> <?= e((string) ($device['petugas'] ?? '-')); ?></div>
                            <div><strong>Kondisi:</strong> <span class="badge text-bg-<?= condition_badge((string) ($device['kondisi'] ?? 'Perbaikan')); ?>"><?= e((string) ($device['kondisi'] ?? '-')); ?></span></div>
                        </div>
                    </div>
                </div>

                <?php if ($isServer): ?>
                    <div class="border rounded-4 p-3 mt-4">
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                            <div>
                                <h6 class="mb-1">Detail Server</h6>
                                <div class="small text-muted">Informasi tambahan khusus device bertipe server.</div>
                            </div>
                            <span class="badge text-bg-warning">SERVER</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div><strong>Virtualization:</strong> <?= e((string) ($device['virtualization'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>RAID:</strong> <?= e((string) ($device['raid'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Hypervisor:</strong> <?= e((string) ($device['hypervisor'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Uptime:</strong> <?= e((string) ($device['uptime'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Server Role:</strong> <?= e((string) ($device['server_role'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Domain Joined:</strong> <?= e((string) ($device['domain_joined'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>IP Utama:</strong> <?= e((string) ($device['ip_utama'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Jenis Server:</strong> <?= e((string) ($device['jenis_server'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Fungsi Server:</strong> <?= e((string) ($device['fungsi_server'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Lokasi Rack:</strong> <?= e((string) ($device['lokasi_rack'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Virtual / Fisik:</strong> <?= e((string) ($device['virtual_fisik'] ?? '-')); ?></div>
                            </div>
                            <div class="col-md-12">
                                <strong>Multiple NIC:</strong>
                                <div class="mt-2"><?= nl2br(e((string) ($device['multiple_nic'] ?? '-'))); ?></div>
                            </div>
                            <div class="col-md-12">
                                <strong>Multiple IP:</strong>
                                <?php $ipLines = komputer_inventory_server_ip_lines($device); ?>
                                <?php if ($ipLines): ?>
                                    <ul class="mb-0 mt-2 ps-3">
                                        <?php foreach ($ipLines as $ipLine): ?>
                                            <li><?= e($ipLine); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="text-muted mt-2">Belum ada multiple IP yang dicatat.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">Riwayat Pendataan</h5>
                <p class="text-muted mb-0">Waktu terakhir perangkat ini tercatat di sistem.</p>
            </div>
            <div class="card-body">
                <div class="border rounded-4 p-3 mb-3">
                    <div class="small text-muted mb-2">Tanggal Scan</div>
                    <strong><?= e(format_date_id((string) ($device['tanggal'] ?? ''))); ?> <?= e(format_time_id((string) ($device['jam'] ?? ''))); ?></strong>
                </div>
                <div class="border rounded-4 p-3 mb-3">
                    <div class="small text-muted mb-2">Input User</div>
                    <strong><?= e((string) ($device['petugas'] ?? '-')); ?></strong>
                    <div class="small text-muted mt-1"><?= e((string) ($device['username'] ?? '-')); ?></div>
                </div>
                <div class="border rounded-4 p-3">
                    <div class="small text-muted mb-2">Ringkasan Device</div>
                    <div class="mb-2"><strong>Merk:</strong> <?= e((string) ($device['merk'] ?? '-')); ?></div>
                    <div class="mb-2"><strong>Model:</strong> <?= e((string) ($device['model'] ?? '-')); ?></div>
                    <div><strong>VGA / GPU:</strong> <?= e((string) ($device['vga'] ?? '-')); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
