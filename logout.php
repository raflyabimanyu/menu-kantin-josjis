<?php
/**
 * logout.php
 * Mengakhiri session dan redirect ke login
 */

require_once 'sesi.php';

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
?>
