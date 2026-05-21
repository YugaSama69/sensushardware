<?php
$monitoringNavActive = $monitoringNavActive ?? '';
$monitoringNavItems = [
    'dashboard' => ['label' => 'Dashboard Monitoring', 'href' => url('modules/monitoring_ruangan/dashboard.php')],
    'form' => ['label' => 'Form Monitoring Baru', 'href' => url('modules/monitoring_ruangan/form.php')],
    'histori' => ['label' => 'Histori Monitoring', 'href' => url('modules/monitoring_ruangan/histori.php')],
    'master_ruangan' => ['label' => 'Master Ruangan', 'href' => url('modules/monitoring_ruangan/master_ruangan.php')],
    'master_petugas' => ['label' => 'Master Petugas', 'href' => url('modules/monitoring_ruangan/master_petugas.php')],
];
?>

<div class="monitoring-nav mb-4">
    <?php foreach ($monitoringNavItems as $navKey => $navItem): ?>
        <a
            href="<?= e($navItem['href']); ?>"
            class="monitoring-nav-link <?= $monitoringNavActive === $navKey ? 'active' : ''; ?>"
        ><?= e($navItem['label']); ?></a>
    <?php endforeach; ?>
</div>
