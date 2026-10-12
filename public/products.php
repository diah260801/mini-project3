<?php
// Modul Produk: List, Filter, Search, Sort Allowlist, Pagination & Delete (RESTRICT)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAuth();

$activePage = 'products';
$pageTitle = 'Manajemen Produk';

$action = $_GET['action'] ?? 'index';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle DELETE dengan Aturan Bisnis RESTRICT
if ($action === 'delete' && $id > 0) {
    try {
        // Cek histori transaksi stock_movements
        $checkHistory = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE product_id = :id');
        $checkHistory->execute([':id' => $id]);
        $historyCount = $checkHistory->fetchColumn();

        if ($historyCount > 0) {
            setFlash('error', 'RESTRICT: Produk ini tidak dapat dihapus karena memiliki riwayat transaksi mutasi stok (' . $historyCount . ' catatan).');
        } else {
            // Hapus relasi junction supplier dahulu kemudian produk
            $delStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $delStmt->execute([':id' => $id]);
            setFlash('success', 'Produk berhasil dihapus dari inventaris.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Gagal menghapus produk: ' . $e->getMessage());
    }
    header('Location: products.php');
    exit;
}

// Parameter Filter, Search, Sort & Pagination
$search = trim($_GET['search'] ?? '');
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
$stockFilter = $_GET['stock_filter'] ?? '';

// Sorting Allowlist Keamanan
$allowedSort = [
    'nama_produk'   => 'p.nama_produk',
    'sku'           => 'p.sku',
    'harga'         => 'p.harga',
    'stok'          => 'p.stok',
    'kategori'      => 'c.nama_kategori',
    'created_at'    => 'p.created_at'
];
$sort = $_GET['sort'] ?? 'nama_produk';
$order = strtoupper($_GET['order'] ?? 'ASC');

if (!array_key_exists($sort, $allowedSort)) {
    $sort = 'nama_produk';
}
if (!in_array($order, ['ASC', 'DESC'], true)) {
    $order = 'ASC';
}
$orderByColumn = $allowedSort[$sort];

// Pagination Setup
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Bangun Klausa WHERE Dinamis Menggunakan Prepared Statement
$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(p.nama_produk LIKE :search OR p.sku LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($categoryId !== null && $categoryId > 0) {
    $whereClauses[] = 'p.category_id = :category_id';
    $params[':category_id'] = $categoryId;
}

if ($stockFilter === 'low') {
    $whereClauses[] = 'p.stok <= p.minimum_stock';
} elseif ($stockFilter === 'out') {
    $whereClauses[] = 'p.stok = 0';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// 1. Hitung Total Baris
$countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id = c.id {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// 2. Query Data Produk dengan Junction Suppliers (GROUP_CONCAT)
$dataSql = "
    SELECT 
        p.*, 
        c.nama_kategori,
        GROUP_CONCAT(s.nama_supplier SEPARATOR ', ') AS suppliers_list
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_suppliers ps ON p.id = ps.product_id
    LEFT JOIN suppliers s ON ps.supplier_id = s.id
    {$whereSql}
    GROUP BY p.id
    ORDER BY {$orderByColumn} {$order}
    LIMIT :offset, :limit
";

$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Ambil Kategori untuk dropdown filter
$allCategories = $pdo->query('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC')->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Katalog & Stok Produk</h3>
        <p class="text-muted mb-0">Total <?= number_format($totalRows) ?> produk terdaftar</p>
    </div>
    <div class="d-flex gap-2">
        <a href="product_form.php" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Tambah Produk Baru
        </a>
    </div>
</div>

<!-- Card Filter, Pencarian dan Pengurutan -->
<div class="card card-custom shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="products.php" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari SKU atau nama..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="category_id" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($categoryId === $cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="stock_filter" class="form-select">
                    <option value="">-- Status Stok --</option>
                    <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Stok Rendah (&le; Min)</option>
                    <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Stok Habis (0)</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="sort" class="form-select">
                    <option value="nama_produk" <?= $sort === 'nama_produk' ? 'selected' : '' ?>>Urut: Nama</option>
                    <option value="sku" <?= $sort === 'sku' ? 'selected' : '' ?>>Urut: SKU</option>
                    <option value="harga" <?= $sort === 'harga' ? 'selected' : '' ?>>Urut: Harga</option>
                    <option value="stok" <?= $sort === 'stok' ? 'selected' : '' ?>>Urut: Stok</option>
                    <option value="kategori" <?= $sort === 'kategori' ? 'selected' : '' ?>>Urut: Kategori</option>
                </select>
            </div>

            <div class="col-md-1">
                <select name="order" class="form-select">
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>A-Z / Min</option>
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Z-A / Max</option>
                </select>
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-dark w-100" title="Terapkan Filter"><i class="bi bi-funnel"></i></button>
                <a href="products.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar Produk -->
<div class="card card-custom shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>SKU</th>
                        <th>Nama Produk</th>
                        <th>Kategori</th>
                        <th>Supplier Terkait (M:M)</th>
                        <th class="text-end">Harga</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Min. Stok</th>
                        <th class="text-end" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Tidak ditemukan data produk yang sesuai kriteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><code class="fw-bold"><?= e($p['sku']) ?></code></td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= e($p['nama_produk']) ?></span>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($p['nama_kategori']) ?></span></td>
                                <td>
                                    <small class="text-muted">
                                        <?= e($p['suppliers_list'] ?: 'Belum diasosiasikan') ?>
                                    </small>
                                </td>
                                <td class="text-end fw-semibold text-secondary">
                                    <?= formatRupiah($p['harga']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['stok'] <= 0): ?>
                                        <span class="badge bg-dark">Habis (0)</span>
                                    <?php elseif ($p['stok'] <= $p['minimum_stock']): ?>
                                        <span class="badge bg-danger" title="Di bawah minimum"><?= $p['stok'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= $p['stok'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-muted"><?= $p['minimum_stock'] ?></td>
                                <td class="text-end">
                                    <a href="product_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="stock_movements.php?action=create&product_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-success py-0 px-2" title="Mutasi Stok">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </a>
                                    <a href="products.php?action=delete&id=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger py-0 px-2" 
                                       title="Hapus"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
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
            <small class="text-muted">
                Menampilkan halaman <?= $page ?> dari <?= $totalPages ?> (Total <?= $totalRows ?> produk)
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    // Helper parameter query string pagination
                    $queryParams = $_GET;
                    ?>
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <?php $queryParams['page'] = $page - 1; ?>
                        <a class="page-link" href="products.php?<?= http_build_query($queryParams) ?>">Sebelumnya</a>
                    </li>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $queryParams['page'] = $i; ?>
                        <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                            <a class="page-link" href="products.php?<?= http_build_query($queryParams) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <?php $queryParams['page'] = $page + 1; ?>
                        <a class="page-link" href="products.php?<?= http_build_query($queryParams) ?>">Selanjutnya</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../views/layouts/footer.php';
?>
