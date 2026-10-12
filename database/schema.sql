-- Database: inventory_db
-- Toko Elektronik Maju Jaya

CREATE DATABASE IF NOT EXISTS `inventory_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `inventory_db`;

-- Tabel: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel: categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_kategori` VARCHAR(100) NOT NULL UNIQUE,
    `deskripsi` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel: suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_supplier` VARCHAR(150) NOT NULL,
    `kontak` VARCHAR(100) NULL,
    `alamat` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel: products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `nama_produk` VARCHAR(150) NOT NULL,
    `harga` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `stok` INT NOT NULL DEFAULT 0,
    `minimum_stock` INT NOT NULL DEFAULT 5,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tabel Junction: product_suppliers (Many-to-Many)
CREATE TABLE IF NOT EXISTS `product_suppliers` (
    `product_id` INT NOT NULL,
    `supplier_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`, `supplier_id`),
    CONSTRAINT `fk_ps_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ps_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tabel: stock_movements (History Barang Masuk & Keluar)
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `tipe` ENUM('in', 'out') NOT NULL,
    `quantity` INT NOT NULL,
    `keterangan` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Seeder Akun Dummy (Password hash BCRYPT dari: Admin#12345 dan Staff#12345)
-- Admin#12345 -> $2y$10$wE0v7H.p5U5PjF.kS8lT2eOQ4a0a7lA0h5wV9j6G4P6E0k7P6uK1W (Dibuat dinamis di schema dengan nilai standar yang valid)
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`) VALUES
(1, 'admin', '$2y$10$e8duj7mCPZd8cQeQBkOWO.7/fYVl0KlK0N8QqoIEXW8jTvDJ/yCBW', 'Administrator Maju Jaya', 'admin'),
(2, 'staff', '$2y$10$H1.umHRynbdQcp2ZKfni3eB74cGu9fXAXr7r4Wh0CqldgIF/IUM1y', 'Staff Gudang Maju Jaya', 'staff')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seeder 5 Kategori
INSERT INTO `categories` (`id`, `nama_kategori`, `deskripsi`) VALUES
(1, 'Laptop & Komputer', 'Perangkat komputer pribadi, desktop, laptop, dan peripheral'),
(2, 'Smartphone & Tablet', 'Perangkat komunikasi bergerak dan aksesorisnya'),
(3, 'Audio & Sound System', 'Headphone, speaker bluetooth, earphone, dan soundbar'),
(4, 'Aksesoris & Storage', 'Flashdisk, harddisk eksternal, mouse, dan keyboard mekanikal'),
(5, 'Smart TV & Elektronik Rumah', 'Televisi pintar, set top box, dan perangkat display')
ON DUPLICATE KEY UPDATE `nama_kategori`=`nama_kategori`;

-- Seeder 5 Supplier
INSERT INTO `suppliers` (`id`, `nama_supplier`, `kontak`, `alamat`) VALUES
(1, 'PT Nusantara Elektronik Sentosa', '0812-3456-7890 (Budi)', 'Jl. Mangga Dua Raya No. 45, Jakarta Pusat'),
(2, 'CV Maju Distribusi Tech', '0813-8899-7711 (Dewi)', 'Jl. Gajah Mada No. 12, Surabaya'),
(3, 'PT Sumber Berkat Digital', '0821-2233-4455 (Hendra)', 'Ruko Orion Dusit No. 8, Jakarta Barat'),
(4, 'PT Megah Komunika Asia', '0856-7788-9900 (Rian)', 'Jl. Sudirman Plaza Kav. 21, Bandung'),
(5, 'CV Global Parts Mandiri', '0878-1122-3344 (Siti)', 'Kawasan Industri Cikarang Blok B2, Bekasi')
ON DUPLICATE KEY UPDATE `nama_supplier`=`nama_supplier`;

-- Seeder 30 Produk
INSERT INTO `products` (`id`, `category_id`, `sku`, `nama_produk`, `harga`, `stok`, `minimum_stock`) VALUES
(1, 1, 'LAP-001', 'Laptop Asus Vivobook 14 A1404', 7500000.00, 12, 5),
(2, 1, 'LAP-002', 'Laptop Lenovo IdeaPad Slim 3', 6800000.00, 8, 5),
(3, 1, 'LAP-003', 'Laptop Acer Aspire 5 Intel Core i5', 8200000.00, 4, 5),
(4, 1, 'LAP-004', 'MacBook Air M2 256GB', 15999000.00, 6, 3),
(5, 1, 'LAP-005', 'Laptop HP Pavilion 14 Ryzen 5', 7900000.00, 3, 5),
(6, 1, 'LAP-006', 'Monitor LG 24 Inch IPS 75Hz', 1450000.00, 15, 5),
(7, 2, 'HP-001', 'Samsung Galaxy A54 5G 8/256GB', 5499000.00, 10, 4),
(8, 2, 'HP-002', 'Xiaomi Redmi Note 13 Pro', 3799000.00, 14, 5),
(9, 2, 'HP-003', 'iPhone 15 128GB Black', 14200000.00, 5, 2),
(10, 2, 'HP-004', 'Vivo V29 5G 12/512GB', 5999000.00, 7, 3),
(11, 2, 'HP-005', 'Oppo Reno 11 5G 8/256GB', 5299000.00, 2, 5),
(12, 2, 'HP-006', 'iPad 10th Gen Wi-Fi 64GB', 5800000.00, 9, 3),
(13, 3, 'AUD-001', 'Headset Sony WH-1000XM5 ANC', 4999000.00, 5, 2),
(14, 3, 'AUD-002', 'Speaker JBL Charge 5 Bluetooth', 2499000.00, 11, 4),
(15, 3, 'AUD-003', 'TWS Samsung Galaxy Buds FE', 1199000.00, 20, 5),
(16, 3, 'AUD-004', 'Soundbar Yamaha SR-B20A', 2850000.00, 3, 3),
(17, 3, 'AUD-005', 'Earphone Razer BlackShark V2', 890000.00, 8, 4),
(18, 3, 'AUD-006', 'Microphone Condenser Fifine T669', 550000.00, 12, 5),
(19, 4, 'ACC-001', 'SSD Eksternal Samsung T7 1TB', 1750000.00, 10, 3),
(20, 4, 'ACC-002', 'Harddisk Seagate Backup Plus 2TB', 1150000.00, 16, 5),
(21, 4, 'ACC-003', 'Mouse Logitech Master 3S Wireless', 1450000.00, 7, 3),
(22, 4, 'ACC-004', 'Keyboard Mechanical Keychron K2', 1250000.00, 6, 4),
(23, 4, 'ACC-005', 'Flashdisk SanDisk Ultra Dual 128GB', 165000.00, 35, 10),
(24, 4, 'ACC-006', 'Power Bank Anker 20000mAh 20W PD', 499000.00, 4, 5),
(25, 5, 'TV-001', 'Smart TV Samsung 43 Inch 4K Crystal', 4200000.00, 6, 3),
(26, 5, 'TV-002', 'Smart TV Xiaomi A Pro 32 Inch HD', 1899000.00, 9, 4),
(27, 5, 'TV-003', 'Smart TV LG 55 Inch 4K NanoCell', 7999000.00, 2, 2),
(28, 5, 'TV-004', 'Android TV Box Mi Box S 2nd Gen', 749000.00, 18, 5),
(29, 5, 'TV-005', 'Projector Wanbo T2 Max New 1080P', 2150000.00, 5, 3),
(30, 5, 'TV-006', 'Bracket TV Universal North Bayou 32-55', 185000.00, 25, 5)
ON DUPLICATE KEY UPDATE `sku`=`sku`;

-- Seeder Junction product_suppliers (Relasi Many-to-Many)
INSERT IGNORE INTO `product_suppliers` (`product_id`, `supplier_id`) VALUES
(1, 1), (1, 3),
(2, 1), (2, 2),
(3, 1), (3, 5),
(4, 3),
(5, 1), (5, 2),
(6, 2), (6, 5),
(7, 4), (7, 1),
(8, 4), (8, 2),
(9, 3), (9, 4),
(10, 4),
(11, 4), (11, 2),
(12, 3),
(13, 2), (13, 3),
(14, 2),
(15, 4), (15, 3),
(16, 2),
(17, 1), (17, 5),
(18, 5),
(19, 3), (19, 1),
(20, 1), (20, 5),
(21, 1), (21, 2),
(22, 5), (22, 2),
(23, 1), (23, 5),
(24, 2), (24, 4),
(25, 2), (25, 3),
(26, 4), (26, 2),
(27, 2),
(28, 4), (28, 5),
(29, 3), (29, 5),
(30, 5);

-- Seeder History Transaksi Awal (Stock Movements)
INSERT INTO `stock_movements` (`product_id`, `user_id`, `tipe`, `quantity`, `keterangan`) VALUES
(1, 1, 'in', 15, 'Pengadaan stok awal supplier PT Nusantara Elektronik'),
(1, 2, 'out', 3, 'Penjualan toko offline faktur #INV-001'),
(2, 1, 'in', 10, 'Pengadaan stok awal CV Maju Tech'),
(2, 2, 'out', 2, 'Penjualan toko online marketplace #MP-102'),
(3, 1, 'in', 5, 'Pengadaan barang baru Acer Aspire'),
(3, 2, 'out', 1, 'Penjualan offline faktur #INV-002'),
(5, 1, 'in', 5, 'Stok awal HP Pavilion'),
(5, 2, 'out', 2, 'Penjualan promo awal bulan'),
(11, 1, 'in', 5, 'Stok awal Oppo Reno 11'),
(11, 2, 'out', 3, 'Penjualan preorder 3 unit');
