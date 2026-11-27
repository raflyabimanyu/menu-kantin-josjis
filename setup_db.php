<?php
/**
 * setup_db.php
 * Memeriksa struktur database lalu memperbaikinya agar sesuai dengan kode aplikasi.
 * - HANYA admin
 * - Aman dijalankan berulang kali (hanya mengerjakan yang memang kurang)
 * - Tidak menghapus data: tabel pesanan yang tidak cocok diganti namanya
 *   (pesanan_lama_...), bukan di-DROP, kecuali tabelnya kosong.
 * Alur: halaman menampilkan RENCANA dulu; perubahan baru dijalankan setelah tombol ditekan.
 */

require_once __DIR__ . '/includes/koneksi.php';
require_once __DIR__ . '/includes/sesi.php';

// Proteksi akses: wajib login DAN berperan admin sebelum kode apa pun di bawah dijalankan
wajibLogin();
wajibPeran('admin');

// ---- Struktur tabel yang diharapkan ----
const DDL_PESANAN = "CREATE TABLE pesanan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT UNSIGNED NOT NULL,
  menu_id INT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  harga_satuan INT NOT NULL,
  catatan VARCHAR(255) NULL,
  metode_bayar VARCHAR(20) NOT NULL DEFAULT 'Tunai',
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pesanan_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna (id),
  CONSTRAINT fk_pesanan_menu FOREIGN KEY (menu_id) REFERENCES menu (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

const DDL_TOKEN_INGAT = "CREATE TABLE token_ingat (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pengguna_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL UNIQUE,
  hash_validator CHAR(64) NOT NULL,
  kedaluwarsa DATETIME NOT NULL,
  dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_token_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Kolom yang WAJIB ada agar pesanan.php / fungsi_pesanan.php bekerja
const PESANAN_INTI = ['id', 'pengguna_id', 'menu_id', 'jumlah', 'harga_satuan', 'dibuat_pada'];
// Kolom pelengkap yang bisa ditambahkan tanpa membuang data
const PESANAN_OPSIONAL = [
    'catatan'         => 'ALTER TABLE pesanan ADD COLUMN catatan VARCHAR(255) NULL',
    'metode_bayar'    => "ALTER TABLE pesanan ADD COLUMN metode_bayar VARCHAR(20) NOT NULL DEFAULT 'Tunai'",
    'diperbarui_pada' => 'ALTER TABLE pesanan ADD COLUMN diperbarui_pada TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
];

function tabelAda(mysqli $db, string $tabel): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $tabel);
    $stmt->execute();
    $ada = (int) $stmt->get_result()->fetch_row()[0] > 0;
    $stmt->close();
    return $ada;
}

/** @return array<string,array> nama kolom => info (IS_NULLABLE, COLUMN_DEFAULT, EXTRA) */
function kolomTabel(mysqli $db, string $tabel): array
{
    $stmt = $db->prepare(
        'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->bind_param('s', $tabel);
    $stmt->execute();
    $hasil = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $baris) {
        $hasil[$baris['COLUMN_NAME']] = $baris;
    }
    $stmt->close();
    return $hasil;
}

/**
 * Susun rencana perbaikan.
 * @return array<int,array{judul:string,sql:string[]}>
 */
function susunRencana(mysqli $db): array
{
    $rencana = [];

    // --- pengguna (profil) ---
    $kolomPengguna = kolomTabel($db, 'pengguna');
    if (!isset($kolomPengguna['foto_profil'])) {
        $rencana[] = ['judul' => 'Tambah kolom pengguna.foto_profil (foto profil)', 'sql' => [
            'ALTER TABLE pengguna ADD COLUMN foto_profil VARCHAR(255) NULL',
        ]];
    }
    if (!isset($kolomPengguna['bio'])) {
        $rencana[] = ['judul' => 'Tambah kolom pengguna.bio', 'sql' => [
            'ALTER TABLE pengguna ADD COLUMN bio VARCHAR(255) NULL',
        ]];
    }

    // --- menu ---
    $kolomMenu = kolomTabel($db, 'menu');
    if (!isset($kolomMenu['stok'])) {
        $rencana[] = ['judul' => 'Tambah kolom menu.stok (menu yang sudah ada diberi stok awal 20)', 'sql' => [
            'ALTER TABLE menu ADD COLUMN stok INT UNSIGNED NOT NULL DEFAULT 0 AFTER harga',
            'UPDATE menu SET stok = 20',
        ]];
    }
    if (!isset($kolomMenu['dihapus_pada'])) {
        $rencana[] = ['judul' => 'Tambah kolom menu.dihapus_pada (untuk fitur hapus menu)', 'sql' => [
            'ALTER TABLE menu ADD COLUMN dihapus_pada DATETIME NULL DEFAULT NULL',
        ]];
    }

    // --- pesanan ---
    if (!tabelAda($db, 'pesanan')) {
        $rencana[] = ['judul' => 'Buat tabel pesanan', 'sql' => [DDL_PESANAN]];
    } else {
        $kolom = kolomTabel($db, 'pesanan');
        $inti_lengkap = count(array_diff(PESANAN_INTI, array_keys($kolom))) === 0;

        // Kolom "asing" yang wajib diisi tanpa nilai bawaan akan membuat INSERT gagal
        $diharapkan = array_merge(PESANAN_INTI, array_keys(PESANAN_OPSIONAL));
        $penghalang = [];
        foreach ($kolom as $nama => $info) {
            if (in_array($nama, $diharapkan, true)) {
                continue;
            }
            $auto = stripos((string) $info['EXTRA'], 'auto_increment') !== false;
            if ($info['IS_NULLABLE'] === 'NO' && $info['COLUMN_DEFAULT'] === null && !$auto) {
                $penghalang[] = $nama;
            }
        }

        if ($inti_lengkap && empty($penghalang)) {
            foreach (PESANAN_OPSIONAL as $nama => $sql) {
                if (!isset($kolom[$nama])) {
                    $rencana[] = ['judul' => "Tambah kolom pesanan.$nama (data pesanan tetap utuh)", 'sql' => [$sql]];
                }
            }
        } else {
            $alasan = !$inti_lengkap
                ? 'kolom wajib tidak lengkap (kurang: ' . implode(', ', array_diff(PESANAN_INTI, array_keys($kolom))) . ')'
                : 'ada kolom wajib-isi yang tidak dikenal aplikasi (' . implode(', ', $penghalang) . ')';
            $jumlah = (int) $db->query('SELECT COUNT(*) FROM pesanan')->fetch_row()[0];
            if ($jumlah === 0) {
                $rencana[] = ['judul' => "Tabel pesanan lama tidak cocok ($alasan) dan KOSONG: dibuat ulang", 'sql' => [
                    'DROP TABLE pesanan',
                    DDL_PESANAN,
                ]];
            } else {
                $cadangan = 'pesanan_lama_' . date('Ymd_His');
                $rencana[] = ['judul' => "Tabel pesanan lama tidak cocok ($alasan) dan berisi $jumlah baris: "
                    . "diganti nama menjadi $cadangan (data TIDAK hilang), lalu dibuat tabel pesanan baru", 'sql' => [
                    "RENAME TABLE pesanan TO $cadangan",
                    DDL_PESANAN,
                ]];
            }
        }
    }

    // --- token_ingat (cookie "Ingat saya") ---
    if (!tabelAda($db, 'token_ingat')) {
        $rencana[] = ['judul' => 'Buat tabel token_ingat', 'sql' => [DDL_TOKEN_INGAT]];
    } else {
        $kolom = kolomTabel($db, 'token_ingat');
        if (count(array_diff(['pengguna_id', 'selector', 'hash_validator', 'kedaluwarsa'], array_keys($kolom))) > 0) {
            $rencana[] = ['judul' => 'Tabel token_ingat tidak cocok: dibuat ulang (isinya hanya token login, pengguna cukup login ulang)', 'sql' => [
                'DROP TABLE token_ingat',
                DDL_TOKEN_INGAT,
            ]];
        }
    }

    return $rencana;
}

