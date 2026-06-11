<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']);
}

function requireLogin(string $redirectTo = '/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

function requireRole(string $role, string $redirectTo = '/login.php'): void {
    requireLogin($redirectTo);
    $user = getCurrentUser();
    if ($user['role'] !== $role) {
        http_response_code(403);
        die('Accès refusé.');
    }
}
