<?php
// Modul CRUD Supplier Barang
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

requireAuth();

$activePage = 'suppliers';
$pageTitle = 'Supplier Produk';

$action = $_GET['action'] ?? 'index';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$supplier = null;

// Handle DELETE
if ($action === 'delete' && $id > 0) {
    try {
        // Hapus relasi junction akan otomatis CASCADE sesuai skema
        $delStmt = $pdo->prepare('DELETE FROM suppliers WHERE id = :id');
        $delStmt->execute([':id' => $id]);
        setFlash('success', 'Supplier berhasil dihapus.');
    } catch (PDOException $e) {
        setFlash('error', 'Gagal menghapus supplier: ' . $e->getMessage());
    }
    header('Location: suppliers.php');
    exit;
}

// Handle Form POST (Create / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_supplier = trim($_POST['nama_supplier'] ?? '');
    $kontak = trim($_POST['kontak'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    // Validasi
    if (empty($nama_supplier)) {
        $error = 'Nama supplier wajib diisi!';
    } else {
        try {
            if ($action === 'edit' && $id > 0) {
                $stmt = $pdo->prepare('UPDATE suppliers SET nama_supplier = :nama, kontak = :kontak, alamat = :alamat WHERE id = :id');
                $stmt->execute([':nama' => $nama_supplier, ':kontak' => $kontak, ':alamat' => $alamat, ':id' => $id]);
                setFlash('success', 'Data supplier berhasil diperbarui.');
                header('Location: suppliers.php');
                exit;
            } else {
                $stmt = $pdo->prepare('INSERT INTO suppliers (nama_supplier, kontak, alamat) VALUES (:nama, :kontak, :alamat)');
                $stmt->execute([':nama' => $nama_supplier, ':kontak' => $kontak, ':alamat' => $alamat]);
                setFlash('success', 'Supplier baru berhasil ditambahkan.');
                header('Location: suppliers.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}

// Data untuk Edit Form
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $supplier = $stmt->fetch();
    if (!$supplier) {
        setFlash('error', 'Supplier tidak ditemukan.');
        header('Location: suppliers.php');
        exit;
    }
}

// Daftar Semua Supplier + Jumlah Produk Terhubung (Many-to-Many via product_suppliers)
$suppliersStmt = $pdo->query('
    SELECT s.*, COUNT(ps.product_id) AS total_produk_disuplai
    FROM suppliers s
    LEFT JOIN product_suppliers ps ON s.id = ps.supplier_id
    GROUP BY s.id
    ORDER BY s.nama_supplier ASC
');
$suppliers = $suppliersStmt->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Manajemen Supplier</h3>
        <p class="text-muted mb-0">Kelola distributor dan pemasok barang elektronik</p>
    </div>
</div>

<div class="row g-4">
    <!-- Form Tambah / Edit Supplier -->
    <div class="col-lg-4">
        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><?= $action === 'edit' ? 'Edit Supplier' : 'Tambah Supplier Baru' ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form action="suppliers.php?action=<?= e($action) ?><?= $id > 0 ? '&id=' . $id : '' ?>" method="POST">
                    <div class="mb-3">
                        <label for="nama_supplier" class="form-label small fw-semibold">Nama Supplier <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_supplier" name="nama_supplier" 
                               value="<?= e($_POST['nama_supplier'] ?? $supplier['nama_supplier'] ?? '') ?>" required placeholder="Contoh: PT Surya Jaya Elektronik">
                    </div>

                    <div class="mb-3">
                        <label for="kontak" class="form-label small fw-semibold">Kontak / No. Telepon / PIC</label>
                        <input type="text" class="form-control" id="kontak" name="kontak" 
                               value="<?= e($_POST['kontak'] ?? $supplier['kontak'] ?? '') ?>" placeholder="Contoh: 0812-3456-7890 (Bpk. Toni)">
                    </div>

                    <div class="mb-4">
                        <label for="alamat" class="form-label small fw-semibold">Alamat Kantor / Gudang</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Alamat lengkap..."><?= e($_POST['alamat'] ?? $supplier['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-3">
                            <i class="bi bi-check2-circle me-1"></i> <?= $action === 'edit' ? 'Perbarui Supplier' : 'Simpan Supplier' ?>
                        </button>
                        <?php if ($action === 'edit'): ?>
                            <a href="suppliers.php" class="btn btn-outline-secondary">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Supplier -->
    <div class="col-lg-8">
        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Daftar Supplier Terdaftar</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Nama Supplier</th>
                                <th>Kontak</th>
                                <th>Alamat</th>
                                <th class="text-center">Produk Disuplai</th>
                                <th class="text-end" style="width: 130px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($suppliers)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada data supplier.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($suppliers as $idx => $sup): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td class="fw-semibold text-dark"><?= e($sup['nama_supplier']) ?></td>
                                        <td class="small"><?= e($sup['kontak'] ?? '-') ?></td>
                                        <td class="small text-muted text-truncate" style="max-width: 200px;"><?= e($sup['alamat'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-info-subtle text-info border">
                                                <?= $sup['total_produk_disuplai'] ?> Item
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="suppliers.php?action=edit&id=<?= $sup['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="suppliers.php?action=delete&id=<?= $sup['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger py-0 px-2" 
                                               title="Hapus"
                                               onclick="return confirm('Yakin ingin menghapus supplier ini?');">
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
