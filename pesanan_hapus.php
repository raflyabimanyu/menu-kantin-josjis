<?php
/**
 * pesanan_hapus.php
 * Membatalkan pesanan (DELETE) dan mengembalikan stok.
 * Hanya menerima POST dari pemilik pesanan.
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'fungsi_pesanan.php';

wajibLogin();
wajibPeran('anggota');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pesanan.php');
    exit;
}
wajibCsrf();

$pesanan_id = (int) ambilPost('id');

try {
    hapusPesanan($db, (int) $_SESSION['id'], $pesanan_id);
    setFlash('Pesanan berhasil dibatalkan.');
} catch (PesananGagal $ex) {
    setFlash($ex->getMessage());
} catch (Throwable $ex) {
    error_log('Hapus pesanan gagal: ' . $ex->getMessage());
    setFlash('Terjadi kesalahan saat membatalkan pesanan. Silakan coba lagi.');
}

header('Location: pesanan.php');
exit;
