<?php
/**
 * koneksi.php
 * Koneksi ke database MySQL dengan error handling
 */

$db = null;

try {
    // Buat koneksi ke database menu_kantin
    $db = new mysqli('localhost', 'root', '', 'menu_kantin');
    
    // Atur charset ke UTF-8
    if (!$db->set_charset('utf8mb4')) {
        throw new Exception('Gagal mengatur charset: ' . $db->error);
    }
} catch (Exception $ex) {
    // Catat error ke log, tampilkan pesan umum ke pengguna
    error_log('Database connection failed: ' . $ex->getMessage());
    die('Terjadi kesalahan koneksi database. Silakan coba lagi nanti.');
}
?>
