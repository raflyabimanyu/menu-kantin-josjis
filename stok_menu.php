<?php
/**
 * stok_menu.php
 * Admin menambah/mengurangi stok menu sebanyak 1 langsung dari kartu menu (tombol - / +).
 * Hanya admin, hanya POST, token CSRF wajib. Stok tidak bisa kurang dari 0.
 */

require_once __DIR__ . '/includes/koneksi.php';
require_once __DIR__ . '/includes/sesi.php';
require_once __DIR__ . '/includes/fungsi_pesanan.php';

wajibPeran('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar.php');
    exit;
}
wajibCsrf();

$menu_id = (int) ambilPost('id');
$aksi = ambilPost('aksi');

if ($menu_id <= 0 || !in_array($aksi, ['tambah', 'kurang'], true)) {
    setFlash('Permintaan tidak valid.');
    header('Location: daftar.php');
    exit;
}

try {
    $stok_baru = sesuaikanStok($db, $menu_id, $aksi === 'tambah' ? 1 : -1);
    setFlash('Stok diperbarui: sekarang ' . $stok_baru . '.');
} catch (PesananGagal $ex) {
    setFlash($ex->getMessage());
} catch (Throwable $ex) {
    error_log('Sesuaikan stok gagal: ' . $ex->getMessage());
    setFlash('Terjadi kesalahan saat memperbarui stok.');
}

// Kembali ke kartu yang sama (anchor) agar posisi scroll tidak melompat ke atas
header('Location: daftar.php#menu-' . $menu_id);
exit;
