<?php
// Modul Form Tambah / Edit Produk Beserta Relasi Many-to-Many Supplier
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAuth();

$activePage = 'products';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$pageTitle = $isEdit ? 'Edit Produk' : 'Tambah Produk Baru';

$errors = [];
$product = [
    'sku' => '',
    'nama_produk' => '',
    'category_id' => '',
    'harga' => '',
    'stok' => '0',
    'minimum_stock' => '5'
];
$selectedSuppliers = [];

// Load data jika Edit
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        setFlash('error', 'Produk tidak ditemukan!');
        header('Location: products.php');
        exit;
    }
    $product = $existing;

    // Ambil supplier yang terhubung
    $supStmt = $pdo->prepare('SELECT supplier_id FROM product_suppliers WHERE product_id = :id');
    $supStmt->execute([':id' => $id]);
    $selectedSuppliers = $supStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle Form Submission POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = strtoupper(trim($_POST['sku'] ?? ''));
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $harga = (float)($_POST['harga'] ?? 0);
    $stok = (int)($_POST['stok'] ?? 0);
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 5);
    $suppliersPost = isset($_POST['suppliers']) && is_array($_POST['suppliers']) ? array_map('intval', $_POST['suppliers']) : [];

    // Validasi Sesuai Aturan Bisnis
    if (empty($sku)) {
        $errors[] = 'SKU (Kode Barang) wajib diisi.';
    }
    if (empty($nama_produk)) {
        $errors[] = 'Nama produk wajib diisi.';
    }
    if ($category_id <= 0) {
        $errors[] = 'Kategori produk wajib dipilih.';
    }
    if ($harga < 0) {
        $errors[] = 'Harga tidak boleh bernilai negatif.';
    }
    if ($stok < 0) {
        $errors[] = 'Stok tidak boleh bernilai negatif.';
    }
    if ($minimum_stock < 0) {
        $errors[] = 'Batas minimum stok tidak boleh negatif.';
    }

    // Cek Keunikan SKU
    $checkSkuSql = 'SELECT id FROM products WHERE sku = :sku' . ($isEdit ? ' AND id != :id' : '');
    $checkSkuStmt = $pdo->prepare($checkSkuSql);
    $checkSkuParams = [':sku' => $sku];
    if ($isEdit) {
        $checkSkuParams[':id'] = $id;
    }
    $checkSkuStmt->execute($checkSkuParams);
    if ($checkSkuStmt->fetch()) {
        $errors[] = "SKU '{$sku}' sudah digunakan oleh produk lain.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                // Update produk
                $updSql = 'UPDATE products SET category_id = :cat, sku = :sku, nama_produk = :nama, harga = :harga, stok = :stok, minimum_stock = :min_stok WHERE id = :id';
                $updStmt = $pdo->prepare($updSql);
                $updStmt->execute([
                    ':cat' => $category_id,
                    ':sku' => $sku,
                    ':nama' => $nama_produk,
                    ':harga' => $harga,
                    ':stok' => $stok,
                    ':min_stok' => $minimum_stock,
                    ':id' => $id
                ]);
                $productId = $id;
            } else {
                // Insert produk baru
                $insSql = 'INSERT INTO products (category_id, sku, nama_produk, harga, stok, minimum_stock) VALUES (:cat, :sku, :nama, :harga, :stok, :min_stok)';
                $insStmt = $pdo->prepare($insSql);
                $insStmt->execute([
                    ':cat' => $category_id,
                    ':sku' => $sku,
                    ':nama' => $nama_produk,
                    ':harga' => $harga,
                    ':stok' => $stok,
                    ':min_stok' => $minimum_stock
                ]);
                $productId = (int)$pdo->lastInsertId();
            }

            // Kelola Relasi Many-to-Many pada junction table: product_suppliers
            $delJunc = $pdo->prepare('DELETE FROM product_suppliers WHERE product_id = :id');
            $delJunc->execute([':id' => $productId]);

            if (!empty($suppliersPost)) {
                $insJunc = $pdo->prepare('INSERT INTO product_suppliers (product_id, supplier_id) VALUES (:pid, :sid)');
                foreach ($suppliersPost as $sid) {
                    $insJunc->execute([':pid' => $productId, ':sid' => $sid]);
                }
            }

            $pdo->commit();
            setFlash('success', $isEdit ? 'Produk berhasil diperbarui!' : 'Produk baru berhasil disimpan!');
            header('Location: products.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan data produk: ' . $e->getMessage();
        }
    }

    // Preserve input data saat validasi gagal
    $product['sku'] = $sku;
    $product['nama_produk'] = $nama_produk;
    $product['category_id'] = $category_id;
    $product['harga'] = $harga;
    $product['stok'] = $stok;
    $product['minimum_stock'] = $minimum_stock;
    $selectedSuppliers = $suppliersPost;
}

