# Mini Project 3 — Inventory Management System (Toko Elektronik Maju Jaya)

Aplikasi Web Manajemen Inventaris Barang berbasis **PHP 8.2 Native + MySQL 8 (PDO) + Bootstrap 5**, dirancang dengan standar keamanan tinggi, pencegahan SQL Injection (PDO Prepared Statements), otorisasi berbasis peran (Role-Based Access Control), serta transaksi atomik (`SELECT ... FOR UPDATE`) untuk menghindari race condition pada stok fisik.

---

## 📌 Identitas Proyek
- **Mata Kuliah**: Web Programming (Pertemuan 4)
- **Tema Toko**: Toko Elektronik Maju Jaya
- **Nama Repository**: `inventory-app` (Public)
- **Branch Utama**: `main`

---

## 🛠️ Lingkungan Teknis
- **PHP**: Versi 8.2+
- **Database**: MySQL 8.0 / MariaDB 10.4+ (Engine InnoDB)
- **Web Server**: Apache (XAMPP / Laragon / Standalone PHP Built-in Server)
- **Frontend**: Bootstrap 5.3 + Bootstrap Icons

---

## 🚀 Fitur Utama & Aturan Bisnis (Business Rules)
1. **Autentikasi & Otorisasi**:
   - Penyimpanan password terenkripsi menggunakan algoritma `password_hash($pwd, PASSWORD_BCRYPT)`.
   - Proteksi sesi aman dengan `session_regenerate_id(true)`.
   - Otorisasi server-side: Halaman `users.php` (Manajemen Pengguna) hanya dapat diakses oleh role `admin`. Role `staff` langsung ditolak dengan kode status **HTTP 403 Forbidden**.
2. **Katalog & Relasi Database**:
   - CRUD Kategori, Produk, dan Supplier.
   - Relasi **Many-to-Many** antara Produk dan Supplier melalui junction table `product_suppliers`.
   - Aturan **RESTRICT**: Produk yang sudah memiliki riwayat mutasi stok tidak dapat dihapus sembarangan guna menjaga integritas audit trail.
3. **Pencarian, Filter & Navigasi**:
   - Pencarian dinamis berdasarkan SKU dan Nama Produk.
   - Filter dropdown berdasarkan kategori dan status stok (Stok Rendah / Stok Habis).
   - Pengurutan data (Sorting) terproteksi dengan **Allowlist** (`nama_produk`, `sku`, `harga`, `stok`, `kategori`).
   - Paginasi data yang rapi dan responsif.
4. **Transaksi Stok Aman**:
   - Database Transaction (`beginTransaction`, `commit`, `rollBack`).
   - Pessimistic locking menggunakan `SELECT ... FOR UPDATE` saat mutasi barang keluar/masuk.
   - Validasi ketat: Kuantitas barang keluar tidak boleh melebihi stok yang ada, stok akhir tidak boleh negatif.
5. **Dashboard Metrik**:
   - Total produk & unit fisik stok.
   - Akumulasi nilai inventaris (Total Aset = $\sum (\text{harga} \times \text{stok})$).
   - Jumlah produk dengan stok rendah (&le; minimum stock, default 5).
   - Total barang masuk dan barang keluar.

---

## 🔐 Akun Pengguna Dummy
| Role | Username | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `Admin#12345` | Akses penuh (Dashboard, Produk, Kategori, Supplier, Mutasi Stok, Manajemen User) |
| **Staff** | `staff` | `Staff#12345` | Akses operasional (Dashboard, Produk, Kategori, Supplier, Mutasi Stok) |

---

## 📂 Struktur Direktori Proyek
```text
inventory-app/
├── config/
│   ├── database.php          # Konfigurasi koneksi PDO aktif (diabaikan git)
│   └── database.example.php  # Template konfigurasi database
├── database/
│   └── schema.sql            # Skema DDL tabel, relasi FK, dan data seeder
├── helpers/
│   └── functions.php         # Fungsi utilitas (auth, sanitasi e(), format rupiah, flash message)
├── public/
│   ├── index.php             # Dashboard ringkasan stok & grafik/metrik
│   ├── login.php             # Halaman login otentikasi
│   ├── logout.php            # Handler logout & pembersihan sesi
│   ├── categories.php        # Modul CRUD Kategori
│   ├── suppliers.php         # Modul CRUD Supplier
│   ├── products.php          # Katalog produk (Filter, Search, Sort allowlist, Pagination)
│   ├── product_form.php      # Form Tambah/Edit Produk (relasi M:M Supplier)
│   ├── stock_movements.php   # Transaksi mutasi stok (in/out + FOR UPDATE)
│   └── users.php             # Modul CRUD User & Hak Akses (Khusus Admin, 403 untuk staff)
├── views/
│   ├── errors/
│   │   └── 403.php           # Tampilan halaman error HTTP 403 Forbidden
│   └── layouts/
│       ├── header.php        # Template navbar & header Bootstrap 5
│       └── footer.php        # Template footer & JavaScript dependencies
├── .gitignore                # File abaian git
└── README.md                 # Dokumentasi lengkap proyek
```

---

## 💻 Panduan Instalasi & Menjalankan Proyek