$hasil = null;
$error_rencana = null;
try {
    $rencana = susunRencana($db);
} catch (Throwable $ex) {
    error_log('setup_db gagal menyusun rencana: ' . $ex->getMessage());
    $rencana = [];
    $error_rencana = 'Gagal memeriksa database: ' . $ex->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_rencana === null) {
    wajibCsrf();
    $hasil = [];
    foreach ($rencana as $langkah) {
        try {
            foreach ($langkah['sql'] as $sql) {
                $db->query($sql);
            }
            $hasil[] = [true, $langkah['judul']];
        } catch (Throwable $ex) {
            error_log('setup_db: ' . $ex->getMessage());
            $hasil[] = [false, $langkah['judul'] . ' — GAGAL: ' . $ex->getMessage()];
            break; // jangan lanjut ke langkah berikutnya bila ada yang gagal
        }
    }
    try {
        $rencana = susunRencana($db); // hitung ulang untuk menampilkan sisa pekerjaan
    } catch (Throwable $ex) {
        $rencana = [];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Database - Menu Kantin</title>
    <link rel="icon" href="assets/images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; color: #333; }
        .container { max-width: 760px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        h1 { font-size: 22px; margin-bottom: 6px; }
        .sub { color: #666; font-size: 14px; margin-bottom: 20px; }
        ul { list-style: none; margin: 16px 0; }
        li { padding: 12px 14px; border-radius: 8px; margin-bottom: 8px; font-size: 14px; line-height: 1.5; }
        li.ok { background: #e8f5e9; color: #2e7d32; }
        li.gagal { background: #ffebee; color: #c62828; }
        li.todo { background: #fff8e1; color: #8d6e00; }
        .sudah { background: #e8f5e9; color: #2e7d32; padding: 14px; border-radius: 8px; font-weight: 600; }
        .btn { display: inline-block; padding: 12px 22px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-hijau { background: #00aa13; color: white; }
        .btn-abu { background: #f0f0f0; color: #555; margin-left: 8px; }
        .aksi { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Setup Database</h1>
            <p class="sub">Memeriksa apakah struktur database sudah sesuai dengan versi aplikasi ini.</p>

            <?php if ($error_rencana): ?>
                <ul><li class="gagal"><?= e($error_rencana) ?></li></ul>
            <?php endif; ?>

            <?php if ($hasil !== null): ?>
                <ul>
                    <?php foreach ($hasil as [$berhasil, $teks]): ?>
                        <li class="<?= $berhasil ? 'ok' : 'gagal' ?>"><?= $berhasil ? '✔ ' : '✘ ' ?><?= e($teks) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!$error_rencana && empty($rencana)): ?>
                <p class="sudah">✔ Database sudah sesuai. Tidak ada yang perlu diperbaiki.</p>
                <div class="aksi"><a href="daftar.php" class="btn btn-hijau">Ke Daftar Menu</a></div>
            <?php elseif (!$error_rencana): ?>
                <p class="sub"><?= $hasil === null ? 'Perubahan yang akan dilakukan:' : 'Masih ada yang belum selesai:' ?></p>
                <ul>
                    <?php foreach ($rencana as $langkah): ?>
                        <li class="todo">• <?= e($langkah['judul']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST" action="" class="aksi">
                    <?= inputCsrf() ?>
                    <button type="submit" class="btn btn-hijau">Terapkan Perbaikan</button>
                    <a href="daftar.php" class="btn btn-abu">Batal</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
