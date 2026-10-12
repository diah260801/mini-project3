<?php
// Helper Functions: Security, Flash Messages, Sanitization & Authorization

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sanitasi output HTML
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Format Rupiah
function formatRupiah(float|int|string $angka): string {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

// Cek apakah user sudah login
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

// Ambil user yang sedang login
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nama_lengkap' => $_SESSION['nama_lengkap'],
        'role' => $_SESSION['role']
    ];
}

// Middleware: Wajib Login
function requireAuth(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk mengakses sistem.';
        header('Location: login.php');
        exit;
    }
}

// Middleware: Wajib Role Tertentu (misal Admin)
function requireRole(string|array $roles): void {
    requireAuth();
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../views/errors/403.php';
        exit;
    }
}

// Set Flash Message
function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

// Ambil dan bersihkan Flash Message
function getFlash(string $type): ?string {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}
