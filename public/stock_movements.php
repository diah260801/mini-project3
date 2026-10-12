<?php
// Modul Transaksi Stok Barang (Masuk / Keluar) dengan FOR UPDATE dan Riwayat Mutasi
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAuth();

$activePage = 'stock';
$pageTitle = 'Transaksi Stok Barang';
$action = $_GET['action'] ?? 'index';

// Ambil User yang aktif
$currentUser = currentUser();
$errors = [];

// Proses Formulir Transaksi Baru (Barang Masuk / Keluar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $tipe = $_POST['tipe'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    // 1. Validasi Input Dasar
    if ($product_id <= 0) {
        $errors[] = 'Silakan pilih produk yang akan dimutasi stoknya.';
    }
    if (!in_array($tipe, ['in', 'out'], true)) {
        $errors[] = 'Tipe transaksi tidak valid (harus Masuk atau Keluar).';
    }
    if ($quantity <= 0) {
        $errors[] = 'Jumlah kuantitas (quantity) harus lebih besar dari 0.';
    }

    if (empty($errors)) {
        try {
            // Memulai Transaksi Database PDO
            $pdo->beginTransaction();

            // 2. Lock baris produk dengan SELECT ... FOR UPDATE untuk mencegah race condition
            $lockStmt = $pdo->prepare('SELECT id, nama_produk, stok FROM products WHERE id = :id FOR UPDATE');
            $lockStmt->execute([':id' => $product_id]);
            $prod = $lockStmt->fetch();

            if (!$prod) {
                throw new Exception('Produk tidak ditemukan di database.');
            }

            $currentStock = (int)$prod['stok'];

            // 3. Validasi Aturan Bisnis Stok: Barang keluar tidak boleh melebihi stok yang tersedia
            if ($tipe === 'out') {
                if ($quantity > $currentStock) {
                    throw new Exception("Stok tidak mencukupi! Stok saat ini untuk {$prod['nama_produk']} adalah {$currentStock}, permintaan keluar: {$quantity}.");
                }
                $newStock = $currentStock - $quantity;
            } else {
                $newStock = $currentStock + $quantity;
            }

            // 4. Update Stok Produk
            $updStmt = $pdo->prepare('UPDATE products SET stok = :new_stock WHERE id = :id');
            $updStmt->execute([
                ':new_stock' => $newStock,
                ':id'        => $product_id
            ]);

            // 5. Catat Histori Mutasi ke stock_movements
            $insStmt = $pdo->prepare('
                INSERT INTO stock_movements (product_id, user_id, tipe, quantity, keterangan)
                VALUES (:pid, :uid, :tipe, :qty, :ket)
            ');
            $insStmt->execute([
                ':pid'  => $product_id,
                ':uid'  => $currentUser['id'],
                ':tipe' => $tipe,
                ':qty'  => $quantity,
                ':ket'  => $keterangan
            ]);

            // Commit Transaksi
            $pdo->commit();

            setFlash('success', "Transaksi stok berhasil! Stok {$prod['nama_produk']} kini menjadi {$newStock} unit.");
            header('Location: stock_movements.php');
            exit;

        } catch (Exception $e) {
            // Rollback jika terjadi kegagalan atau validasi stok tidak mencukupi
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

// Parameter Pencarian / Filter Riwayat Transaksi
$filterType = $_GET['filter_type'] ?? '';
$filterProduct = isset($_GET['product_id']) && $_GET['product_id'] !== '' ? (int)$_GET['product_id'] : 0;

$whereClauses = [];
$params = [];

if (in_array($filterType, ['in', 'out'], true)) {
    $whereClauses[] = 'sm.tipe = :tipe';
    $params[':tipe'] = $filterType;
}

if ($filterProduct > 0) {
    $whereClauses[] = 'sm.product_id = :pid';
    $params[':pid'] = $filterProduct;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Pagination Riwayat Mutasi
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Hitung total data
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm {$whereSql}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Query History Mutasi Stok
$querySql = "
    SELECT 
        sm.*,
        p.nama_produk,
        p.sku,
        p.stok AS stok_saat_ini,
        u.nama_lengkap,
        u.username
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    {$whereSql}
    ORDER BY sm.created_at DESC
    LIMIT :offset, :limit
";

$stmt = $pdo->prepare($querySql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$movements = $stmt->fetchAll();

// Ambil semua daftar produk untuk opsi formulir
$allProducts = $pdo->query('SELECT id, nama_produk, sku, stok FROM products ORDER BY nama_produk ASC')->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Transaksi Mutasi Stok</h3>
        <p class="text-muted mb-0">Kelola pengadaan barang masuk (Restock) dan pengeluaran barang (Penjualan/Retur)</p>
    </div>
    <?php if ($action !== 'create'): ?>
        <div>
            <a href="stock_movements.php?action=create" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Catat Transaksi Baru
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($action === 'create'): ?>
    <!-- Form Tambah Transaksi Mutasi Baru -->
    <div class="row justify-content-center mb-4">
        <div class="col-lg-8">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm">
                    <strong class="d-block mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Transaksi Gagal:</strong>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card card-custom shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">Formulir Mutasi Stok (In / Out)</h6>
                    <a href="stock_movements.php" class="btn btn-outline-secondary btn-sm">Batal / Kembali</a>
                </div>
                <div class="card-body p-4">
                    <form action="stock_movements.php?action=create" method="POST">
                        <div class="mb-3">
                            <label for="product_id" class="form-label small fw-semibold">Pilih Produk <span class="text-danger">*</span></label>
                            <select class="form-select" id="product_id" name="product_id" required>
                                <option value="">-- Pilih Produk --</option>
                                <?php 
                                $selectedProdId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);
                                foreach ($allProducts as $ap): 
                                ?>
                                    <option value="<?= $ap['id'] ?>" <?= ($selectedProdId === $ap['id']) ? 'selected' : '' ?>>
                                        <?= e($ap['nama_produk']) ?> (SKU: <?= e($ap['sku']) ?>) — Sisa Stok: <?= $ap['stok'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold d-block">Tipe Mutasi <span class="text-danger">*</span></label>
                                <?php $selectedType = $_POST['tipe'] ?? $_GET['type'] ?? 'in'; ?>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="tipe" id="tipe_in" value="in" <?= $selectedType === 'in' ? 'checked' : '' ?> required>
                                    <label class="btn btn-outline-success" for="tipe_in">
                                        <i class="bi bi-box-arrow-in-down me-1"></i> Barang Masuk (IN)
                                    </label>

                                    <input type="radio" class="btn-check" name="tipe" id="tipe_out" value="out" <?= $selectedType === 'out' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-danger" for="tipe_out">
                                        <i class="bi bi-box-arrow-up me-1"></i> Barang Keluar (OUT)
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="quantity" class="form-label small fw-semibold">Jumlah Kuantitas (Qty) <span class="text-danger">*</span></label>
                                <input type="number" min="1" class="form-control" id="quantity" name="quantity" 
                                       value="<?= e((string)($_POST['quantity'] ?? '1')) ?>" required placeholder="Kuantitas">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="keterangan" class="form-label small fw-semibold">Keterangan / Nomor Referensi Dokumen</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Contoh: Pembelian dari Supplier Maju Tech Faktur #PO-881 atau Penjualan Faktur #INV-204"><?= e($_POST['keterangan'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="stock_movements.php" class="btn btn-outline-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                <i class="bi bi-check-circle me-1"></i> Simpan Transaksi Stok
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Filter Riwayat Mutasi -->
<div class="card card-custom shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="stock_movements.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="product_id" class="form-select">
                    <option value="">-- Semua Produk --</option>
                    <?php foreach ($allProducts as $ap): ?>
                        <option value="<?= $ap['id'] ?>" <?= ($filterProduct === $ap['id']) ? 'selected' : '' ?>>
                            <?= e($ap['nama_produk']) ?> (<?= e($ap['sku']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="filter_type" class="form-select">
                    <option value="">-- Semua Tipe Transaksi --</option>
                    <option value="in" <?= $filterType === 'in' ? 'selected' : '' ?>>Barang Masuk (IN)</option>
                    <option value="out" <?= $filterType === 'out' ? 'selected' : '' ?>>Barang Keluar (OUT)</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-dark w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="stock_movements.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Riwayat Transaksi Mutasi Stok -->
<div class="card card-custom shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold">Riwayat Log Mutasi Stok (Audit Trail)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 140px;">Waktu & Tanggal</th>
                        <th style="width: 100px;">Tipe</th>
                        <th>Produk</th>
                        <th class="text-center" style="width: 90px;">Jumlah</th>
                        <th>Keterangan</th>
                        <th>Petugas (User)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">Belum ada catatan mutasi stok yang tersimpan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td class="small text-muted">
                                    <?= date('d/m/Y H:i:s', strtotime($m['created_at'])) ?>
                                </td>
                                <td>
                                    <?php if ($m['tipe'] === 'in'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-box-arrow-in-down me-1"></i> MASUK
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-box-arrow-up me-1"></i> KELUAR
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark d-block"><?= e($m['nama_produk']) ?></span>
                                    <code class="small text-muted"><?= e($m['sku']) ?></code>
                                </td>
                                <td class="text-center fw-bold fs-6 <?= $m['tipe'] === 'in' ? 'text-success' : 'text-danger' ?>">
                                    <?= $m['tipe'] === 'in' ? '+' : '-' ?><?= $m['quantity'] ?>
                                </td>
                                <td class="small text-secondary">
                                    <?= e($m['keterangan'] ?? '-') ?>
                                </td>
                                <td class="small">
                                    <span class="fw-semibold"><?= e($m['nama_lengkap']) ?></span>
                                    <small class="text-muted d-block">@<?= e($m['username']) ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
            <small class="text-muted">Halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= $totalRows ?> transaksi)</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php $qParams = $_GET; ?>
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <?php $qParams['page'] = $page - 1; ?>
                        <a class="page-link" href="stock_movements.php?<?= http_build_query($qParams) ?>">Sebelumnya</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $qParams['page'] = $i; ?>
                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                            <a class="page-link" href="stock_movements.php?<?= http_build_query($qParams) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <?php $qParams['page'] = $page + 1; ?>
                        <a class="page-link" href="stock_movements.php?<?= http_build_query($qParams) ?>">Selanjutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../views/layouts/footer.php';
?>
