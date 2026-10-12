<?php
// Layout Header & Navigasi Utama
require_once __DIR__ . '/../../helpers/functions.php';
$activePage = $activePage ?? '';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>Toko Elektronik Maju Jaya</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .main-content {
            flex: 1;
        }
        .badge-role {
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .card-custom {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand text-primary fw-bold" href="index.php">
            <i class="bi bi-cpu-fill text-warning me-2"></i>Maju Jaya <span class="text-white fs-6 fw-normal">| Inventory</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navMain">
            <?php if (isLoggedIn()): ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'dashboard' ? 'active fw-semibold' : '' ?>" href="index.php">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'products' ? 'active fw-semibold' : '' ?>" href="products.php">
                        <i class="bi bi-box-seam me-1"></i> Produk
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'categories' ? 'active fw-semibold' : '' ?>" href="categories.php">
                        <i class="bi bi-tags me-1"></i> Kategori
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'suppliers' ? 'active fw-semibold' : '' ?>" href="suppliers.php">
                        <i class="bi bi-truck me-1"></i> Supplier
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'stock' ? 'active fw-semibold' : '' ?>" href="stock_movements.php">
                        <i class="bi bi-arrow-left-right me-1"></i> Transaksi Stok
                    </a>
                </li>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'users' ? 'active fw-semibold' : '' ?>" href="users.php">
                        <i class="bi bi-people me-1"></i> Manajemen User
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item me-3 text-light">
                    <small class="text-muted d-block d-lg-inline">Login sebagai:</small>
                    <span class="fw-semibold text-white ms-1"><?= e($user['nama_lengkap']) ?></span>
                    <span class="badge <?= $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?> badge-role ms-1">
                        <?= e($user['role']) ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </li>
            </ul>
            <?php else: ?>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-white" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="main-content container-fluid px-4 py-4">
    <?php
    $successMsg = getFlash('success');
    $errorMsg = getFlash('error');
    ?>
    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($successMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($errorMsg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
