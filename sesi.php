<?php
/**
 * sesi.php
 * Helper untuk session, escaping output, dan penjaga akses
 */

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output untuk mencegah XSS
 * @param mixed $nilai - nilai yang akan di-escape
 * @return string - nilai yang sudah di-escape
 */
function e($nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

/**
 * Cek apakah pengguna sudah login
 * @return bool
 */
function sudahLogin(): bool
{
    return isset($_SESSION['id']) && isset($_SESSION['username']);
}

/**
 * Wajib login - redirect ke login jika belum login
 */
function wajibLogin(): void
{
    if (!sudahLogin()) {
        setFlash('Silakan login terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Wajib peran tertentu - tampilkan 403 jika peran tidak sesuai
 * @param string $peran - peran yang dibutuhkan (admin/anggota)
 */
function wajibPeran(string $peran): void
{
    wajibLogin();
    if ($_SESSION['peran'] !== $peran) {
        http_response_code(403);
        die('
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>403 Forbidden</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                h1 { color: #d32f2f; }
            </style>
        </head>
        <body>
            <h1>403 - Akses Ditolak</h1>
            <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
            <p><a href="daftar.php">Kembali ke Daftar Menu</a></p>
        </body>
        </html>
        ');
    }
}

/**
 * Set pesan flash (pesan sekali tampil)
 * @param string $pesan
 */
function setFlash(string $pesan): void
{
    $_SESSION['flash'] = $pesan;
}

/**
 * Ambil dan hapus pesan flash
 * @return string|null
 */
function ambilFlash(): ?string
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $pesan = $_SESSION['flash'];
    unset($_SESSION['flash']); // Tampil sekali lalu hilang
    return $pesan;
}

/**
 * Ambil nilai POST dengan default kosong
 * @param string $kunci
 * @return string
 */
function ambilPost(string $kunci): string
{
    return $_POST[$kunci] ?? '';
}

/**
 * Bersihkan teks dengan trim
 * @param string $teks
 * @return string
 */
function bersihkanTeks(string $teks): string
{
    return trim($teks);
}
?>
