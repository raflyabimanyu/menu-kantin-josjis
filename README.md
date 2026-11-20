# Menu Kantin JosJis

Aplikasi web pemesanan makanan dan manajemen stok kantin berbasis **PHP Native** dan **MySQL** dengan antarmuka responsif serta fitur keamanan modern.

---

## Fitur Utama

### Admin
* **Manajemen Menu Lengkap:** Tambah menu beserta stok awal (`tambah.php`), edit deskripsi, serta tambah/kurangi stok (`edit.php`).
* **Soft Delete Menu:** Menghapus menu (`hapus_menu.php`) hanya menandai kolom `dihapus_pada`, sehingga riwayat transaksi pelanggan tetap aman dan utuh.
* **Kontrol Stok Cepat:** Ubah stok langsung (`+` / `-`) dari kartu menu atau atur jumlah spesifik (batas `0` – `99.999`) di halaman edit.
* **Monitoring Pesanan:** Melihat stok seluruh menu secara *real-time* dan memantau semua pesanan yang masuk.

### Pelanggan (Anggota)
* **Pemesanan Interaktif:** Atur jumlah pesanan langsung di kartu menu (`0` – `20` porsi per item).
* **Keranjang Melayang (Floating Cart):** Akses cepat di pojok kanan bawah untuk mengatur jumlah item, menambahkan catatan pesanan, dan melihat total harga. Data keranjang otomatis tersimpan dan sinkron antar-tab browser.
* **Transaksi Aman (Atomic Checkout):** Proses *checkout* (`checkout.php`) mendukung metode Tunai, QRIS, dan Transfer *(simulasi)*. Menggunakan transaksi database penuh—jika satu item kehabisan stok saat proses bayar, seluruh transaksi otomatis dibatalkan agar stok tetap akurat.
* **Kelola Pesanan:** Pelanggan dapat mengubah jumlah/catatan pesanan (stok otomatis menyesuaikan selisihnya) atau membatalkan pesanan (stok dikembalikan).
* **Status Ketersediaan Otomatis:** Sisa stok disembunyikan dari pelanggan; menu dengan stok `0` otomatis berlabel **"Habis"**.

### Akun dan Keamanan
* **Manajemen Profil:** Ubah *username*, bio, dan foto profil (`JPG/PNG/WEBP`, maks. `2 MB` dengan penamaan file acak di `uploads/profil/`).
* **Session dan Cookie Terproteksi:**
  * *Session* login menggunakan `HttpOnly` + `SameSite`, regenerasi ID saat login, dan *auto-logout* jika diam selama 30 menit.
  * Fitur **"Ingat Saya"** menggunakan token *cookie* 30 hari yang di-*hash* di database, dirotasi setiap kali dipakai, dan dihapus saat *logout*.
  * **Cookie Preferensi:** Otomatis mengingat kategori menu terakhir yang dipilih pengguna.
* **Proteksi CSRF dan Error Handling:** Seluruh form (termasuk login, registrasi, dan logout via `POST`) dilindungi token CSRF. Error database ditangani dengan halaman pesan ramah beserta pintasan perbaikan struktur database untuk admin.

---

## Cara Menjalankan (Lokal / XAMPP)

### 1. Persiapan Server
1. Pindahkan folder proyek ini ke dalam direktori `xampp/htdocs/`.
2. Buka aplikasi **XAMPP Control Panel**, lalu jalankan module **Apache** dan **MySQL**.

### 2. Konfigurasi Environment (`.env`)
File `.env` sudah disertakan dengan pengaturan bawaan XAMPP:
```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=menu_kantin
```
> **Catatan Produksi:** Jika di-deploy ke server publik, pindahkan file `.env` ke luar folder web publik dan arahkan melalui variabel lingkungan `APP_ENV_FILE`.

### 3. Setup Database
Buka **phpMyAdmin** di `http://localhost/phpmyadmin/`, lalu pilih salah satu langkah berikut:
* **Instalasi Baru:** Buat database atau langsung *import* file `app.sql` (database `menu_kantin` akan dibuat secara otomatis).
* **Update dari Database Lama (Jangan import `app.sql`):**
  1. Login ke aplikasi sebagai **Admin**.
  2. Buka alamat `http://localhost/<nama_folder>/setup_db.php`.
  3. Klik **"Terapkan Perbaikan"**. Skrip ini aman dijalankan berulang kali untuk menambah kolom/tabel yang kurang tanpa menghapus data lama (tabel pesanan lama yang strukturnya berbeda akan di-*rename* menjadi `pesanan_lama_...`).

### 4. Akses Aplikasi
Buka browser dan kunjungi:
```text
http://localhost/<nama_folder>/login.php
```

---

## Akun Demo Bawaan

|   Role        | Username    | Password    |
|   ---         |   ---       |   ---       |
| **Admin**     |   `admin`   | `admin123`  |
| **Pelanggan** |   `rina`    | `rina123`   |

> **Troubleshooting Login:** Jika password di atas ditolak karena perbedaan *hash* pada database lama, jalankan query `UPDATE pengguna SET hash_sandi = '<hash_baru>'` melalui phpMyAdmin, atau *import* ulang `app.sql` untuk instalasi bersih.

---

*Menu Kantin 2026 — Dibuat untuk Tugas Pemrograman Web I*