### Cara 1: Menggunakan XAMPP
1. Salin atau pindahkan folder `inventory-app` ke dalam folder `C:\xampp\htdocs\`.
2. Buka aplikasi **XAMPP Control Panel**, lalu aktifkan modul **Apache** dan **MySQL**.
3. Buka browser dan akses **phpMyAdmin** di `http://localhost/phpmyadmin/`.
4. Buat database baru bernama `inventory_db` atau impor langsung file `database/schema.sql`.
5. Salin `config/database.example.php` menjadi `config/database.php` (sesuaikan user `root` dan password jika ada).
6. Buka aplikasi di browser:
   ```text
   http://localhost/inventory-app/public/
   ```

### Cara 2: Menggunakan PHP Built-in Server
1. Buka terminal / PowerShell di direktori `inventory-app/public`:
   ```powershell
   cd c:\mini_project_3\inventory-app\public
   php -S localhost:8000
   ```
2. Pastikan database MySQL sudah berjalan dan skema `schema.sql` telah diimpor ke database `inventory_db`.
3. Buka browser di `http://localhost:8000`.

---

## 🧪 Skenario Pengujian Sistem (Test Cases)

### 1. Kasus Validation Error (Stok Keluar Melebihi Kuantitas Yang Tersedia)
- **Langkah Pengujian**:
  1. Login menggunakan akun `staff` (`staff` / `Staff#12345`).
  2. Buka menu **Transaksi Stok** (`stock_movements.php?action=create`).
  3. Pilih produk dengan sisa stok rendah, misalnya **Oppo Reno 11 5G** (sisa stok: 2).
  4. Pilih radio button **Barang Keluar (OUT)**.
  5. Masukkan **Quantity**: `10` (melebihi stok 2).
  6. Klik tombol **Simpan Transaksi Stok**.
- **Hasil yang Diharapkan**:
  - Transaksi otomatis dibatalkan (`rollBack`).
  - Sistem menampilkan pesan error validasi:
    `Stok tidak mencukupi! Stok saat ini untuk Oppo Reno 11 5G 8/256GB adalah 2, permintaan keluar: 10.`
  - Stok fisik produk tetap utuh bernilai 2 dan tidak ada rekaman mutasi yang masuk ke database.

### 2. Kasus Akses Terlarang / Otorisasi (HTTP 403 Forbidden)
- **Langkah Pengujian**:
  1. Login menggunakan akun dengan role staff (`staff` / `Staff#12345`).
  2. Cek navigasi navbar: link **Manajemen User** tidak ditampilkan untuk staff.
  3. Lakukan pengujian bypass URL dengan mengetik langsung alamat: `http://localhost:8000/users.php`.
- **Hasil yang Diharapkan**:
  - Fungsi server-side middleware `requireRole('admin')` aktif.
  - Server mengembalikan status code **HTTP 403**.
  - Browser menampilkan tampilan halaman `views/errors/403.php` bertuliskan **403 - Akses Ditolak**.
  - Akun staff dilarang memanipulasi data user dan role.

### 3. Kasus Integritas RESTRICT (Penghapusan Produk Berhistori)
- **Langkah Pengujian**:
  1. Login sebagai `admin`.
  2. Buka menu **Produk**.
  3. Cari produk yang memiliki histori mutasi (misalnya: `LAP-001` - Asus Vivobook 14).
  4. Klik tombol ikon tempat sampah (**Hapus**).
- **Hasil yang Diharapkan**:
  - Sistem menolak penghapusan dan menampilkan alert error:
    `RESTRICT: Produk ini tidak dapat dihapus karena memiliki riwayat transaksi mutasi stok.`

---

## 📦 Rencana Commit Git (Conventional Commits)
1. `feat(db): create schema, constraints, junction table, and dummy seeders`
2. `feat(core): setup database PDO config and security helpers`
3. `feat(auth): implement login, logout, and role-based 403 middleware`
4. `feat(categories): implement category CRUD module with restrict check`
5. `feat(suppliers): implement supplier CRUD module with many-to-many junction`
6. `feat(products): implement product catalog with search, filter, allowlist sorting, pagination and M:M suppliers`
7. `feat(stock): implement stock movements with FOR UPDATE transaction and audit trail`
8. `feat(users): implement admin user management module`
9. `feat(dashboard): implement metrics summary and low stock alerts`
10. `docs: add comprehensive documentation, test cases, and git guidelines`

---

## 🚀 Panduan Push ke GitHub (Langkah demi Langkah)
Jalankan perintah berikut di PowerShell atau terminal:
```bash
# 1. Masuk ke direktori project
cd c:\mini_project_3\inventory-app

# 2. Inisialisasi Git repository lokal
git init

# 3. Ubah branch default menjadi main
git branch -M main

# 4. Tambahkan seluruh berkas ke staging
git add .

# 5. Buat commit pertama
git commit -m "feat: initial commit of complete inventory management system"

# 6. Hubungkan ke remote GitHub (Ganti [USERNAME] dengan username GitHub Anda)
git remote add origin https://github.com/[USERNAME]/inventory-app.git

# 7. Push berkas ke GitHub
git push -u origin main
```
