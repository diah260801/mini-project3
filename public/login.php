<?php
// Halaman Login Pengguna
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        // Prepared statement PDO
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // Verifikasi password hash
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            setFlash('success', 'Selamat datang kembali, ' . $user['nama_lengkap'] . '!');
            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Toko Elektronik Maju Jaya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }
        .login-header {
            background: #2563eb;
            color: #fff;
            padding: 2rem 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="login-card mx-auto">
        <div class="login-header">
            <h4 class="fw-bold mb-1"><i class="bi bi-cpu-fill text-warning me-2"></i>Maju Jaya</h4>
            <p class="mb-0 small text-light opacity-75">Sistem Manajemen Inventaris Barang</p>
        </div>
        <div class="p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php
            $flashErr = getFlash('error');
            if ($flashErr): ?>
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= e($flashErr) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" required autofocus placeholder="Masukkan username">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
                </button>
            </form>

            <div class="mt-4 pt-3 border-top text-center">
                <small class="text-muted d-block fw-semibold mb-1">Akun Percobaan (Demo):</small>
                <div class="d-flex justify-content-center gap-2">
                    <span class="badge bg-light text-dark border">Admin: <code>admin / Admin#12345</code></span>
                    <span class="badge bg-light text-dark border">Staff: <code>staff / Staff#12345</code></span>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
