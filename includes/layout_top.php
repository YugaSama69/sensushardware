<?php

require_login();
require_module_access();

$pageTitle = $pageTitle ?? APP_NAME;
$pageSubtitle = $pageSubtitle ?? 'Kelola inventaris hardware dan elektronik secara real-time.';
$lowStockItems = get_low_stock_items($pdo);
$currentUser = current_user();
$flash = get_flash();
$isAdminUser = is_admin_user();
$showLowStockAlert = !is_active_menu('/modules/pengembangan/') && !is_active_menu('/modules/monitoring_ruangan/');
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle); ?> - <?= e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= e(url(APP_FAVICON_PATH)); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body>
    <div class="app-shell">
        <button class="btn btn-primary mobile-menu-fab d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
            Menu
        </button>

        <aside class="sidebar d-none d-xl-flex flex-column">
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <img src="<?= e(url(APP_LOGO_PATH)); ?>" alt="Logo SiAEGIS" class="brand-logo">
                </div>
                <div>
                    <h1><?= e(APP_NAME); ?></h1>
                    <p>Admin Dashboard</p>
                </div>
            </div>

            <nav class="nav flex-column sidebar-nav">
                <?php if ($isAdminUser): ?>
                    <a class="nav-link <?= is_active_menu('/modules/dashboard/') ? 'active' : ''; ?>" href="<?= e(url('modules/dashboard/index.php')); ?>">Dashboard</a>
                    <a class="nav-link <?= is_active_menu('/modules/ruangan/') ? 'active' : ''; ?>" href="<?= e(url('modules/ruangan/index.php')); ?>">Data Ruangan</a>
                    <a class="nav-link <?= is_active_menu('/modules/barang/') ? 'active' : ''; ?>" href="<?= e(url('modules/barang/index.php')); ?>">Data Barang</a>
                    <a class="nav-link <?= is_active_menu('/modules/transaksi/masuk.php') ? 'active' : ''; ?>" href="<?= e(url('modules/transaksi/masuk.php')); ?>">Barang Masuk</a>
                    <a class="nav-link <?= is_active_menu('/modules/transaksi/keluar.php') ? 'active' : ''; ?>" href="<?= e(url('modules/transaksi/keluar.php')); ?>">Barang Keluar</a>
                    <a class="nav-link <?= is_active_menu('/modules/transaksi/history.php') ? 'active' : ''; ?>" href="<?= e(url('modules/transaksi/history.php')); ?>">History</a>
                    <a class="nav-link <?= is_active_menu('/modules/laporan/') ? 'active' : ''; ?>" href="<?= e(url('modules/laporan/index.php')); ?>">Laporan</a>
                    <a class="nav-link <?= is_active_menu('/modules/komputer/') ? 'active' : ''; ?>" href="<?= e(url('modules/komputer/index.php')); ?>">Komputer Client</a>
                    <a class="nav-link <?= is_active_menu('/modules/kondisi_komputer/') ? 'active' : ''; ?>" href="<?= e(url('modules/kondisi_komputer/index.php')); ?>">Data Kondisi Komputer</a>
                    <a class="nav-link <?= is_active_menu('/modules/mutasi_komputer/') ? 'active' : ''; ?>" href="<?= e(url('modules/mutasi_komputer/index.php')); ?>">Mutasi Komputer</a>
                    <a class="nav-link <?= is_active_menu('/modules/monitoring_ruangan/') ? 'active' : ''; ?>" href="<?= e(url('modules/monitoring_ruangan/dashboard.php')); ?>">Monitoring Ruangan Server</a>
                <?php endif; ?>
                <a class="nav-link <?= is_active_menu('/modules/pengembangan/') ? 'active' : ''; ?>" href="<?= e(url('modules/pengembangan/index.php')); ?>">Laporan Pengembangan</a>
            </nav>

            <a href="<?= e(url('logout.php')); ?>" class="btn btn-outline-light mt-auto">Logout</a>
        </aside>

        <div class="content-wrapper">
            <header class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-light d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
                        Menu
                    </button>
                    <div>
                        <h2 class="mb-0"><?= e($pageTitle); ?></h2>
                        <small class="text-muted"><?= e($pageSubtitle); ?></small>
                    </div>
                </div>
                <div class="topbar-meta">
                    <?php if ($showLowStockAlert): ?>
                        <div class="alert-pill <?= $lowStockItems ? 'alert-low' : 'alert-safe'; ?>">
                            <?= $lowStockItems ? count($lowStockItems) . ' stok menipis' : 'Stok aman'; ?>
                        </div>
                    <?php endif; ?>
                    <div class="user-pill">
                        <strong><?= e($currentUser['nama'] ?? 'Admin'); ?></strong>
                        <span><?= e($currentUser['username'] ?? '-'); ?></span>
                    </div>
                </div>
            </header>

            <div class="offcanvas offcanvas-start d-xl-none" tabindex="-1" id="mobileSidebar">
                <div class="offcanvas-header">
                    <div class="offcanvas-brand">
                        <div class="brand-mark brand-mark-mobile">
                            <img src="<?= e(url(APP_LOGO_PATH)); ?>" alt="Logo SiAEGIS" class="brand-logo">
                        </div>
                        <div>
                            <h5 class="offcanvas-title mb-0"><?= e(APP_NAME); ?></h5>
                            <div class="small text-muted">Admin Dashboard</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <nav class="nav flex-column sidebar-nav">
                        <?php if ($isAdminUser): ?>
                            <a class="nav-link <?= is_active_menu('/modules/dashboard/') ? 'active' : ''; ?>" href="<?= e(url('modules/dashboard/index.php')); ?>">Dashboard</a>
                            <a class="nav-link <?= is_active_menu('/modules/ruangan/') ? 'active' : ''; ?>" href="<?= e(url('modules/ruangan/index.php')); ?>">Data Ruangan</a>
                            <a class="nav-link <?= is_active_menu('/modules/barang/') ? 'active' : ''; ?>" href="<?= e(url('modules/barang/index.php')); ?>">Data Barang</a>
                            <a class="nav-link <?= is_active_menu('/modules/transaksi/masuk.php') ? 'active' : ''; ?>" href="<?= e(url('modules/transaksi/masuk.php')); ?>">Barang Masuk</a>
                            <a class="nav-link <?= is_active_menu('/modules/transaksi/keluar.php') ? 'active' : ''; ?>" href="<?= e(url('modules/transaksi/keluar.php')); ?>">Barang Keluar</a>
                            <a class="nav-link <?= is_active_menu('/modules/transaksi/history.php') ? 'active' : ''; ?>" href="<?= e(url('modules/transaksi/history.php')); ?>">History</a>
                            <a class="nav-link <?= is_active_menu('/modules/laporan/') ? 'active' : ''; ?>" href="<?= e(url('modules/laporan/index.php')); ?>">Laporan</a>
                            <a class="nav-link <?= is_active_menu('/modules/komputer/') ? 'active' : ''; ?>" href="<?= e(url('modules/komputer/index.php')); ?>">Komputer Client</a>
                            <a class="nav-link <?= is_active_menu('/modules/kondisi_komputer/') ? 'active' : ''; ?>" href="<?= e(url('modules/kondisi_komputer/index.php')); ?>">Data Kondisi Komputer</a>
                            <a class="nav-link <?= is_active_menu('/modules/mutasi_komputer/') ? 'active' : ''; ?>" href="<?= e(url('modules/mutasi_komputer/index.php')); ?>">Mutasi Komputer</a>
                            <a class="nav-link <?= is_active_menu('/modules/monitoring_ruangan/') ? 'active' : ''; ?>" href="<?= e(url('modules/monitoring_ruangan/dashboard.php')); ?>">Monitoring Ruangan Server</a>
                        <?php endif; ?>
                        <a class="nav-link <?= is_active_menu('/modules/pengembangan/') ? 'active' : ''; ?>" href="<?= e(url('modules/pengembangan/index.php')); ?>">Laporan Pengembangan</a>
                        <a class="nav-link text-danger" href="<?= e(url('logout.php')); ?>">Logout</a>
                    </nav>
                </div>
            </div>

            <main class="page-content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']); ?> alert-dismissible fade show">
                        <?= e($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($showLowStockAlert && $lowStockItems): ?>
                    <div class="alert alert-warning border-0 shadow-sm">
                        <strong>Perhatian:</strong> Ada barang dengan stok di bawah 5 unit.
                        <?php foreach ($lowStockItems as $item): ?>
                            <span class="badge text-bg-light me-1 mt-2"><?= e($item['nama_barang']); ?> (<?= e($item['qty']); ?>)</span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
