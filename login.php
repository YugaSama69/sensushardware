<?php

require_once __DIR__ . '/config/app.php';

require_guest();

$errors = [];

if (is_post()) {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    }

    if (!$errors && !attempt_login($pdo, $username, $password)) {
        $errors[] = 'Username atau password tidak valid.';
    }

    if (!$errors) {
        set_flash('success', 'Login berhasil. Selamat datang kembali.');
        redirect('modules/dashboard/index.php');
    }
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="login-body">
    <div class="container">
        <div class="row min-vh-100 align-items-center justify-content-center py-5">
            <div class="col-lg-10">
                <div class="row g-0 login-shell shadow-lg overflow-hidden rounded-4">
                    <div class="col-lg-6 login-cover d-none d-lg-flex">
                        <div class="p-5 d-flex flex-column justify-content-between h-100">
                            <div>
                                <span class="badge text-bg-light text-primary fw-semibold px-3 py-2">Bootstrap 5 Dashboard</span>
                            </div>
                            <div>
                                <h1 class="display-6 fw-bold mb-3">Sistem Sensus Hardware & Inventaris Elektronik</h1>
                                <p class="mb-0 text-white-50">Catat stok barang, mutasi masuk-keluar, histori lengkap, dan laporan tiap ruangan dalam satu dashboard yang ringkas.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 bg-white">
                        <div class="p-4 p-lg-5">
                            <div class="mb-4">
                                <!-- <p class="text-uppercase text-primary fw-bold small mb-2">Admin Login</p> -->
                                <h2 class="fw-bold mb-2">WELCOME SI INTEL</h2>
                                <!-- <p class="text-muted mb-0">Gunakan akun admin untuk mengelola data inventaris.</p> -->
                            </div>

                            <?php if ($errors): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0 ps-3">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= e($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if ($flash = get_flash()): ?>
                                <div class="alert alert-<?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
                            <?php endif; ?>

                            <form method="post" class="needs-validation" novalidate>
                                <?= csrf_field(); ?>
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control form-control-lg" value="<?= e($_POST['username'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control form-control-lg" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
                            </form>

                            <div class="small text-muted mt-4">
                                <!-- Default akun: -->
                                <!-- <span class="fw-semibold">admin / admin123</span> -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
