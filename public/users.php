<?php
// Modul Manajemen User Khusus Role ADMIN (Role Based Access Control 403)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

// Otorisasi Ketat: Hanya Admin yang boleh mengakses modul ini
requireRole('admin');

$activePage = 'users';
$pageTitle = 'Manajemen User & Hak Akses';
$action = $_GET['action'] ?? 'index';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentAdmin = currentUser();
$errors = [];
$userEdit = null;

// Handle DELETE User
if ($action === 'delete' && $id > 0) {
    if ($id === (int)$currentAdmin['id']) {
        setFlash('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.');
    } else {
        try {
            // Cek apakah user punya histori transaksi mutasi
            $checkSm = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE user_id = :id');
            $checkSm->execute([':id' => $id]);
            if ($checkSm->fetchColumn() > 0) {
                setFlash('error', 'RESTRICT: User tidak dapat dihapus karena tercatat sebagai operator dalam histori mutasi stok.');
            } else {
                $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $del->execute([':id' => $id]);
                setFlash('success', 'User berhasil dihapus.');
            }
        } catch (PDOException $e) {
            setFlash('error', 'Gagal menghapus user: ' . $e->getMessage());
        }
    }
    header('Location: users.php');
    exit;
}

// Handle POST Create / Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';

    // Validasi
    if (empty($username)) {
        $errors[] = 'Username wajib diisi.';
    }
    if (empty($nama_lengkap)) {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if (!in_array($role, ['admin', 'staff'], true)) {
        $errors[] = 'Role tidak valid (harus admin atau staff).';
    }

    if ($action === 'create' && empty($password)) {
        $errors[] = 'Password wajib diisi untuk user baru.';
    }

    // Cek duplikasi username
    $checkSql = 'SELECT id FROM users WHERE username = :username' . ($action === 'edit' ? ' AND id != :id' : '');
    $checkStmt = $pdo->prepare($checkSql);
    $checkParams = [':username' => $username];
    if ($action === 'edit') {
        $checkParams[':id'] = $id;
    }
    $checkStmt->execute($checkParams);
    if ($checkStmt->fetch()) {
        $errors[] = "Username '{$username}' sudah digunakan user lain.";
    }

    if (empty($errors)) {
        try {
            if ($action === 'edit' && $id > 0) {
                if (!empty($password)) {
                    // Update dengan password baru (hash BCRYPT)
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare('UPDATE users SET username = :username, password = :pwd, nama_lengkap = :nama, role = :role WHERE id = :id');
                    $upd->execute([
                        ':username' => $username,
                        ':pwd'      => $hash,
                        ':nama'     => $nama_lengkap,
                        ':role'     => $role,
                        ':id'       => $id
                    ]);
                } else {
                    // Update tanpa mengubah password lama
                    $upd = $pdo->prepare('UPDATE users SET username = :username, nama_lengkap = :nama, role = :role WHERE id = :id');
                    $upd->execute([
                        ':username' => $username,
                        ':nama'     => $nama_lengkap,
                        ':role'     => $role,
                        ':id'       => $id
                    ]);
                }
                setFlash('success', 'Data user berhasil diperbarui.');
                header('Location: users.php');
                exit;

            } else {
                // Insert User Baru dengan password_hash()
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO users (username, password, nama_lengkap, role) VALUES (:username, :pwd, :nama, :role)');
                $ins->execute([
                    ':username' => $username,
                    ':pwd'      => $hash,
                    ':nama'     => $nama_lengkap,
                    ':role'     => $role
                ]);
                setFlash('success', 'User baru berhasil didaftarkan.');
                header('Location: users.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Database Error: ' . $e->getMessage();
        }
    }
}

// Ambil data untuk Edit
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare('SELECT id, username, nama_lengkap, role FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $userEdit = $stmt->fetch();
    if (!$userEdit) {
        setFlash('error', 'User tidak ditemukan.');
        header('Location: users.php');
        exit;
    }
}

// Ambil Seluruh Data User
$usersList = $pdo->query('SELECT id, username, nama_lengkap, role, created_at FROM users ORDER BY id ASC')->fetchAll();

include __DIR__ . '/../views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Manajemen Pengguna (User Management)</h3>
        <p class="text-muted mb-0">Kelola akun dan hak akses otoritas (Admin & Staff)</p>
    </div>
</div>

<div class="row g-4">
    <!-- Formulir Tambah / Edit Pengguna -->
    <div class="col-lg-4">
        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><?= $action === 'edit' ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <strong class="d-block mb-1">Terjadi Kesalahan:</strong>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $e): ?>
                                <li><?= e($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="users.php?action=<?= e($action === 'edit' ? 'edit' : 'create') ?><?= $id > 0 ? '&id=' . $id : '' ?>" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?= e($_POST['username'] ?? $userEdit['username'] ?? '') ?>" required placeholder="Contoh: staff_gudang">
                    </div>

                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" 
                               value="<?= e($_POST['nama_lengkap'] ?? $userEdit['nama_lengkap'] ?? '') ?>" required placeholder="Contoh: Ahmad Fauzi">
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label small fw-semibold">Hak Akses (Role) <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <?php $currentRole = $_POST['role'] ?? $userEdit['role'] ?? 'staff'; ?>
                            <option value="staff" <?= $currentRole === 'staff' ? 'selected' : '' ?>>Staff (Operasional Stok & Produk)</option>
                            <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>Admin (Akses Penuh + Manajemen User)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label small fw-semibold">
                            Password <?= $action === 'edit' ? '<span class="text-muted fw-normal">(Kosongkan jika tidak diubah)</span>' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               <?= $action === 'edit' ? '' : 'required' ?> placeholder="Minimal kombinasi aman">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-3">
                            <i class="bi bi-person-check me-1"></i> <?= $action === 'edit' ? 'Perbarui User' : 'Simpan User' ?>
                        </button>
                        <?php if ($action === 'edit'): ?>
                            <a href="users.php" class="btn btn-outline-secondary">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Pengguna -->
    <div class="col-lg-8">
        <div class="card card-custom shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Daftar Akun Pengguna Terdaftar</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>#</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th class="text-center">Role</th>
                                <th>Terdaftar Sejak</th>
                                <th class="text-end" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersList as $idx => $u): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td class="fw-semibold">
                                        <?= e($u['nama_lengkap']) ?>
                                        <?php if ((int)$u['id'] === (int)$currentAdmin['id']): ?>
                                            <span class="badge bg-secondary-subtle text-secondary ms-1">Anda</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= e($u['username']) ?></code></td>
                                    <td class="text-center">
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge bg-danger">ADMIN</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">STAFF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                    <td class="text-end">
                                        <a href="users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <?php if ((int)$u['id'] !== (int)$currentAdmin['id']): ?>
                                            <a href="users.php?action=delete&id=<?= $u['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger py-0 px-2" 
                                               title="Hapus"
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus akun user ini?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
