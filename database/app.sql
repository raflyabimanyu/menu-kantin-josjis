-- Skema Menu Kantin (instalasi baru)
-- Jika database Anda SUDAH berjalan dari versi sebelumnya, JANGAN import file ini.
-- Login sebagai admin lalu buka setup_db.php (memperbarui struktur tanpa menghapus data).
CREATE DATABASE IF NOT EXISTS menu_kantin;
USE menu_kantin;

-- Tabel pengguna (akun)
CREATE TABLE pengguna (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  peran ENUM('admin','anggota') NOT NULL DEFAULT 'anggota',
  hash_sandi VARCHAR(255) NOT NULL,
  foto_profil VARCHAR(255) NULL,   -- nama file di uploads/profil/
  bio VARCHAR(255) NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel menu (entitas utama)
-- stok hanya ditampilkan ke admin; berkurang otomatis saat ada pesanan
CREATE TABLE menu (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT UNSIGNED NOT NULL,
  nama_menu VARCHAR(100) NOT NULL,
  deskripsi TEXT NOT NULL,
  harga INT NOT NULL,
  stok INT UNSIGNED NOT NULL DEFAULT 0,
  kategori ENUM('Makanan','Minum','Camilan','Dessert','Lainnya') NOT NULL DEFAULT 'Lainnya',
  gambar VARCHAR(255) NULL,
  tersedia ENUM('Ya','Tidak') NOT NULL DEFAULT 'Ya',
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  dihapus_pada DATETIME NULL DEFAULT NULL, -- terisi = menu "dihapus" admin (disembunyikan, riwayat pesanan tetap aman)
  FOREIGN KEY (pengguna_id) REFERENCES pengguna (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel pesanan (satu baris = satu menu yang dipesan seorang pelanggan)
-- harga_satuan disimpan sebagai "snapshot" harga saat pesanan dibuat
CREATE TABLE pesanan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT UNSIGNED NOT NULL,
  menu_id INT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  harga_satuan INT NOT NULL,
  catatan VARCHAR(255) NULL,
  metode_bayar VARCHAR(20) NOT NULL DEFAULT 'Tunai',
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pesanan_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna (id),
  CONSTRAINT fk_pesanan_menu FOREIGN KEY (menu_id) REFERENCES menu (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel token "Ingat saya" (cookie login jangka panjang)
-- Yang disimpan hanya HASH dari validator, bukan nilai asli di cookie
CREATE TABLE token_ingat (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL UNIQUE,
  hash_validator CHAR(64) NOT NULL,
  kedaluwarsa DATETIME NOT NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pengguna_id) REFERENCES pengguna (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal: akun (admin / admin123 dan rina / rina123)
INSERT INTO pengguna (username, nama, peran, hash_sandi) VALUES
('admin', 'Administrator Kantin', 'admin', '$2y$10$8F9KmOZADDYSbmzP88l/KOgIaHOdB6CpwPe9zXL1nQrAAKqmON7Je'),
('rina', 'Rina Wulandari', 'anggota', '$2y$10$3tbtY0uH46fhW722xkDkTuZ.M1zBYATKOrB5dI/q7ETFquRKEFHaq');

-- Data awal: menu (5 baris, stok awal 20)
INSERT INTO menu (pengguna_id, nama_menu, deskripsi, harga, stok, kategori, tersedia) VALUES
(1, 'Nasi Goreng Spesial', 'Nasi goreng dengan telur, ayam, dan sayuran pilihan', 15000, 20, 'Makanan', 'Ya'),
(1, 'Teh Tarik Premium', 'Teh tarik kental kaya dengan susu', 8000, 20, 'Minum', 'Ya'),
(1, 'Martabak Telor', 'Martabak dengan isi telur, keju, dan coklat', 12000, 20, 'Camilan', 'Ya'),
(2, 'Es Krim Vanilla', 'Es krim vanilla premium dengan topping coklat', 10000, 20, 'Dessert', 'Ya'),
(2, 'Mie Ayam Kuah', 'Mie ayam dengan kuah gurih dan topping telur', 14000, 20, 'Makanan', 'Ya');
