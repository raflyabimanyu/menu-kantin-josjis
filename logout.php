<?php
/**
 * logout.php
 * Mengakhiri session dan redirect ke login.
 * Hanya menerima POST dengan token CSRF yang valid, supaya situs lain tidak bisa
 * mengeluarkan pengguna lewat tautan/gambar tersembunyi (logout-CSRF).
 */

require_once 'sesi.php';

$csrf_kirim = $_POST['csrf'] ?? '';
$csrf_valid = $_SERVER['REQUEST_METHOD'] === 'POST'
    && is_string($csrf_kirim)
    && !empty($_SESSION['csrf'])
    && hash_equals($_SESSION['csrf'], $csrf_kirim);

if (!$csrf_valid) {
    // Akses langsung (GET) atau token salah: jangan logout, kembali ke halaman utama
    header('Location: ' . (sudahLogin() ? 'daftar.php' : 'login.php'));
    exit;
}

// Matikan token "Ingat saya" (database + cookie) supaya tidak login otomatis lagi
lupakanTokenIngat($db);

// Hapus semua data session
$_SESSION = [];

// Hapus cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect ke halaman login
header('Location: login.php');
exit;
