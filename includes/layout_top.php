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
$currentDateLabel = function_exists('format_date_id') ? format_date_id(date('Y-m-d')) : date('d M Y');
$currentTimeLabel = date('H:i:s');

$renderAppIcon = static function (string $icon): string {
    switch ($icon) {
        case 'dashboard':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="8" height="7" rx="1.5"/><rect x="13" y="4" width="8" height="5" rx="1.5"/><rect x="13" y="11" width="8" height="9" rx="1.5"/><rect x="3" y="13" width="8" height="7" rx="1.5"/></svg>';
        case 'room':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V7l8-3 8 3v13"/><path d="M9 20v-5h6v5"/><path d="M9 9h.01"/><path d="M15 9h.01"/></svg>';
        case 'box':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M12 12l8-4.5"/><path d="M12 12v9"/><path d="M12 12L4 7.5"/></svg>';
        case 'arrow-down':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4v12"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/></svg>';
        case 'arrow-up':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20V8"/><path d="M17 13l-5-5-5 5"/><path d="M5 4h14"/></svg>';
        case 'history':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v6l4 2"/></svg>';
        case 'report':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6"/><path d="M10 17h6"/><path d="M10 9h1"/></svg>';
        case 'computer':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/></svg>';
        case 'shield':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l7 3v6c0 4.6-2.9 7.9-7 9-4.1-1.1-7-4.4-7-9V6l7-3z"/><path d="M9.5 12.5l1.8 1.8 3.7-4.3"/></svg>';
        case 'swap':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 7h12"/><path d="M15 3l4 4-4 4"/><path d="M17 17H5"/><path d="M9 13l-4 4 4 4"/></svg>';
        case 'server':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="6" rx="2"/><rect x="4" y="14" width="16" height="6" rx="2"/><path d="M8 7h.01"/><path d="M8 17h.01"/><path d="M16 7h2"/><path d="M16 17h2"/></svg>';
        case 'development':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 4l6 6-9 9H5v-6z"/><path d="M13 5l6 6"/><path d="M3 21h18"/></svg>';
        case 'calendar':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4"/><path d="M8 3v4"/><path d="M3 10h18"/></svg>';
        case 'lock':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 1 1 8 0v3"/></svg>';
        case 'user':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 1 0-16 0"/><circle cx="12" cy="8" r="4"/></svg>';
        case 'logout':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4"/><path d="M13 16l5-4-5-4"/><path d="M18 12H9"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/></svg>';
    }
};

$adminNavigation = [
    ['path' => 'modules/dashboard/index.php', 'match' => '/modules/dashboard/', 'label' => 'Dashboard', 'icon' => 'dashboard'],
    ['path' => 'modules/ruangan/index.php', 'match' => '/modules/ruangan/', 'label' => 'Data Ruangan', 'icon' => 'room'],
    ['path' => 'modules/barang/index.php', 'match' => '/modules/barang/', 'label' => 'Data Barang', 'icon' => 'box'],
    ['path' => 'modules/transaksi/masuk.php', 'match' => '/modules/transaksi/masuk.php', 'label' => 'Barang Masuk', 'icon' => 'arrow-down'],
    ['path' => 'modules/transaksi/keluar.php', 'match' => '/modules/transaksi/keluar.php', 'label' => 'Barang Keluar', 'icon' => 'arrow-up'],
    ['path' => 'modules/transaksi/history.php', 'match' => '/modules/transaksi/history.php', 'label' => 'History', 'icon' => 'history'],
    ['path' => 'modules/laporan/index.php', 'match' => '/modules/laporan/', 'label' => 'Laporan', 'icon' => 'report'],
    ['path' => 'modules/komputer/index.php', 'match' => '/modules/komputer/', 'label' => 'Komputer Client', 'icon' => 'computer'],
    ['path' => 'modules/kondisi_komputer/index.php', 'match' => '/modules/kondisi_komputer/', 'label' => 'Data Kondisi Komputer', 'icon' => 'shield'],
    ['path' => 'modules/mutasi_komputer/index.php', 'match' => '/modules/mutasi_komputer/', 'label' => 'Mutasi Komputer', 'icon' => 'swap'],
    ['path' => 'modules/monitoring_ruangan/dashboard.php', 'match' => '/modules/monitoring_ruangan/', 'label' => 'Monitoring Ruangan Server', 'icon' => 'server'],
];

