<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';

$pageTitle = 'Komputer Client';
$pageSubtitle = 'Inventaris komputer client dan server dalam satu modul yang tetap ringan untuk operasional.';
$errors = [];
$filters = komputer_inventory_normalize_filters($_GET);
$redirectQuery = trim((string) ($_POST['redirect_query'] ?? ''));

if (is_post()) {
    verify_csrf();

    $action = trim((string) ($_POST['action'] ?? ''));
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        try {
            $pdo->beginTransaction();
            $detailStatement = $pdo->prepare('DELETE FROM server_detail WHERE komputer_id = :id');
            $detailStatement->execute(['id' => $id]);
            $statement = $pdo->prepare('DELETE FROM komputer_client WHERE id = :id');
            $statement->execute(['id' => $id]);
            $pdo->commit();
            set_flash('success', 'Data device berhasil dihapus.');
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('danger', 'Data device belum berhasil dihapus.');
        }
        redirect('modules/komputer/index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
    }

    if ($action === 'update' && $id > 0) {
        if (komputer_inventory_update_device($pdo, $id, $_POST, $errors)) {
            set_flash('success', 'Data device berhasil diperbarui.');
            redirect('modules/komputer/index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
        }
    }

    if ($action === 'create_server') {
        $newId = komputer_inventory_create_server($pdo, $_POST, $errors);

        if ($newId !== null) {
            set_flash('success', 'Data server berhasil ditambahkan.');
            redirect('modules/komputer/index.php?device_type=SERVER');
        }
    }
}

$rows = komputer_inventory_rows($pdo, $filters);
$filterOptions = komputer_inventory_filter_options($pdo);
$exportQuery = build_query_string($filters);
$roomOptions = get_room_name_options($pdo);
$tabCounts = komputer_inventory_tab_counts($pdo, $filters);
$activeDeviceType = $filters['device_type'];
$isServerTab = $activeDeviceType === 'SERVER';
$tableTitle = $isServerTab ? 'Data Server' : 'Data Komputer Client';
$tableSummary = $isServerTab
    ? 'Inventory server yang sudah diklasifikasikan di database.'
    : 'Data hasil pendataan otomatis dari komputer client rumah sakit.';
$quickFilterLabels = [];

if ($filters['device_type'] !== '') {
    $quickFilterLabels[] = 'Device Type: ' . komputer_inventory_device_type_label($filters['device_type']);
}

if ($filters['virtualization'] !== '' && $isServerTab) {
    $quickFilterLabels[] = 'Virtualization: ' . $filters['virtualization'];
}

if ($filters['server_role'] !== '' && $isServerTab) {
    $quickFilterLabels[] = 'Server Role: ' . $filters['server_role'];
}

if ($filters['os_name'] !== '') {
    $quickFilterLabels[] = 'OS: ' . $filters['os_name'];
}

if ($filters['ram_group'] !== '') {
    $quickFilterLabels[] = 'RAM: ' . $filters['ram_group'];
}

$pageScripts = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    const toggleServerFields = function (scope) {
        const root = scope || document;

        root.querySelectorAll('[data-device-type-select]').forEach(function (select) {
            const form = select.closest('form');
            if (!form) {
                return;
            }

            const syncState = function () {
                const isServer = select.value === 'SERVER';

                form.querySelectorAll('[data-server-only-fields]').forEach(function (section) {
                    section.classList.toggle('d-none', !isServer);
                });

                form.querySelectorAll('[data-client-only-fields]').forEach(function (section) {
                    section.classList.toggle('d-none', isServer);
                });

                form.querySelectorAll('[data-server-filter-field]').forEach(function (field) {
                    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                        field.disabled = !isServer;
                    }
                });

                form.querySelectorAll('[data-client-filter-field]').forEach(function (field) {
                    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                        field.disabled = isServer;
                    }
                });
            };

            select.addEventListener('change', syncState);
            syncState();
        });
    };

    toggleServerFields(document);

    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('shown.bs.modal', function () {
            toggleServerFields(modal);
        });
    });
});
JS;

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h5 class="mb-1">Dashboard Komputer Client &amp; Server</h5>
                <p class="text-muted mb-0">Satu menu yang tetap familiar untuk melihat inventory komputer client maupun server.</p>
            </div>
            <?php if ($isServerTab): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createServerModal">Tambah Server</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4">
            <?php foreach (komputer_inventory_device_type_options() as $deviceType => $deviceLabel): ?>
                <?php $tabFilters = $filters; $tabFilters['device_type'] = $deviceType; ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activeDeviceType === $deviceType ? 'active' : ''; ?>" href="<?= e(url('modules/komputer/index.php?' . build_query_string($tabFilters))); ?>">
                        <?= e($deviceLabel); ?>
                        <span class="badge text-bg-light ms-2"><?= (int) ($tabCounts[$deviceType] ?? 0); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="get" class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-4">
                <label class="form-label">Device Type</label>
                <select name="device_type" class="form-select" data-device-type-select>
                    <?php foreach ($filterOptions['device_type'] as $deviceType => $deviceLabel): ?>
                        <option value="<?= e($deviceType); ?>" <?= $filters['device_type'] === $deviceType ? 'selected' : ''; ?>><?= e($deviceLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-4">
                <label class="form-label">Operating System</label>
                <select name="os_name" class="form-select">
                    <option value="">Semua Operating System</option>
                    <?php foreach ($filterOptions['os_name'] as $option): ?>
                        <option value="<?= e($option); ?>" <?= $filters['os_name'] === $option ? 'selected' : ''; ?>><?= e($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-4">
                <label class="form-label">Virtualization</label>
                <select name="virtualization" class="form-select" data-server-filter-field <?= $isServerTab ? '' : 'disabled'; ?>>
                    <option value="">Semua Virtualization</option>
                    <?php foreach ($filterOptions['virtualization'] as $option): ?>
                        <option value="<?= e($option); ?>" <?= $filters['virtualization'] === $option ? 'selected' : ''; ?>><?= e($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-3 col-md-4">
                <label class="form-label">Server Role</label>
                <select name="server_role" class="form-select" data-server-filter-field <?= $isServerTab ? '' : 'disabled'; ?>>
                    <option value="">Semua Server Role</option>
                    <?php foreach ($filterOptions['server_role'] as $option): ?>
                        <option value="<?= e($option); ?>" <?= $filters['server_role'] === $option ? 'selected' : ''; ?>><?= e($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $secondaryFilters = [
                'merk' => 'Merk',
                'processor' => 'Processor',
                'kondisi' => 'Kondisi',
                'ram' => 'RAM',
                'storage' => 'SSD / HDD',
                'tahun_inventaris' => 'Tahun Inventaris',
                'ruangan' => 'Ruangan',
            ];
            ?>
            <?php foreach ($secondaryFilters as $name => $label): ?>
                <div class="col-xl-3 col-md-4">
                    <label class="form-label"><?= e($label); ?></label>
                    <select name="<?= e($name); ?>" class="form-select">
                        <option value="">Semua <?= e($label); ?></option>
                        <?php foreach ($filterOptions[$name] as $option): ?>
                            <option value="<?= e($option); ?>" <?= $filters[$name] === $option ? 'selected' : ''; ?>><?= e($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div class="col-12 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="<?= e(url('modules/komputer/index.php?device_type=' . $activeDeviceType)); ?>" class="btn btn-light">Reset</a>
                <a href="<?= e(url('modules/komputer/export_excel.php' . ($exportQuery !== '' ? '?' . $exportQuery : ''))); ?>" class="btn btn-success">Export Excel</a>
                <a href="<?= e(url('pendataan/index.php')); ?>" class="btn btn-outline-dark" target="_blank">Buka Halaman Client</a>
            </div>
        </form>

        <?php if ($quickFilterLabels): ?>
            <div class="alert alert-info mt-3 mb-0">
                <strong>Filter aktif:</strong>
                <?php foreach ($quickFilterLabels as $label): ?>
                    <span class="badge text-bg-light me-1 mt-2"><?= e($label); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
        <div class="d-flex align-items-start gap-3 flex-wrap">
            <div>
                <h5 class="mb-1"><?= e($tableTitle); ?></h5>
                <p class="text-muted mb-0"><?= count($rows); ?> data tampil. <?= e($tableSummary); ?></p>
            </div>
            <button
                type="button"
                class="btn btn-outline-primary btn-sm js-refresh-computer-table mt-1"
                data-refresh-url="<?= e(url('modules/komputer/index.php' . ($exportQuery !== '' ? '?' . $exportQuery : ''))); ?>"
                title="Refresh data device"
                aria-label="Refresh data device"
            >&#x21bb;</button>
        </div>
    </div>
    <div class="computer-client-refresh-region">
        <div class="computer-client-loading-indicator d-none" data-computer-loading>
            <div class="computer-client-loading-box">
                <div>
                    <div class="fw-semibold">
                        Memuat data device<span class="computer-client-loading-dots" aria-hidden="true"></span>
                    </div>
                    <div class="small text-muted">Mohon tunggu, tabel sedang diperbarui.</div>
                </div>
            </div>
        </div>
        <div class="card-body computer-client-table-panel">
            <div class="table-responsive">
                <table class="table table-hover align-middle datatable">
                    <?php if ($isServerTab): ?>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Device</th>
                                <th>IP</th>
                                <th>Processor</th>
                                <th>OS</th>
                                <th>Virtualization</th>
                                <th>Hypervisor</th>
                                <th>RAID</th>
                                <th>Uptime</th>
                                <th>Server Role</th>
                                <th>Ruangan</th>
                                <th>Petugas</th>
                                <th>Tanggal Scan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $computer): ?>
                                <?php
                                $serverIpLines = komputer_inventory_server_ip_lines($computer);
                                $serverPrimaryIp = $serverIpLines[0] ?? '-';
                                $serverSecondaryIps = count($serverIpLines) > 1 ? array_slice($serverIpLines, 1) : [];
                                ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <strong><?= e($computer['hostname']); ?></strong>
                                            <span class="badge text-bg-<?= komputer_inventory_device_type_badge_class((string) $computer['device_type']); ?>">
                                                <?= e((string) $computer['device_type']); ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted"><?= e($computer['mac_address']); ?></div>
                                    </td>
                                    <td>
                                        <div><?= e($serverPrimaryIp); ?></div>
                                        <div class="small text-muted"><?= count($serverIpLines); ?> IP aktif</div>
                                        <?php if ($serverSecondaryIps): ?>
                                            <div class="small text-muted mt-1">
                                                <?php foreach ($serverSecondaryIps as $secondaryIp): ?>
                                                    <div><?= e($secondaryIp); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e($computer['processor'] ?: '-'); ?>
                                        <div class="small text-muted"><?= e((string) ($computer['core'] ?: 0)); ?> core</div>
                                    </td>
                                    <td>
                                        <?= e($computer['os_name'] ?: '-'); ?>
                                        <div class="small text-muted"><?= e($computer['architecture'] ?: '-'); ?> | <?= e($computer['os_version'] ?: '-'); ?></div>
                                    </td>
                                    <td><?= e($computer['virtualization'] ?: '-'); ?></td>
                                    <td><?= e($computer['hypervisor'] ?: '-'); ?></td>
                                    <td><?= e($computer['raid'] ?: '-'); ?></td>
                                    <td><?= e($computer['uptime'] ?: '-'); ?></td>
                                    <td><?= e(komputer_inventory_server_role_display($computer)); ?></td>
                                    <td><?= e($computer['ruangan']); ?></td>
                                    <td><?= e($computer['petugas'] ?: '-'); ?></td>
                                    <td><?= e(format_date_id($computer['tanggal'])); ?> <?= e(format_time_id($computer['jam'])); ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('modules/komputer/detail.php?id=' . (int) $computer['id'])); ?>" class="btn btn-sm btn-outline-dark">Detail</a>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editComputerModal<?= (int) $computer['id']; ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteComputerModal<?= (int) $computer['id']; ?>">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php else: ?>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Device</th>
                                <th>IP</th>
                                <th>Merk</th>
                                <th>Processor</th>
                                <th>Kondisi</th>
                                <th>RAM</th>
                                <th>SSD/HDD</th>
                                <th>OS</th>
                                <th>Tahun Inventaris</th>
                                <th>Ruangan</th>
                                <th>Nama User</th>
                                <th>Tanggal Scan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $computer): ?>
                                <?php $clientIpLines = komputer_inventory_client_ip_lines($computer); ?>
                                <?php $clientPrimaryIp = komputer_inventory_primary_ip($computer); ?>
                                <?php $clientSecondaryIps = komputer_inventory_secondary_ip_lines($computer); ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <strong><?= e($computer['hostname']); ?></strong>
                                            <span class="badge text-bg-<?= komputer_inventory_device_type_badge_class((string) $computer['device_type']); ?>">
                                                <?= e((string) $computer['device_type']); ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted"><?= e($computer['username'] ?: '-'); ?> | <?= e($computer['mac_address']); ?></div>
                                    </td>
                                    <td>
                                        <div><?= e($clientPrimaryIp !== '' ? $clientPrimaryIp : '-'); ?></div>
                                        <div class="small text-muted"><?= count($clientIpLines); ?> IP aktif</div>
                                        <?php if ($clientSecondaryIps): ?>
                                            <div class="small text-muted mt-1">
                                                <?php foreach ($clientSecondaryIps as $secondaryIp): ?>
                                                    <div><?= e($secondaryIp); ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e($computer['merk'] ?: '-'); ?>
                                        <div class="small text-muted"><?= e($computer['model'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <?= e($computer['processor'] ?: '-'); ?>
                                        <div class="small text-muted"><?= e((string) ($computer['core'] ?: 0)); ?> core</div>
                                    </td>
                                    <td><span class="badge text-bg-<?= condition_badge($computer['kondisi'] ?? 'Perbaikan'); ?>"><?= e($computer['kondisi'] ?: 'Baik'); ?></span></td>
                                    <td><?= e($computer['ram'] ?: '-'); ?></td>
                                    <td>
                                        <div><strong>SSD:</strong> <?= e($computer['ssd'] ?: '-'); ?></div>
                                        <div><strong>HDD:</strong> <?= e($computer['hdd'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <?= e($computer['os_name'] ?: '-'); ?>
                                        <div class="small text-muted"><?= e($computer['architecture'] ?: '-'); ?> | <?= e($computer['os_version'] ?: '-'); ?></div>
                                    </td>
                                    <td><?= e($computer['tahun_inventaris'] ?: '-'); ?></td>
                                    <td><?= e($computer['ruangan']); ?></td>
                                    <td><?= e($computer['petugas'] ?: '-'); ?></td>
                                    <td><?= e(format_date_id($computer['tanggal'])); ?> <?= e(format_time_id($computer['jam'])); ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('modules/komputer/detail.php?id=' . (int) $computer['id'])); ?>" class="btn btn-sm btn-outline-dark">Detail</a>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editComputerModal<?= (int) $computer['id']; ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteComputerModal<?= (int) $computer['id']; ?>">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php foreach ($rows as $computer): ?>
            <?php $editClientSecondaryIps = komputer_inventory_secondary_ip_lines($computer); ?>
            <div class="modal fade" id="editComputerModal<?= (int) $computer['id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <form method="post">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Data Device</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int) $computer['id']; ?>">
                                <input type="hidden" name="redirect_query" value="<?= e($exportQuery); ?>">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Device Type</label>
                                        <select name="device_type" class="form-select" data-device-type-select required>
                                            <?php foreach (komputer_inventory_device_type_options() as $deviceType => $deviceLabel): ?>
                                                <option value="<?= e($deviceType); ?>" <?= ($computer['device_type'] ?? 'CLIENT') === $deviceType ? 'selected' : ''; ?>><?= e($deviceLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Hostname</label>
                                        <input type="text" name="hostname" class="form-control" value="<?= e((string) $computer['hostname']); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Username Windows</label>
                                        <input type="text" name="username" class="form-control" value="<?= e((string) ($computer['username'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">IP Address</label>
                                        <input type="text" name="ip_address" class="form-control" value="<?= e((string) ($computer['ip_address'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-8 <?= ($computer['device_type'] ?? 'CLIENT') === 'SERVER' ? 'd-none' : ''; ?>" data-client-only-fields>
                                        <label class="form-label">IP Tambahan</label>
                                        <textarea name="client_multiple_ip" class="form-control" rows="2" data-client-filter-field><?= e(implode(PHP_EOL, $editClientSecondaryIps)); ?></textarea>
                                        <div class="form-text">Isi IP selain IP utama di atas. Pisahkan dengan baris baru atau koma.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">MAC Address</label>
                                        <input type="text" name="mac_address" class="form-control" value="<?= e((string) ($computer['mac_address'] ?? '')); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Merk / Brand</label>
                                        <input type="text" name="merk" class="form-control" value="<?= e((string) ($computer['merk'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Model</label>
                                        <input type="text" name="model" class="form-control" value="<?= e((string) ($computer['model'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Processor</label>
                                        <input type="text" name="processor" class="form-control" value="<?= e((string) ($computer['processor'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Jumlah Core</label>
                                        <input type="number" name="core" class="form-control" value="<?= e((string) ($computer['core'] ?? 0)); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Kondisi Device</label>
                                        <select name="kondisi" class="form-select" required>
                                            <?php foreach (['Baik', 'Rusak', 'Perbaikan'] as $conditionOption): ?>
                                                <option value="<?= e($conditionOption); ?>" <?= ($computer['kondisi'] ?? 'Baik') === $conditionOption ? 'selected' : ''; ?>><?= e($conditionOption); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">RAM Total</label>
                                        <input type="text" name="ram" class="form-control" value="<?= e((string) ($computer['ram'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">SSD</label>
                                        <textarea name="ssd" class="form-control" rows="2"><?= e((string) ($computer['ssd'] ?? '')); ?></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">HDD</label>
                                        <textarea name="hdd" class="form-control" rows="2"><?= e((string) ($computer['hdd'] ?? '')); ?></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">VGA / GPU</label>
                                        <textarea name="vga" class="form-control" rows="2"><?= e((string) ($computer['vga'] ?? '')); ?></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Motherboard</label>
                                        <input type="text" name="motherboard" class="form-control" value="<?= e((string) ($computer['motherboard'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Nama OS</label>
                                        <input type="text" name="os_name" class="form-control" value="<?= e((string) ($computer['os_name'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Versi OS</label>
                                        <input type="text" name="os_version" class="form-control" value="<?= e((string) ($computer['os_version'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Arsitektur</label>
                                        <input type="text" name="architecture" class="form-control" value="<?= e((string) ($computer['architecture'] ?? '')); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tahun Inventaris</label>
                                        <input type="number" name="tahun_inventaris" class="form-control" value="<?= e((string) ($computer['tahun_inventaris'] ?: date('Y'))); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Nama User / Petugas</label>
                                        <input type="text" name="nama_user" class="form-control" value="<?= e((string) ($computer['petugas'] ?? '')); ?>" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Ruangan</label>
                                        <select name="ruangan" class="form-select" required>
                                            <option value="">Pilih ruangan</option>
                                            <?php foreach ($roomOptions as $roomOption): ?>
                                                <option value="<?= e($roomOption); ?>" <?= ($computer['ruangan'] ?? '') === $roomOption ? 'selected' : ''; ?>><?= e($roomOption); ?></option>
                                            <?php endforeach; ?>
                                            <?php if (($computer['ruangan'] ?? '') !== '' && !in_array($computer['ruangan'], $roomOptions, true)): ?>
                                                <option value="<?= e((string) $computer['ruangan']); ?>" selected><?= e((string) $computer['ruangan']); ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="border rounded-4 p-3 mt-4 <?= ($computer['device_type'] ?? 'CLIENT') === 'SERVER' ? '' : 'd-none'; ?>" data-server-only-fields>
                                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                                        <div>
                                            <h6 class="mb-1">Detail Tambahan Server</h6>
                                            <p class="text-muted mb-0 small">Field ini hanya dipakai jika device diklasifikasikan sebagai server.</p>
                                        </div>
                                        <span class="badge text-bg-warning">SERVER DETAIL</span>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Virtualization</label>
                                            <input type="text" name="virtualization" class="form-control" value="<?= e((string) ($computer['virtualization'] ?? '')); ?>" data-server-filter-field>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">RAID</label>
                                            <input type="text" name="raid" class="form-control" value="<?= e((string) ($computer['raid'] ?? '')); ?>" data-server-filter-field>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Hypervisor</label>
                                            <input type="text" name="hypervisor" class="form-control" value="<?= e((string) ($computer['hypervisor'] ?? '')); ?>" data-server-filter-field>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Uptime</label>
                                            <input type="text" name="uptime" class="form-control" value="<?= e((string) ($computer['uptime'] ?? '')); ?>" data-server-filter-field>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Server Role</label>
                                            <input type="text" name="server_role" class="form-control" value="<?= e((string) ($computer['server_role'] ?? '')); ?>" data-server-filter-field>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Multiple IP</label>
                                            <textarea name="multiple_ip" class="form-control" rows="3" data-server-filter-field><?= e((string) ($computer['multiple_ip'] ?? '')); ?></textarea>
                                            <div class="form-text">Pisahkan dengan baris baru atau koma bila ada lebih dari satu IP.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="deleteComputerModal<?= (int) $computer['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post">
                            <div class="modal-header">
                                <h5 class="modal-title">Hapus Data Device</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $computer['id']; ?>">
                                <input type="hidden" name="redirect_query" value="<?= e($exportQuery); ?>">
                                <p class="mb-0">Yakin ingin menghapus data <strong><?= e((string) $computer['hostname']); ?></strong>?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-danger">Hapus</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="createServerModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Server</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create_server">
                    <input type="hidden" name="device_type" value="SERVER">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Hostname</label>
                            <input type="text" name="hostname" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Username Windows</label>
                            <input type="text" name="username" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">IP Address</label>
                            <input type="text" name="ip_address" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">MAC Address</label>
                            <input type="text" name="mac_address" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Merk / Brand</label>
                            <input type="text" name="merk" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Processor</label>
                            <input type="text" name="processor" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Core</label>
                            <input type="number" name="core" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kondisi Device</label>
                            <select name="kondisi" class="form-select" required>
                                <?php foreach (['Baik', 'Rusak', 'Perbaikan'] as $conditionOption): ?>
                                    <option value="<?= e($conditionOption); ?>"><?= e($conditionOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">RAM Total</label>
                            <input type="text" name="ram" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nama OS</label>
                            <input type="text" name="os_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Versi OS</label>
                            <input type="text" name="os_version" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Arsitektur</label>
                            <input type="text" name="architecture" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tahun Inventaris</label>
                            <input type="number" name="tahun_inventaris" class="form-control" value="<?= e(date('Y')); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nama User / Petugas</label>
                            <input type="text" name="nama_user" class="form-control" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Ruangan</label>
                            <select name="ruangan" class="form-select" required>
                                <option value="">Pilih ruangan</option>
                                <?php foreach ($roomOptions as $roomOption): ?>
                                    <option value="<?= e($roomOption); ?>"><?= e($roomOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">SSD</label>
                            <textarea name="ssd" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">HDD</label>
                            <textarea name="hdd" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">VGA / GPU</label>
                            <textarea name="vga" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Motherboard</label>
                            <input type="text" name="motherboard" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Virtualization</label>
                            <input type="text" name="virtualization" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">RAID</label>
                            <input type="text" name="raid" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hypervisor</label>
                            <input type="text" name="hypervisor" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Uptime</label>
                            <input type="text" name="uptime" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Server Role</label>
                            <input type="text" name="server_role" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Multiple IP</label>
                            <textarea name="multiple_ip" class="form-control" rows="3"></textarea>
                            <div class="form-text">Pisahkan dengan baris baru atau koma bila ada lebih dari satu IP.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Server</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
