<?php
/**
 * hapus_menu.php
 * Admin menghapus menu dari daftar (DELETE) - hanya admin, hanya POST.
 *
 * Menu tidak dibuang dari tabel, melainkan DITANDAI dihapus (dihapus_pada).
 * Alasannya: pesanan lama pelanggan merujuk ke menu ini; kalau barisnya
 * dibuang, riwayat pesanan mereka ikut rusak/hilang.
 */

require_once __DIR__ . '/includes/koneksi.php';
require_once __DIR__ . '/includes/sesi.php';

wajibPeran('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: daftar.php');
    exit;
}
wajibCsrf();

$menu_id = (int) ambilPost('id');

try {
    $stmt = $db->prepare('SELECT nama_menu FROM menu WHERE id = ? AND dihapus_pada IS NULL');
    $stmt->bind_param('i', $menu_id);
    $stmt->execute();
    $menu = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$menu) {
        setFlash('Menu tidak ditemukan atau sudah dihapus.');
    } else {
        $stmt = $db->prepare('UPDATE menu SET dihapus_pada = NOW() WHERE id = ? AND dihapus_pada IS NULL');
        $stmt->bind_param('i', $menu_id);
        $stmt->execute();
        $stmt->close();
        setFlash('Menu "' . $menu['nama_menu'] . '" berhasil dihapus dari daftar. Pesanan yang sudah ada tetap tersimpan.');
    }
} catch (mysqli_sql_exception $ex) {
    error_log('Hapus menu gagal: ' . $ex->getMessage());
    setFlash('Terjadi kesalahan saat menghapus menu. Pastikan database sudah diperbarui (setup_db.php).');
}

header('Location: daftar.php');
exit;
