<?php

declare(strict_types=1);

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
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
        redirect('modules/dashboard/index.php');
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
