<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Komputer Client';
$errors = [];
$filters = normalize_computer_client_filters($_GET);
$redirectQuery = trim($_POST['redirect_query'] ?? '');

if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $statement = $pdo->prepare('DELETE FROM komputer_client WHERE id = :id');
        $statement->execute(['id' => $id]);
        set_flash('success', 'Data komputer client berhasil dihapus.');
        redirect('modules/komputer/index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
    }

    if ($action === 'update' && $id > 0) {
        $payload = [
            'hostname' => trim($_POST['hostname'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'ip_address' => trim($_POST['ip_address'] ?? ''),
            'mac_address' => trim($_POST['mac_address'] ?? ''),
            'merk' => trim($_POST['merk'] ?? ''),
            'model' => trim($_POST['model'] ?? ''),
            'processor' => trim($_POST['processor'] ?? ''),
            'core' => (int) ($_POST['core'] ?? 0),
            'ram' => trim($_POST['ram'] ?? ''),
            'ssd' => trim($_POST['ssd'] ?? ''),
            'hdd' => trim($_POST['hdd'] ?? ''),
            'vga' => trim($_POST['vga'] ?? ''),
            'motherboard' => trim($_POST['motherboard'] ?? ''),
            'os_name' => trim($_POST['os_name'] ?? ''),
            'os_version' => trim($_POST['os_version'] ?? ''),
            'architecture' => trim($_POST['architecture'] ?? ''),
            'tahun_inventaris' => trim($_POST['tahun_inventaris'] ?? date('Y')),
            'ruangan' => trim($_POST['ruangan'] ?? ''),
            'petugas' => trim($_POST['nama_user'] ?? ($_POST['petugas'] ?? '')),
        ];

        if ($payload['hostname'] === '') {
            $errors[] = 'Hostname wajib diisi.';
        }

        if ($payload['mac_address'] === '') {
            $errors[] = 'MAC address wajib diisi.';
        }

        if ($payload['ruangan'] === '') {
            $errors[] = 'Ruangan wajib diisi.';
        }

        if ($payload['petugas'] === '') {
            $errors[] = 'Nama user wajib diisi.';
        }

        if (!preg_match('/^\d{4}$/', $payload['tahun_inventaris'])) {
            $errors[] = 'Tahun inventaris harus 4 digit.';
        }

        if (!$errors) {
            $statement = $pdo->prepare('
                UPDATE komputer_client
                SET hostname = :hostname,
                    username = :username,
                    ip_address = :ip_address,
                    mac_address = :mac_address,
                    merk = :merk,
                    model = :model,
                    processor = :processor,
                    core = :core,
                    ram = :ram,
                    ssd = :ssd,
                    hdd = :hdd,
                    vga = :vga,
                    motherboard = :motherboard,
                    os_name = :os_name,
                    os_version = :os_version,
                    architecture = :architecture,
                    tahun_inventaris = :tahun_inventaris,
                    ruangan = :ruangan,
                    petugas = :petugas
                WHERE id = :id
            ');
            $payload['id'] = $id;
            $statement->execute($payload);
            set_flash('success', 'Data komputer client berhasil diperbarui.');
            redirect('modules/komputer/index.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
        }
    }
}

$computers = get_computer_client_rows($pdo, $filters);
$filterOptions = get_computer_client_filter_options($pdo);
$exportQuery = build_query_string($filters);
$roomOptions = get_room_name_options($pdo);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4">
        <h5 class="mb-1">Dashboard Komputer Client</h5>
        <p class="text-muted mb-0">Data hasil pendataan otomatis dari komputer client rumah sakit.</p>
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

        <form method="get" class="row g-3 align-items-end">
            <?php
            $filterLabels = [
                'merk' => 'Merk',
                'processor' => 'Processor',
                'ram' => 'RAM',
                'storage' => 'SSD / HDD',
                'os_name' => 'OS',
                'tahun_inventaris' => 'Tahun Inventaris',
                'ruangan' => 'Ruangan',
            ];
            ?>
            <?php foreach ($filterLabels as $name => $label): ?>
                <div class="col-xl-3 col-md-4">
                    <label class="form-label"><?= e($label); ?></label>
                    <select name="<?= e($name); ?>" class="form-select">
                        <option value="">Semua <?= e($label); ?></option>
                        <?php foreach ($filterOptions[$name] as $option): ?>
                            <option value="<?= e($option); ?>" <?= $filters[$name] === $option ? 'selected' : ''; ?>>
                                <?= e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endforeach; ?>
            <div class="col-12 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="<?= e(url('modules/komputer/index.php')); ?>" class="btn btn-light">Reset</a>
                <a href="<?= e(url('modules/komputer/export_excel.php' . ($exportQuery !== '' ? '?' . $exportQuery : ''))); ?>" class="btn btn-success">Export Excel</a>
                <a href="<?= e(url('pendataan/index.php')); ?>" class="btn btn-outline-dark" target="_blank">Buka Halaman Client</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
        <div>
            <h5 class="mb-1">Data Komputer Client</h5>
            <p class="text-muted mb-0"><?= count($computers); ?> komputer terdata.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Hostname</th>
                        <th>IP</th>
                        <th>Merk</th>
                        <th>Processor</th>
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
                    <?php foreach ($computers as $index => $computer): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td>
                                <strong><?= e($computer['hostname']); ?></strong>
                                <div class="small text-muted"><?= e($computer['username'] ?: '-'); ?> | <?= e($computer['mac_address']); ?></div>
                            </td>
                            <td><?= e($computer['ip_address'] ?: '-'); ?></td>
                            <td>
                                <?= e($computer['merk'] ?: '-'); ?>
                                <div class="small text-muted"><?= e($computer['model'] ?: '-'); ?></div>
                            </td>
                            <td>
                                <?= e($computer['processor'] ?: '-'); ?>
                                <div class="small text-muted"><?= e((string) ($computer['core'] ?: 0)); ?> core</div>
                            </td>
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
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editComputerModal<?= $computer['id']; ?>">Edit</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteComputerModal<?= $computer['id']; ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($computers as $computer): ?>
    <div class="modal fade" id="editComputerModal<?= $computer['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Data Komputer Client</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $computer['id']; ?>">
                        <input type="hidden" name="redirect_query" value="<?= e($exportQuery); ?>">
                        <div class="row g-3">
                            <?php
                            $inputs = [
                                'hostname' => 'Hostname',
                                'username' => 'Username Windows',
                                'ip_address' => 'IP Address',
                                'mac_address' => 'MAC Address',
                                'merk' => 'Merk / Brand',
                                'model' => 'Model',
                                'processor' => 'Processor',
                                'core' => 'Jumlah Core',
                                'ram' => 'RAM Total',
                                'ssd' => 'SSD',
                                'hdd' => 'HDD',
                                'vga' => 'VGA / GPU',
                                'motherboard' => 'Motherboard',
                                'os_name' => 'Nama OS',
                                'os_version' => 'Versi OS',
                                'architecture' => 'Arsitektur',
                                'tahun_inventaris' => 'Tahun Inventaris',
                                'ruangan' => 'Ruangan',
                                'nama_user' => 'Nama User',
                            ];
                            ?>
                            <?php foreach ($inputs as $name => $label): ?>
                                <?php $fieldName = $name === 'nama_user' ? 'petugas' : $name; ?>
                                <div class="col-md-<?= in_array($name, ['ssd', 'hdd', 'vga'], true) ? '12' : '4'; ?>">
                                    <label class="form-label"><?= e($label); ?></label>
                                    <?php if ($name === 'ruangan'): ?>
                                        <select name="ruangan" class="form-select" required>
                                            <option value="">Pilih ruangan</option>
                                            <?php foreach ($roomOptions as $roomOption): ?>
                                                <option value="<?= e($roomOption); ?>" <?= $computer['ruangan'] === $roomOption ? 'selected' : ''; ?>>
                                                    <?= e($roomOption); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if ($computer['ruangan'] !== '' && !in_array($computer['ruangan'], $roomOptions, true)): ?>
                                                <option value="<?= e($computer['ruangan']); ?>" selected><?= e($computer['ruangan']); ?></option>
                                            <?php endif; ?>
                                        </select>
                                        <div class="form-text">Gunakan dropdown ini untuk memindahkan komputer ke ruangan lain yang sudah terdata.</div>
                                    <?php elseif (in_array($name, ['ssd', 'hdd', 'vga'], true)): ?>
                                        <textarea name="<?= e($name); ?>" class="form-control" rows="2"><?= e($computer[$fieldName]); ?></textarea>
                                    <?php else: ?>
                                        <input type="<?= in_array($name, ['core', 'tahun_inventaris'], true) ? 'number' : 'text'; ?>" name="<?= e($name); ?>" class="form-control" value="<?= e((string) ($computer[$fieldName] ?: ($name === 'tahun_inventaris' ? date('Y') : ''))); ?>" <?= in_array($name, ['hostname', 'mac_address', 'tahun_inventaris', 'ruangan', 'nama_user'], true) ? 'required' : ''; ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
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

    <div class="modal fade" id="deleteComputerModal<?= $computer['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Data Komputer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $computer['id']; ?>">
                        <input type="hidden" name="redirect_query" value="<?= e($exportQuery); ?>">
                        <p class="mb-0">Yakin ingin menghapus data komputer <strong><?= e($computer['hostname']); ?></strong>?</p>
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

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