// Ambil Kategori & Supplier untuk Form
$categories = $pdo->query('SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC')->fetchAll();
$suppliers = $pdo->query('SELECT id, nama_supplier FROM suppliers ORDER BY nama_supplier ASC')->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold mb-1"><?= $isEdit ? 'Edit Data Produk' : 'Tambah Produk Baru' ?></h3>
                <p class="text-muted mb-0">Isi formulir data barang secara lengkap dan benar</p>
            </div>
            <a href="products.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <strong class="d-block mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Terjadi Kesalahan Validasi:</strong>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card card-custom shadow-sm mb-4">
            <div class="card-body p-4">
                <form action="product_form.php<?= $isEdit ? '?id=' . $id : '' ?>" method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="sku" class="form-label small fw-semibold">SKU / Kode Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sku" name="sku" 
                                   value="<?= e($product['sku']) ?>" required placeholder="Contoh: LAP-007">
                            <small class="text-muted">Harus unik untuk tiap barang.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="category_id" class="form-label small fw-semibold">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ((int)$product['category_id'] === $cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['nama_kategori']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="nama_produk" class="form-label small fw-semibold">Nama Produk Elektronik <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_produk" name="nama_produk" 
                               value="<?= e($product['nama_produk']) ?>" required placeholder="Contoh: Laptop Lenovo Legion 5 Pro">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="harga" class="form-label small fw-semibold">Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="1000" min="0" class="form-control" id="harga" name="harga" 
                                   value="<?= e((string)$product['harga']) ?>" required placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label for="stok" class="form-label small fw-semibold">Jumlah Stok Awal <span class="text-danger">*</span></label>
                            <input type="number" min="0" class="form-control" id="stok" name="stok" 
                                   value="<?= e((string)$product['stok']) ?>" required placeholder="0">
                        </div>
                        <div class="col-md-4">
                            <label for="minimum_stock" class="form-label small fw-semibold">Batas Stok Minimum <span class="text-danger">*</span></label>
                            <input type="number" min="0" class="form-control" id="minimum_stock" name="minimum_stock" 
                                   value="<?= e((string)$product['minimum_stock']) ?>" required placeholder="5">
                            <small class="text-muted">Standar peringatan stok rendah.</small>
                        </div>
                    </div>

                    <!-- Relasi Many-to-Many: Pilihan Supplier -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold d-block">Pemasok / Supplier Terkait (Many-to-Many):</label>
                        <div class="border rounded p-3 bg-light" style="max-height: 180px; overflow-y: auto;">
                            <?php if (empty($suppliers)): ?>
                                <small class="text-muted">Belum ada supplier yang terdaftar.</small>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach ($suppliers as $sup): ?>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="suppliers[]" 
                                                       value="<?= $sup['id'] ?>" id="sup_<?= $sup['id'] ?>"
                                                       <?= in_array($sup['id'], $selectedSuppliers) ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="sup_<?= $sup['id'] ?>">
                                                    <?= e($sup['nama_supplier']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">Satu produk dapat disuplai oleh beberapa vendor sekaligus.</small>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="products.php" class="btn btn-outline-secondary px-3">Batal</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="bi bi-save me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Produk' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../views/layouts/footer.php';
?>
