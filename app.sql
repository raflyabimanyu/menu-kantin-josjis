-- Skema Menu Kantin
CREATE DATABASE IF NOT EXISTS menu_kantin;
USE menu_kantin;

-- Tabel pengguna (akun)
CREATE TABLE pengguna (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  peran ENUM('admin','anggota') NOT NULL DEFAULT 'anggota',
  hash_sandi VARCHAR(255) NOT NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel menu (entitas utama)
CREATE TABLE menu (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT UNSIGNED NOT NULL,
  nama_menu VARCHAR(100) NOT NULL,
  deskripsi TEXT NOT NULL,
  harga INT NOT NULL,
  kategori ENUM('Nasi','Minum','Camilan','Dessert','Lainnya') NOT NULL DEFAULT 'Lainnya',
  tersedia ENUM('Ya','Tidak') NOT NULL DEFAULT 'Ya',
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pengguna_id) REFERENCES pengguna (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal: akun (password di-hash dengan password_hash)
INSERT INTO pengguna (username, nama, peran, hash_sandi) VALUES
('admin', 'Administrator Kantin', 'admin', '$2y$10$qHJ2e/kKEGrN0U8gJ9tUZ.GjGXkGqLANvQbFEu3LQxVvmKiQvqpXK'),
('rina', 'Rina Wulandari', 'anggota', '$2y$10$nBg9c2xV7z1pJ.kM5qL8pO4dXxYz3aB1cD2eF5gH6iJ7kL8nM9oO');

-- Data awal: menu (5 baris)
INSERT INTO menu (pengguna_id, nama_menu, deskripsi, harga, kategori, tersedia) VALUES
(1, 'Nasi Goreng Spesial', 'Nasi goreng dengan telur, ayam, dan sayuran pilihan', 15000, 'Nasi', 'Ya'),
(1, 'Teh Tarik Premium', 'Teh tarik kental kaya dengan susu', 8000, 'Minum', 'Ya'),
(1, 'Martabak Telor', 'Martabak dengan isi telur, keju, dan coklat', 12000, 'Camilan', 'Ya'),
(2, 'Es Krim Vanilla', 'Es krim vanilla premium dengan topping coklat', 10000, 'Dessert', 'Ya'),
(2, 'Mie Ayam Kuah', 'Mie ayam dengan kuah gurih dan topping telur', 14000, 'Nasi', 'Ya');
