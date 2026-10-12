<?php
// Dashboard Utama Inventaris
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAuth();

$activePage = 'dashboard';
$pageTitle = 'Dashboard Inventaris';

// Query Metrik Dashboard
// 1. Total Produk & Total Kuantitas Stok & Total Nilai Inventaris (harga * stok)
$metricStmt = $pdo->query('
    SELECT 
        COUNT(id) AS total_produk,
        COALESCE(SUM(stok), 0) AS total_kuantitas_stok,
        COALESCE(SUM(harga * stok), 0) AS total_nilai_inventory
    FROM products
');
$metrics = $metricStmt->fetch();

// 2. Jumlah Produk Stok Rendah (stok <= minimum_stock)
$lowStockCountStmt = $pdo->query('
    SELECT COUNT(id) AS total_low_stock 
    FROM products 
    WHERE stok <= minimum_stock
');
$lowStockCount = $lowStockCountStmt->fetchColumn();

// 3. Total Barang Masuk & Barang Keluar dari history
$movementStmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN tipe = 'in' THEN quantity ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN tipe = 'out' THEN quantity ELSE 0 END), 0) AS total_keluar
    FROM stock_movements
");
$movements = $movementStmt->fetch();

// 4. Daftar 5 Produk dengan Stok Rendah
$lowStockProductsStmt = $pdo->query('
    SELECT p.*, c.nama_kategori 
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stok <= p.minimum_stock
    ORDER BY p.stok ASC
    LIMIT 5
');
$lowStockProducts = $lowStockProductsStmt->fetchAll();

// 5. 5 Aktivitas Transaksi Stok Terakhir
$recentActivitiesStmt = $pdo->query('
    SELECT sm.*, p.nama_produk, p.sku, u.nama_lengkap
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    ORDER BY sm.created_at DESC
    LIMIT 5
');
$recentActivities = $recentActivitiesStmt->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Dashboard Inventaris</h3>
        <p class="text-muted mb-0">Ringkasan stok barang Toko Elektronik Maju Jaya</p>
    </div>
    <div>
        <a href="stock_movements.php?action=create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Transaksi Stok Baru
        </a>
    </div>
</div>

<!-- Kartu Metrik Utama -->
<div class="row g-3 mb-4">
    <!-- Total Produk -->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom border-start border-primary border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Produk</div>
                        <h3 class="fw-bold text-dark mt-2 mb-0"><?= number_format($metrics['total_produk']) ?></h3>
                        <small class="text-secondary"><?= number_format($metrics['total_kuantitas_stok']) ?> unit fisik</small>
                    </div>
                    <div class="bg-primary-subtle p-3 rounded-circle text-primary">
                        <i class="bi bi-box-seam fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Nilai Inventaris -->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom border-start border-success border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Nilai Inventaris</div>
                        <h4 class="fw-bold text-success mt-2 mb-0"><?= formatRupiah($metrics['total_nilai_inventory']) ?></h4>
                        <small class="text-secondary">Akumulasi stok fisik</small>
                    </div>
                    <div class="bg-success-subtle p-3 rounded-circle text-success">
                        <i class="bi bi-cash-stack fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stok Rendah -->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom border-start border-danger border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Stok Kritis / Rendah</div>
                        <h3 class="fw-bold text-danger mt-2 mb-0"><?= number_format($lowStockCount) ?></h3>
                        <small class="text-danger">&le; Batas minimum stok</small>
                    </div>
                    <div class="bg-danger-subtle p-3 rounded-circle text-danger">
                        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Arus Barang Masuk & Keluar -->
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom border-start border-info border-4 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pergerakan Stok</div>
                        <div class="mt-2">
                            <span class="badge bg-success me-1"><i class="bi bi-arrow-down-left me-1"></i> Masuk: <?= number_format($movements['total_masuk']) ?></span>
                        </div>
                        <div class="mt-1">
                            <span class="badge bg-danger"><i class="bi bi-arrow-up-right me-1"></i> Keluar: <?= number_format($movements['total_keluar']) ?></span>
                        </div>
                    </div>
                    <div class="bg-info-subtle p-3 rounded-circle text-info">
                        <i class="bi bi-arrow-left-right fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Tabel Stok Rendah -->
    <div class="col-lg-6">
        <div class="card card-custom shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-danger">
                    <i class="bi bi-bell-fill me-1"></i> Peringatan Stok Rendah (Perlu Restock)
                </h6>
                <a href="products.php" class="btn btn-outline-secondary btn-sm">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th class="text-center">Sisa Stok</th>
                                <th class="text-center">Min. Stok</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lowStockProducts)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Semua stok produk dalam kondisi aman.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lowStockProducts as $lp): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold d-block"><?= e($lp['nama_produk']) ?></span>
                                            <code class="small text-muted"><?= e($lp['sku']) ?></code>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($lp['nama_kategori']) ?></span></td>
                                        <td class="text-center">
                                            <span class="badge bg-danger fs-6"><?= $lp['stok'] ?></span>
                                        </td>
                                        <td class="text-center text-muted"><?= $lp['minimum_stock'] ?></td>
                                        <td class="text-end">
                                            <a href="stock_movements.php?action=create&product_id=<?= $lp['id'] ?>&type=in" class="btn btn-sm btn-success py-0 px-2" title="Restock Masuk">
                                                <i class="bi bi-plus-lg"></i> In
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Aktivitas Transaksi Terbaru -->
    <div class="col-lg-6">
        <div class="card card-custom shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-clock-history me-1"></i> Aktivitas Mutasi Stok Terakhir
                </h6>
                <a href="stock_movements.php" class="btn btn-outline-secondary btn-sm">Lihat Riwayat</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Waktu</th>
                                <th>Tipe</th>
                                <th>Produk</th>
                                <th class="text-center">Qty</th>
                                <th>Operator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada transaksi mutasi stok.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $act): ?>
                                    <tr>
                                        <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></td>
                                        <td>
                                            <?php if ($act['tipe'] === 'in'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">MASUK</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">KELUAR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold small d-block text-truncate" style="max-width: 180px;"><?= e($act['nama_produk']) ?></span>
                                            <code class="small text-muted"><?= e($act['sku']) ?></code>
                                        </td>
                                        <td class="text-center fw-bold <?= $act['tipe'] === 'in' ? 'text-success' : 'text-danger' ?>">
                                            <?= $act['tipe'] === 'in' ? '+' : '-' ?><?= $act['quantity'] ?>
                                        </td>
                                        <td class="small"><?= e($act['nama_lengkap']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../views/layouts/footer.php';
?>