$sharedNavigation = [
    ['path' => 'modules/pengembangan/index.php', 'match' => '/modules/pengembangan/', 'label' => 'Laporan Pengembangan', 'icon' => 'development'],
];
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
    <style>
        body.app-admin-body .table,
        body.app-admin-body table.dataTable,
        body.app-admin-body .dataTables_wrapper table {
            --bs-table-color: #eaf4ff;
            --bs-table-bg: rgba(3, 11, 24, 0.88);
            --bs-table-accent-bg: rgba(3, 11, 24, 0.88);
            --bs-table-border-color: rgba(70, 102, 152, 0.18);
            --bs-table-striped-color: #eef7ff;
            --bs-table-striped-bg: rgba(5, 16, 34, 0.94);
            --bs-table-hover-color: #ffffff;
            --bs-table-hover-bg: rgba(7, 31, 69, 0.94);
            color: #eaf4ff !important;
            background: transparent !important;
        }

        body.app-admin-body .table-responsive,
        body.app-admin-body .dataTables_wrapper .table-responsive,
        body.app-admin-body .card .table-responsive {
            border: 1px solid rgba(41, 109, 196, 0.22) !important;
            border-radius: 18px !important;
            background: linear-gradient(180deg, rgba(6, 16, 36, 0.98) 0%, rgba(3, 10, 22, 1) 100%) !important;
            overflow: hidden !important;
        }

        body.app-admin-body .table thead th,
        body.app-admin-body table.dataTable thead th,
        body.app-admin-body .dataTables_wrapper table thead th {
            color: #f5fbff !important;
            background: linear-gradient(180deg, rgba(8, 20, 45, 1) 0%, rgba(5, 14, 30, 1) 100%) !important;
            border-bottom: 1px solid rgba(72, 109, 162, 0.3) !important;
            font-weight: 700 !important;
            box-shadow: none !important;
        }

        body.app-admin-body .table > :not(caption) > * > *,
        body.app-admin-body table.dataTable > :not(caption) > * > *,
        body.app-admin-body .dataTables_wrapper table > :not(caption) > * > * {
            background-color: rgba(3, 11, 24, 0.88) !important;
            color: #eaf4ff !important;
            border-color: rgba(70, 102, 152, 0.18) !important;
        }

        body.app-admin-body .table tbody tr:nth-child(even) > *,
        body.app-admin-body table.dataTable tbody tr:nth-child(even) > *,
        body.app-admin-body .dataTables_wrapper table tbody tr:nth-child(even) > *,
        body.app-admin-body table.dataTable.table-striped > tbody > tr:nth-of-type(2n+1) > *,
        body.app-admin-body .dataTables_wrapper table.table-striped > tbody > tr:nth-of-type(2n+1) > * {
            background-color: rgba(5, 16, 34, 0.94) !important;
            color: #eef7ff !important;
        }

        body.app-admin-body .table-hover > tbody > tr:hover > *,
        body.app-admin-body table.dataTable.table-hover > tbody > tr:hover > *,
        body.app-admin-body .dataTables_wrapper .table-hover > tbody > tr:hover > * {
            background-color: rgba(7, 31, 69, 0.96) !important;
            color: #ffffff !important;
        }

        body.app-admin-body table.dataTable tbody tr > .sorting_1,
        body.app-admin-body table.dataTable tbody tr > .sorting_2,
        body.app-admin-body table.dataTable tbody tr > .sorting_3,
        body.app-admin-body .dataTables_wrapper table tbody tr > .sorting_1,
        body.app-admin-body .dataTables_wrapper table tbody tr > .sorting_2,
        body.app-admin-body .dataTables_wrapper table tbody tr > .sorting_3 {
            background-color: rgba(6, 17, 37, 0.96) !important;
            color: #eef7ff !important;
            box-shadow: none !important;
        }

        body.app-admin-body .table td strong,
        body.app-admin-body .table th strong,
        body.app-admin-body .table td .text-muted,
        body.app-admin-body .table th .text-muted,
        body.app-admin-body .table td .small,
        body.app-admin-body .table th .small,
        body.app-admin-body table.dataTable td .text-muted,
        body.app-admin-body table.dataTable th .text-muted,
        body.app-admin-body .dataTables_wrapper table td .text-muted,
        body.app-admin-body .dataTables_wrapper table th .text-muted {
            color: inherit !important;
        }

        body.app-admin-body .dataTables_wrapper .dataTables_length,
        body.app-admin-body .dataTables_wrapper .dataTables_filter,
        body.app-admin-body .dataTables_wrapper .dataTables_info,
        body.app-admin-body .dataTables_wrapper .dataTables_paginate,
        body.app-admin-body .dataTables_wrapper .dataTables_length label,
        body.app-admin-body .dataTables_wrapper .dataTables_filter label {
            color: rgba(214, 230, 250, 0.88) !important;
        }

        body.app-admin-body .dataTables_wrapper .dataTables_filter input,
        body.app-admin-body .dataTables_wrapper .dataTables_length select {
            background: rgba(5, 15, 34, 0.94) !important;
            color: #eff7ff !important;
            border: 1px solid rgba(60, 118, 200, 0.34) !important;
        }

        body.app-admin-body .text-muted,
        body.app-admin-body .small,
        body.app-admin-body small,
        body.app-admin-body .form-text,
        body.app-admin-body .form-label,
        body.app-admin-body label,
        body.app-admin-body legend,
        body.app-admin-body .form-select,
        body.app-admin-body select,
        body.app-admin-body .form-select option,
        body.app-admin-body select option,
        body.app-admin-body .form-select optgroup,
        body.app-admin-body select optgroup,
        body.app-admin-body .card p,
        body.app-admin-body .card small,
        body.app-admin-body .dataTables_wrapper .dataTables_info,
        body.app-admin-body .dataTables_wrapper .dataTables_length,
        body.app-admin-body .dataTables_wrapper .dataTables_filter,
        body.app-admin-body .dataTables_wrapper label,
        body.app-admin-body .table td .text-muted,
        body.app-admin-body .table th .text-muted,
        body.app-admin-body .table td .small,
        body.app-admin-body .table th .small {
            color: rgba(241, 248, 255, 0.88) !important;
        }

        body.app-admin-body .table td strong,
        body.app-admin-body .table th strong,
        body.app-admin-body h1,
        body.app-admin-body h2,
        body.app-admin-body h3,
        body.app-admin-body h4,
        body.app-admin-body h5,
        body.app-admin-body h6 {
            color: #f8fbff !important;
        }

        body.app-admin-body .nav-tabs,
        body.app-admin-body .nav-tabs .nav-link,
        body.app-admin-body .nav-pills .nav-link {
            color: rgba(235, 245, 255, 0.92) !important;
            border-color: rgba(66, 110, 168, 0.3) !important;
        }

        body.app-admin-body .nav-tabs .nav-link.active,
        body.app-admin-body .nav-pills .nav-link.active {
            color: #ffffff !important;
            background: rgba(7, 31, 69, 0.96) !important;
            border-color: rgba(70, 123, 190, 0.42) !important;
        }

        body.app-admin-body .alert-info,
        body.app-admin-body .alert-info *,
        body.app-admin-body .alert-warning,
        body.app-admin-body .alert-warning *,
        body.app-admin-body .alert-primary,
        body.app-admin-body .alert-primary * {
            color: #f8fbff !important;
        }

        body.app-admin-body .alert-info,
        body.app-admin-body .alert-warning,
        body.app-admin-body .alert-primary {
            background: linear-gradient(180deg, rgba(15, 42, 82, 0.92) 0%, rgba(10, 28, 56, 0.96) 100%) !important;
            border: 1px solid rgba(86, 143, 214, 0.24) !important;
        }

        body.app-admin-body .badge,
        body.app-admin-body .badge * {
            color: #f8fbff !important;
        }

        body.app-admin-body .dataTables_wrapper .paginate_button,
        body.app-admin-body .dataTables_wrapper .paginate_button a,
        body.app-admin-body .dataTables_wrapper .paginate_button span,
        body.app-admin-body .dataTables_wrapper .paginate_button.disabled,
        body.app-admin-body .dataTables_wrapper .paginate_button.disabled * {
            color: #eaf4ff !important;
        }
    </style>
