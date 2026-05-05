<?php

declare(strict_types=1);

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function current_user_role(): string
{
    return $_SESSION['user']['role'] ?? 'admin';
}

function is_admin_user(): bool
{
    return current_user_role() === 'admin';
}

function user_home_path(): string
{
    return is_admin_user() ? 'modules/dashboard/index.php' : 'modules/pengembangan/index.php';
}

function user_can_access_current_module(): bool
{
    if (!is_logged_in() || is_admin_user()) {
        return true;
    }

    return is_active_menu('/modules/pengembangan/');
}

function require_module_access(): void
{
    if (user_can_access_current_module()) {
        return;
    }

    set_flash('warning', 'Akun Anda hanya memiliki akses ke menu Laporan Pengembangan.');
    redirect(user_home_path());
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect(user_home_path());
    }
}

function attempt_login(PDO $pdo, string $username, string $password): bool
{
    $user = fetch_one($pdo, 'SELECT * FROM users WHERE username = :username LIMIT 1', [
        'username' => $username,
    ]);

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id();
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'nama' => $user['nama'],
        'role' => $user['role'] ?? 'admin',
    ];

    return true;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id();
}
