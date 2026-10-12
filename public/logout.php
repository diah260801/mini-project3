<?php
// Halaman Logout
require_once __DIR__ . '/../helpers/functions.php';

// Hapus seluruh session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
setFlash('success', 'Anda telah berhasil keluar dari sistem.');
header('Location: login.php');
exit;