</head>
<body class="app-admin-body">
    <div class="app-shell">
        <button class="btn btn-primary mobile-menu-fab d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
            Menu
        </button>

        <aside class="sidebar d-none d-xl-flex flex-column">
            <div class="sidebar-brand">
                <div class="brand-mark brand-mark-sidebar">
                    <img src="<?= e(url(APP_LOGO_PATH)); ?>" alt="Logo SiAEGIS" class="brand-logo">
                </div>
                <div class="brand-copy">
                    <h1>SiAEGIS</h1>
                    <p>Inventory System</p>
                </div>
            </div>

            <nav class="nav flex-column sidebar-nav">
                <?php if ($isAdminUser): ?>
                    <?php foreach ($adminNavigation as $navItem): ?>
                        <a class="nav-link <?= is_active_menu($navItem['match']) ? 'active' : ''; ?>" href="<?= e(url($navItem['path'])); ?>">
                            <span class="nav-link-icon"><?= $renderAppIcon($navItem['icon']); ?></span>
                            <span><?= e($navItem['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php foreach ($sharedNavigation as $navItem): ?>
                    <a class="nav-link <?= is_active_menu($navItem['match']) ? 'active' : ''; ?>" href="<?= e(url($navItem['path'])); ?>">
                        <span class="nav-link-icon"><?= $renderAppIcon($navItem['icon']); ?></span>
                        <span><?= e($navItem['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-system-card mt-auto">
                <div class="sidebar-system-icon"><?= $renderAppIcon('shield'); ?></div>
                <div>
                    <strong>Secure System</strong>
                    <span>All systems protected</span>
                </div>
            </div>

            <a href="<?= e(url('logout.php')); ?>" class="btn btn-sidebar-logout">
                <span class="nav-link-icon"><?= $renderAppIcon('logout'); ?></span>
                <span>Logout</span>
            </a>
        </aside>

        <div class="content-wrapper">
            <header class="topbar">
                <div class="topbar-heading d-flex align-items-center gap-3">
                    <button class="btn btn-light d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
                        Menu
                    </button>
                    <div>
                        <h2 class="mb-0"><?= e($pageTitle); ?></h2>
                        <small class="text-muted"><?= e($pageSubtitle); ?></small>
                    </div>
                </div>
                <div class="topbar-meta">
                    <div class="topbar-panel-card">
                        <span class="topbar-panel-icon"><?= $renderAppIcon('calendar'); ?></span>
                        <div>
                            <strong data-live-date><?= e($currentDateLabel); ?></strong>
                            <span data-live-time><?= e($currentTimeLabel); ?> WIB</span>
                        </div>
                    </div>
                    <div class="topbar-panel-card">
                        <span class="topbar-panel-icon"><?= $renderAppIcon('lock'); ?></span>
                        <div>
                            <strong>Secure Connection</strong>
                            <span>Encrypted &amp; Protected</span>
                        </div>
                        <i class="topbar-status-dot"></i>
                    </div>
                    <div class="topbar-panel-card">
                        <span class="topbar-panel-icon"><?= $renderAppIcon('user'); ?></span>
                        <div>
                            <strong><?= e($currentUser['nama'] ?? 'Administrator'); ?></strong>
                            <span><?= e($currentUser['username'] ?? 'admin'); ?></span>
                        </div>
                        <i class="topbar-status-dot"></i>
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
                            <h5 class="offcanvas-title mb-0">SiAEGIS</h5>
                            <div class="small text-muted">Inventory System</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                </div>
                <div class="offcanvas-body">
                    <nav class="nav flex-column sidebar-nav">
                        <?php if ($isAdminUser): ?>
                            <?php foreach ($adminNavigation as $navItem): ?>
                                <a class="nav-link <?= is_active_menu($navItem['match']) ? 'active' : ''; ?>" href="<?= e(url($navItem['path'])); ?>">
                                    <span class="nav-link-icon"><?= $renderAppIcon($navItem['icon']); ?></span>
                                    <span><?= e($navItem['label']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php foreach ($sharedNavigation as $navItem): ?>
                            <a class="nav-link <?= is_active_menu($navItem['match']) ? 'active' : ''; ?>" href="<?= e(url($navItem['path'])); ?>">
                                <span class="nav-link-icon"><?= $renderAppIcon($navItem['icon']); ?></span>
                                <span><?= e($navItem['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                        <a class="nav-link text-danger" href="<?= e(url('logout.php')); ?>">
                            <span class="nav-link-icon"><?= $renderAppIcon('logout'); ?></span>
                            <span>Logout</span>
                        </a>
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

                <script>
                    (function () {
                        const dateElement = document.querySelector('[data-live-date]');
                        const timeElement = document.querySelector('[data-live-time]');
                        if (!dateElement && !timeElement) {
                            return;
                        }

                        const dateFormatter = new Intl.DateTimeFormat('id-ID', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                            timeZone: 'Asia/Jakarta'
                        });

                        const timeFormatter = new Intl.DateTimeFormat('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false,
                            timeZone: 'Asia/Jakarta'
                        });

                        const updateTopbarClock = function () {
                            const now = new Date();
                            if (dateElement) {
                                dateElement.textContent = dateFormatter.format(now);
                            }
                            if (timeElement) {
                                timeElement.textContent = timeFormatter.format(now) + ' WIB';
                            }
                        };

                        updateTopbarClock();
                        window.setInterval(updateTopbarClock, 1000);
                    })();
                </script>

                <?php if ($showLowStockAlert && $lowStockItems): ?>
                    <div class="alert alert-warning border-0 shadow-sm system-alert-banner">
                        <span class="system-alert-label">Perhatian:</span>
                        <span class="system-alert-copy">Ada barang dengan stok di bawah 5 unit.</span>
                        <?php foreach ($lowStockItems as $item): ?>
                            <span class="badge text-bg-light me-1 mt-2"><?= e($item['nama_barang']); ?> (<?= e($item['qty']); ?>)</span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
