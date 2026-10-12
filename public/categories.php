<?php
// Modul CRUD Kategori Barang
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAuth();

$activePage = 'categories';
$pageTitle = 'Kategori Produk';

$action = $_GET['action'] ?? 'index';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$category = null;

// Handle DELETE
if ($action === 'delete' && $id > 0) {
    try {
        // Cek keterikatan produk (RESTRICT)
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
        $checkStmt->execute([':id' => $id]);
        if ($checkStmt->fetchColumn() > 0) {
            setFlash('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh produk terdaftar (RESTRICT).');
        } else {
            $delStmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
            $delStmt->execute([':id' => $id]);
            setFlash('success', 'Kategori berhasil dihapus.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Gagal menghapus kategori: ' . $e->getMessage());
    }
    header('Location: categories.php');
    exit;
}

// Handle Form POST (Create / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    // Validasi
    if (empty($nama_kategori)) {
        $error = 'Nama kategori wajib diisi!';
    } else {
        try {
            if ($action === 'edit' && $id > 0) {
                // Cek keunikan nama kategori kecuali diri sendiri
                $dup = $pdo->prepare('SELECT id FROM categories WHERE nama_kategori = :nama AND id != :id');
                $dup->execute([':nama' => $nama_kategori, ':id' => $id]);
                if ($dup->fetch()) {
                    $error = 'Nama kategori tersebut sudah ada!';
                } else {
                    $stmt = $pdo->prepare('UPDATE categories SET nama_kategori = :nama, deskripsi = :deskripsi WHERE id = :id');
                    $stmt->execute([':nama' => $nama_kategori, ':deskripsi' => $deskripsi, ':id' => $id]);
                    setFlash('success', 'Kategori berhasil diperbarui.');
                    header('Location: categories.php');
                    exit;
                }
            } else {
                // Create baru
                $dup = $pdo->prepare('SELECT id FROM categories WHERE nama_kategori = :nama');
                $dup->execute([':nama' => $nama_kategori]);
                if ($dup->fetch()) {
                    $error = 'Nama kategori tersebut sudah ada!';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO categories (nama_kategori, deskripsi) VALUES (:nama, :deskripsi)');
                    $stmt->execute([':nama' => $nama_kategori, ':deskripsi' => $deskripsi]);
                    setFlash('success', 'Kategori baru berhasil ditambahkan.');
                    header('Location: categories.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}

// Data untuk Edit Form
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $category = $stmt->fetch();
    if (!$category) {
        setFlash('error', 'Kategori tidak ditemukan.');
        header('Location: categories.php');
        exit;
    }
}

// Daftar Semua Kategori + Hitungan Jumlah Produk
$categoriesStmt = $pdo->query('
    SELECT c.*, COUNT(p.id) AS total_produk
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.nama_kategori ASC
');
$categories = $categoriesStmt->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Manajemen Kategori</h3>
        <p class="text-muted mb-0">Kelola kategori barang toko elektronik</p>
    </div>
</div>

<div class="row g-4">
    <!-- Form Tambah / Edit Kategori -->
    <div class="col-lg-4">
        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><?= $action === 'edit' ? 'Edit Kategori' : 'Tambah Kategori Baru' ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form action="categories.php?action=<?= e($action) ?><?= $id > 0 ? '&id=' . $id : '' ?>" method="POST">
                    <div class="mb-3">
                        <label for="nama_kategori" class="form-label small fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" 
                               value="<?= e($_POST['nama_kategori'] ?? $category['nama_kategori'] ?? '') ?>" required placeholder="Contoh: Perangkat Jaringan">
                    </div>

                    <div class="mb-4">
                        <label for="deskripsi" class="form-label small fw-semibold">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" placeholder="Deskripsi singkat..."><?= e($_POST['deskripsi'] ?? $category['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-3">
                            <i class="bi bi-check2-circle me-1"></i> <?= $action === 'edit' ? 'Perbarui Kategori' : 'Simpan Kategori' ?>
                        </button>
                        <?php if ($action === 'edit'): ?>
                            <a href="categories.php" class="btn btn-outline-secondary">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Kategori -->
    <div class="col-lg-8">
        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Daftar Kategori Terdaftar</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th class="text-center">Jumlah Produk</th>
                                <th class="text-end" style="width: 130px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada data kategori.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $idx => $cat): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-semibold"><?= e($cat['nama_kategori']) ?></td>
                                        <td class="small text-muted"><?= e($cat['deskripsi'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <?= $cat['total_produk'] ?> Produk
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="categories.php?action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="categories.php?action=delete&id=<?= $cat['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger py-0 px-2" 
                                               title="Hapus"
                                               onclick="return confirm('Yakin ingin menghapus kategori ini?');">
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
        </div>
    </div>
</div>

<?php
include __DIR__ . '/../views/layouts/footer.php';
?>
