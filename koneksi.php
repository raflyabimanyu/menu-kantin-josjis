<?php
/**
 * koneksi.php
 * Koneksi ke database MySQL. Kredensial TIDAK ditulis di sini, melainkan dibaca dari
 * file .env (lihat .env.example). Lokasi lain dapat ditentukan lewat variabel
 * lingkungan APP_ENV_FILE (mis. file di luar folder htdocs agar tidak bisa diakses browser).
 */

$db = null;

// Pastikan error mysqli dilempar sebagai exception (dibutuhkan untuk transaksi/rollback)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Baca file .env sederhana: baris KEY=VALUE, "#" untuk komentar, nilai boleh diapit kutip.
 * @return array<string,string>
 */
function bacaFileEnv(string $path): array
{
    $hasil = [];
    if (!is_readable($path)) {
        return $hasil;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $baris) {
        $baris = trim($baris);
        if ($baris === '' || $baris[0] === '#' || strpos($baris, '=') === false) {
            continue;
        }
        [$kunci, $nilai] = explode('=', $baris, 2);
        $kunci = trim($kunci);
        $nilai = trim($nilai);
        if (strlen($nilai) >= 2 && ($nilai[0] === '"' || $nilai[0] === "'") && substr($nilai, -1) === $nilai[0]) {
            $nilai = substr($nilai, 1, -1);
        }
        $hasil[$kunci] = $nilai;
    }
    return $hasil;
}

try {
    $path_env = getenv('APP_ENV_FILE') ?: __DIR__ . '/.env';
    $env = bacaFileEnv($path_env);

    // Nilai dari .env diutamakan; bila tidak ada, boleh dari variabel lingkungan server
    $ambil = function (string $kunci, ?string $bawaan = null) use ($env): string {
        if (array_key_exists($kunci, $env)) {
            return $env[$kunci];
        }
        $dari_server = getenv($kunci);
        if ($dari_server !== false) {
            return $dari_server;
        }
        if ($bawaan !== null) {
            return $bawaan;
        }
        throw new RuntimeException("Konfigurasi '$kunci' tidak ditemukan. Salin .env.example menjadi .env lalu isi nilainya.");
    };

    $db = new mysqli(
        $ambil('DB_HOST'),
        $ambil('DB_USER'),
        $ambil('DB_PASS'),
        $ambil('DB_NAME'),
        (int) $ambil('DB_PORT', '3306')
    );

    // Atur charset ke UTF-8
    if (!$db->set_charset('utf8mb4')) {
        throw new Exception('Gagal mengatur charset: ' . $db->error);
    }
} catch (Exception $ex) {
    // Catat error ke log, tampilkan pesan umum ke pengguna (tanpa membocorkan kredensial)
    error_log('Database connection failed: ' . $ex->getMessage());
    die('Terjadi kesalahan koneksi database. Silakan coba lagi nanti.');
}
unset($env, $ambil, $path_env);
