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
        redirect(user_home_path());
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
    <link rel="icon" type="image/png" href="<?= e(url(APP_FAVICON_PATH)); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-scene">
        <div class="container-fluid px-3 px-lg-4">
            <div class="login-grid">
                <section class="login-brand-stage">
                    <div class="login-brand-card">
                        <img src="<?= e(url(APP_LOGO_PATH)); ?>" alt="Logo SiAEGIS" class="img-fluid login-brand-logo">
                        <div class="login-brand-tagline">ASSET &bull; GOVERNANCE &bull; INFRASTRUCTURE &bull; SERVER</div>
                    </div>
                </section>

                <section class="login-auth-stage">
                    <div class="login-auth-card">
                        <div class="login-auth-head">
                            <div class="login-form-logo-wrap d-lg-none text-center mb-4">
                                <img src="<?= e(url(APP_LOGO_PATH)); ?>" alt="Logo SiAEGIS" class="img-fluid login-form-logo">
                            </div>
                            <span class="login-auth-kicker">WELCOME BACK</span>
                            <h1 class="login-auth-title">Silakan Masuk Untuk Melanjutkan</h1>
                        </div>

                        <?php if ($errors): ?>
                            <div class="alert alert-danger login-auth-alert">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($flash = get_flash()): ?>
                            <div class="alert alert-<?= e($flash['type']); ?> login-auth-alert"><?= e($flash['message']); ?></div>
                        <?php endif; ?>

                        <form method="post" class="login-auth-form" novalidate>
                            <?= csrf_field(); ?>
                            <div class="login-input-shell">
                                <label for="loginUsername" class="visually-hidden">Username</label>
                                <span class="login-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M12 12.75a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 1.5c-4.2 0-7.75 2.31-7.75 5.25 0 .41.34.75.75.75h14a.75.75 0 0 0 .75-.75c0-2.94-3.55-5.25-7.75-5.25Z" fill="currentColor"/></svg>
                                </span>
                                <input id="loginUsername" type="text" name="username" class="form-control login-auth-input" value="<?= e($_POST['username'] ?? ''); ?>" placeholder="Username" autocomplete="username" required>
                            </div>

                            <div class="login-input-shell">
                                <label for="loginPassword" class="visually-hidden">Password</label>
                                <span class="login-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><path d="M7.75 9V7.25a4.25 4.25 0 1 1 8.5 0V9h.5A2.25 2.25 0 0 1 19 11.25v7.5A2.25 2.25 0 0 1 16.75 21h-9.5A2.25 2.25 0 0 1 5 18.75v-7.5A2.25 2.25 0 0 1 7.25 9h.5Zm1.5 0h5.5V7.25a2.75 2.75 0 1 0-5.5 0V9Zm2.75 3a.75.75 0 0 1 .75.75v2.69l.22.15a1.75 1.75 0 1 1-2.44 0l.22-.15v-2.69A.75.75 0 0 1 12 12Z" fill="currentColor"/></svg>
                                </span>
                                <input id="loginPassword" type="password" name="password" class="form-control login-auth-input" placeholder="Password" autocomplete="current-password" required>
                                <button type="button" class="login-password-toggle" data-login-password-toggle aria-label="Tampilkan atau sembunyikan password">
                                    <svg class="login-eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.25c4.68 0 8.38 3.13 9.86 6.15a1.34 1.34 0 0 1 0 1.2c-1.48 3.02-5.18 6.15-9.86 6.15S3.62 15.62 2.14 12.6a1.34 1.34 0 0 1 0-1.2C3.62 8.38 7.32 5.25 12 5.25Zm0 2c-3.67 0-6.71 2.4-8.11 4.75 1.4 2.35 4.44 4.75 8.11 4.75s6.71-2.4 8.11-4.75C18.71 9.65 15.67 7.25 12 7.25Zm0 1.75a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z" fill="currentColor"/></svg>
                                    <svg class="login-eye-closed d-none" viewBox="0 0 24 24" aria-hidden="true"><path d="m3.53 2.47 18 18a.75.75 0 1 1-1.06 1.06l-2.39-2.39A10.9 10.9 0 0 1 12 18.75c-4.68 0-8.38-3.13-9.86-6.15a1.34 1.34 0 0 1 0-1.2A12.6 12.6 0 0 1 6.1 6.93L2.47 3.53a.75.75 0 1 1 1.06-1.06Zm4.8 5.86A4.47 4.47 0 0 0 7.5 11c0 2.49 2.01 4.5 4.5 4.5.94 0 1.82-.29 2.55-.78l-1.1-1.1a2.99 2.99 0 0 1-4.12-4.12l-1-1Zm7.36 7.35-1.13-1.13a4.48 4.48 0 0 0 1.94-3.68c0-2.49-2.01-4.5-4.5-4.5-.98 0-1.9.31-2.65.84L8.2 6.06c1.15-.51 2.42-.81 3.8-.81 4.68 0 8.38 3.13 9.86 6.15.2.4.2.8 0 1.2a12.62 12.62 0 0 1-6.17 5.08Z" fill="currentColor"/></svg>
                                </button>
                            </div>

                            <button type="submit" class="btn login-submit-btn w-100">LOGIN</button>

                            <!-- <div class="login-divider">
                                <span>atau</span>
                            </div> -->

                            <!-- <button type="button" class="btn login-sso-btn w-100" aria-label="Masuk dengan SSO"> -->
                                <!-- <span class="login-sso-icon" aria-hidden="true"> -->
                                    <!-- <svg viewBox="0 0 24 24"><path d="M12 3.75 5.25 6v5.08c0 4.06 2.87 7.87 6.75 9.17 3.88-1.3 6.75-5.11 6.75-9.17V6L12 3.75Zm0 2.08 5.25 1.75v3.5c0 3.25-2.19 6.33-5.25 7.48-3.06-1.15-5.25-4.23-5.25-7.48v-3.5L12 5.83Zm-.75 2.92v2.5h-2.5a.75.75 0 0 0 0 1.5h2.5v2.5a.75.75 0 0 0 1.5 0v-2.5h2.5a.75.75 0 0 0 0-1.5h-2.5v-2.5a.75.75 0 0 0-1.5 0Z" fill="currentColor"/></svg> -->
                                <!-- </span> -->
                                <!-- Masuk dengan SSO -->
                            <!-- </button> -->
                        </form>
                    </div>
                </section>
            </div>

            <div class="login-footer-note">&copy; <?= e(date('Y')); ?> SiAEGIS. All rights reserved.</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.querySelector('[data-login-password-toggle]');
            const passwordInput = document.getElementById('loginPassword');

            if (!toggle || !passwordInput) {
                return;
            }

            const openIcon = toggle.querySelector('.login-eye-open');
            const closedIcon = toggle.querySelector('.login-eye-closed');

            toggle.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

                if (openIcon) {
                    openIcon.classList.toggle('d-none', isPassword);
                }

                if (closedIcon) {
                    closedIcon.classList.toggle('d-none', !isPassword);
                }
            });
        });
    </script>
</body>
</html>
