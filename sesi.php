<?php
/**
 * sesi.php
 * Helper untuk session, cookie, CSRF, escaping output, dan penjaga akses
 */

require_once __DIR__ . '/koneksi.php';

// --- Konstanta ---
const SESI_BATAS_DIAM  = 1800;              // 30 menit tanpa aktivitas -> sesi berakhir
const COOKIE_INGAT     = 'ingat_saya';      // cookie login jangka panjang
const INGAT_HARI       = 30;                // masa berlaku cookie "Ingat saya"
const COOKIE_KATEGORI  = 'kategori_terakhir'; // cookie preferensi filter kategori

/**
 * Apakah request ini lewat HTTPS? (menentukan flag "secure" pada cookie)
 */
function pakaiHttps(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/**
 * Opsi standar untuk semua cookie buatan aplikasi ini
 * - httponly : tidak bisa dibaca JavaScript (mengurangi dampak XSS)
 * - samesite : tidak ikut terkirim pada request lintas-situs (mengurangi CSRF)
 * - secure   : hanya dikirim lewat HTTPS (otomatis aktif saat HTTPS)
 */
function opsiCookie(int $kedaluwarsa): array
{
    return [
        'expires'  => $kedaluwarsa,
        'path'     => '/',
        'secure'   => pakaiHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
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
 * Format angka ke format Rupiah
 */
function formatRupiah($harga): string
{
    return 'Rp ' . number_format((int) $harga, 0, ',', '.');
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
        // Jangan timpa pesan yang sudah ada (mis. "sesi berakhir")
        if (empty($_SESSION['flash'])) {
            setFlash('Silakan login terlebih dahulu.');
        }
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
    $nilai = $_POST[$kunci] ?? '';
    return is_string($nilai) ? $nilai : '';
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

// ============================================================
// CSRF - melindungi form yang mengubah data (tambah/edit/hapus/pesan)
// ============================================================

/**
 * Ambil token CSRF milik session ini (dibuat bila belum ada)
 */
function tokenCsrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Hidden input berisi token CSRF, tempel di dalam <form method="POST">
 */
function inputCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(tokenCsrf()) . '">';
}

/**
 * Wajib: token CSRF pada POST harus cocok dengan token di session
 * @param string $kembali      halaman tujuan tautan "kembali" pada halaman error 419
 * @param string $labelKembali teks tautan tersebut
 */
function wajibCsrf(string $kembali = 'daftar.php', string $labelKembali = 'Kembali ke Daftar Menu'): void
{
    $kirim = $_POST['csrf'] ?? '';
    $asli  = $_SESSION['csrf'] ?? '';
    if (!is_string($kirim) || $asli === '' || !hash_equals($asli, $kirim)) {
        http_response_code(419);
        die('
        <!DOCTYPE html>
        <html lang="id">
        <head><meta charset="UTF-8"><title>419 Sesi Tidak Valid</title>
        <style>body{font-family:Arial,sans-serif;text-align:center;padding:50px}h1{color:#d32f2f}</style></head>
        <body>
            <h1>419 - Permintaan Tidak Valid</h1>
            <p>Token keamanan formulir tidak cocok atau sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.</p>
            <p><a href="' . htmlspecialchars($kembali, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($labelKembali, ENT_QUOTES, 'UTF-8') . '</a></p>
        </body>
        </html>
        ');
    }
}

// ============================================================
// SESSION & COOKIE "INGAT SAYA"
// ============================================================

/**
 * Mulai sesi login untuk seorang pengguna.
 * session_regenerate_id mencegah session fixation.
 */
function mulaiSesiPengguna(int $id, string $username, string $nama, string $peran, ?string $fotoProfil = null): void
{
    session_regenerate_id(true);
    $_SESSION['id']       = $id;
    $_SESSION['username'] = $username;
    $_SESSION['nama']     = $nama;
    $_SESSION['peran']    = $peran;
    $_SESSION['foto_profil'] = $fotoProfil; // untuk avatar di navbar (tanpa query tiap halaman)
    $_SESSION['aktivitas_terakhir'] = time();
}

/**
 * Hapus cookie "Ingat saya" dari browser
 */
function hapusCookieIngat(): void
{
    setcookie(COOKIE_INGAT, '', opsiCookie(time() - 3600));
    unset($_COOKIE[COOKIE_INGAT]);
}

/**
 * Buat token "Ingat saya": simpan HASH validator di database,
 * kirim selector:validator ke browser lewat cookie.
 * Database bocor tidak berarti cookie bisa dipalsukan.
 */
function ingatPengguna(mysqli $db, int $penggunaId): void
{
    // Bersihkan token lama yang sudah kedaluwarsa
    $db->query('DELETE FROM token_ingat WHERE kedaluwarsa <= NOW()');

    $selector  = bin2hex(random_bytes(9));   // 18 karakter hex
    $validator = bin2hex(random_bytes(32));  // 64 karakter hex
    $hash      = hash('sha256', $validator);
    $hari      = INGAT_HARI;

    // Waktu kedaluwarsa dihitung di sisi database agar tidak terpengaruh beda zona waktu PHP/MySQL
    $stmt = $db->prepare(
        'INSERT INTO token_ingat (pengguna_id, selector, hash_validator, kedaluwarsa)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))'
    );
    $stmt->bind_param('issi', $penggunaId, $selector, $hash, $hari);
    $stmt->execute();
    $stmt->close();

    $cookie = $selector . ':' . $validator;
    setcookie(COOKIE_INGAT, $cookie, opsiCookie(time() + $hari * 86400));
    $_COOKIE[COOKIE_INGAT] = $cookie;
}

/**
 * Hapus token "Ingat saya" milik browser ini (dipakai saat logout)
 */
function lupakanTokenIngat(mysqli $db): void
{
    if (!empty($_COOKIE[COOKIE_INGAT]) && is_string($_COOKIE[COOKIE_INGAT])) {
        $bagian = explode(':', $_COOKIE[COOKIE_INGAT], 2);
        if (count($bagian) === 2) {
            $stmt = $db->prepare('DELETE FROM token_ingat WHERE selector = ?');
            $stmt->bind_param('s', $bagian[0]);
            $stmt->execute();
            $stmt->close();
        }
    }
    hapusCookieIngat();
}

/**
 * Jika belum login tapi punya cookie "Ingat saya" yang valid, loginkan otomatis.
 * Token diputar (rotasi) tiap dipakai: token lama dihapus, token baru dikirim.
 */
function masukDenganCookieIngat(mysqli $db): void
{
    if (sudahLogin() || empty($_COOKIE[COOKIE_INGAT]) || !is_string($_COOKIE[COOKIE_INGAT])) {
        return;
    }

    $bagian = explode(':', $_COOKIE[COOKIE_INGAT], 2);
    if (count($bagian) !== 2
        || strlen($bagian[0]) !== 18 || !ctype_xdigit($bagian[0])
        || strlen($bagian[1]) !== 64 || !ctype_xdigit($bagian[1])) {
        hapusCookieIngat();
        return;
    }
    [$selector, $validator] = $bagian;

    try {
        $stmt = $db->prepare(
            'SELECT t.id AS token_id, t.hash_validator, u.*
             FROM token_ingat AS t
             JOIN pengguna AS u ON u.id = t.pengguna_id
             WHERE t.selector = ? AND t.kedaluwarsa > NOW()'
        );
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $baris = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($baris === null) {
            hapusCookieIngat(); // token tidak ada / sudah kedaluwarsa
            return;
        }

        if (!hash_equals($baris['hash_validator'], hash('sha256', $validator))) {
            // Selector benar tapi validator salah: curigai pencurian, matikan token ini
            $stmt = $db->prepare('DELETE FROM token_ingat WHERE id = ?');
            $stmt->bind_param('i', $baris['token_id']);
            $stmt->execute();
            $stmt->close();
            hapusCookieIngat();
            return;
        }

        // Valid: mulai sesi (peran diambil segar dari database), lalu rotasi token
        mulaiSesiPengguna((int) $baris['id'], $baris['username'], $baris['nama'], $baris['peran'], $baris['foto_profil'] ?? null);

        $stmt = $db->prepare('DELETE FROM token_ingat WHERE id = ?');
        $stmt->bind_param('i', $baris['token_id']);
        $stmt->execute();
        $stmt->close();
        ingatPengguna($db, (int) $baris['id']);
    } catch (mysqli_sql_exception $ex) {
        error_log('Cookie ingat saya gagal: ' . $ex->getMessage());
    }
}

/**
 * Akhiri sesi bila pengguna diam terlalu lama
 */
function periksaBatasDiam(): void
{
    if (!sudahLogin()) {
        return;
    }
    $terakhir = $_SESSION['aktivitas_terakhir'] ?? time();
    if (time() - $terakhir > SESI_BATAS_DIAM) {
        $_SESSION = [];
        session_regenerate_id(true);
        // Bila punya cookie "Ingat saya", pengguna akan dimasukkan lagi otomatis,
        // jadi pesan "sesi berakhir" tidak perlu ditampilkan.
        if (empty($_COOKIE[COOKIE_INGAT])) {
            setFlash('Sesi Anda berakhir karena tidak aktif. Silakan login kembali.');
        }
        return;
    }
    $_SESSION['aktivitas_terakhir'] = time();
}

// ============================================================
// PENANGKAP ERROR GLOBAL
// Error database yang tidak ditangkap tidak lagi berupa layar putih berisi stack trace.
// ============================================================
set_exception_handler(function (Throwable $ex) {
    error_log('Exception tidak tertangani: ' . $ex->getMessage() . ' di ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    // 1054 = kolom tidak dikenal, 1146 = tabel tidak ada -> struktur database belum diperbarui
    $skema = $ex instanceof mysqli_sql_exception && in_array($ex->getCode(), [1054, 1146], true);
    $admin = ($_SESSION['peran'] ?? '') === 'admin';

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Terjadi Kesalahan</title>'
       . '<style>body{font-family:Arial,sans-serif;text-align:center;padding:50px;color:#333}h1{color:#d32f2f}'
       . 'a.btn{display:inline-block;margin-top:14px;padding:12px 22px;background:#2a9d72;color:#fff;border-radius:8px;text-decoration:none;font-weight:600}'
       . 'pre{text-align:left;background:#f5f5f5;padding:12px;border-radius:8px;max-width:720px;margin:20px auto;white-space:pre-wrap;font-size:13px}</style></head><body>';
    if ($skema) {
        echo '<h1>Struktur database belum diperbarui</h1>';
        if ($admin) {
            echo '<p>Aplikasi ini memerlukan tabel/kolom baru di database.</p><a class="btn" href="setup_db.php">Perbarui Database</a>';
        } else {
            echo '<p>Hubungi admin kantin untuk memperbarui database.</p>';
        }
    } else {
        echo '<h1>Terjadi kesalahan pada server</h1><p>Silakan coba lagi beberapa saat lagi.</p>';
    }
    if ($admin) {
        echo '<pre>' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'; // detail teknis hanya untuk admin
    }
    echo '</body></html>';
});

// ============================================================
// BOOTSTRAP: dijalankan sekali setiap request
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');   // tolak session ID buatan penyerang
    ini_set('session.use_only_cookies', '1');  // session ID tidak boleh lewat URL
    session_set_cookie_params([
        'lifetime' => 0,           // cookie session hilang saat browser ditutup
        'path'     => '/',
        'secure'   => pakaiHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

periksaBatasDiam();
masukDenganCookieIngat($db);
