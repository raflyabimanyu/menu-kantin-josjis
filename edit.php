<?php
/**
 * edit.php
 * Admin mengubah deskripsi menu dan MENAMBAH stok (UPDATE) - HANYA admin.
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'validasi.php';
require_once 'fungsi_pesanan.php';

wajibLogin();
wajibPeran('admin');

$menu_id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ambilPost('id');
if ($menu_id <= 0) {
    setFlash('Menu tidak ditemukan.');
    header('Location: daftar.php');
    exit;
}

function ambilMenu(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT id, nama_menu, deskripsi, harga, stok, tersedia FROM menu WHERE id = ? AND dihapus_pada IS NULL');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $menu = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $menu ?: null;
}

$menu = ambilMenu($db, $menu_id);
if (!$menu) {
    setFlash('Menu tidak ditemukan.');
    header('Location: daftar.php');
    exit;
}

$errors = [];
$input = ['deskripsi' => $menu['deskripsi'], 'aksi_stok' => 'tambah', 'jumlah_stok' => '0'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajibCsrf();

    $deskripsi   = bersihkanTeks(ambilPost('deskripsi'));
    $aksi_stok   = ambilPost('aksi_stok');
    $jumlah_stok = ambilPost('jumlah_stok');
    $input = ['deskripsi' => $deskripsi, 'aksi_stok' => $aksi_stok, 'jumlah_stok' => $jumlah_stok];

    if ($e = validasiPanjang('Deskripsi', $deskripsi, 10, 2000)) {
        $errors['deskripsi'] = $e;
    }
    if (!in_array($aksi_stok, ['tambah', 'kurang'], true)) {
        $errors['jumlah_stok'] = 'Pilih jenis penyesuaian stok.';
    } elseif ($e = validasiRentangAngka('Jumlah stok', $jumlah_stok, 0, 9999)) {
        $errors['jumlah_stok'] = $e;
    }

    if (empty($errors)) {
        $delta = (int) $jumlah_stok * ($aksi_stok === 'kurang' ? -1 : 1);
        try {
            // Deskripsi + stok diperbarui dalam SATU transaksi; stok tidak bisa kurang dari 0
            $stok_baru = sesuaikanStok($db, $menu_id, $delta, $deskripsi);

            $ket = $delta === 0 ? '.' : ($delta > 0 ? " (stok bertambah $delta, sekarang $stok_baru)." : ' (stok berkurang ' . abs($delta) . ", sekarang $stok_baru).");
            setFlash('Menu berhasil diperbarui' . $ket);
            header('Location: detail.php?id=' . $menu_id);
            exit;
        } catch (PesananGagal $ex) {
            $errors['jumlah_stok'] = $ex->getMessage();
        } catch (mysqli_sql_exception $ex) {
            error_log('Edit menu gagal: ' . $ex->getMessage());
            $errors['umum'] = 'Terjadi kesalahan saat menyimpan. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Menu - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); color: white; }
        .container { max-width: 700px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .page-header { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
        .page-header p { color: #666; font-size: 14px; margin-top: 5px; }
        .stok-box { background: #dff2e8; color: #14543c; padding: 14px 16px; border-radius: 5px; margin-bottom: 20px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        input[type="number"], textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; }
        textarea { min-height: 150px; resize: vertical; }
        input:focus, textarea:focus { outline: none; border-color: #2a9d72; }
        .error { color: #d32f2f; font-size: 13px; margin-top: 5px; }
        .error-box { background: #ffebee; color: #d32f2f; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #d32f2f; }
        small { color: #666; display: block; margin-top: 5px; }
        .stok-baris { display: flex; gap: 10px; align-items: center; }
        .stok-baris select { padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; background: #fff; }
        .stok-baris input { width: 120px; }
        .stok-hasil { font-size: 13px; color: #14543c; font-weight: 600; }
        .stok-hasil.bahaya { color: #d32f2f; }
        .btn-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn { flex: 1; padding: 14px; border-radius: 5px; font-size: 16px; font-weight: 600; border: none; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-submit { background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%); color: white; }
        .btn-cancel { background: #f5f5f5; color: #666; }
    </style>
</head>
<body>
    <?php $nb_aktif = 'beranda'; include __DIR__ . '/komponen_navbar.php'; ?>

    <div class="container">
        <div class="card">
            <div class="page-header">
                <h1>Edit Menu</h1>
                <p><?= e($menu['nama_menu']) ?> &middot; <?= formatRupiah($menu['harga']) ?></p>
            </div>

            <div class="stok-box">Stok saat ini: <strong><?= (int) $menu['stok'] ?></strong> porsi</div>

            <?php if (!empty($errors['umum'])): ?>
                <div class="error-box"><?= e($errors['umum']) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= inputCsrf() ?>
                <input type="hidden" name="id" value="<?= (int) $menu['id'] ?>">

                <div class="form-group">
                    <label for="deskripsi">Deskripsi Menu</label>
                    <textarea id="deskripsi" name="deskripsi" required><?= e($input['deskripsi']) ?></textarea>
                    <?php if (!empty($errors['deskripsi'])): ?>
                        <div class="error"><?= e($errors['deskripsi']) ?></div>
                    <?php endif; ?>
                    <small>Minimal 10 karakter, maksimal 2000 karakter.</small>
                </div>

                <div class="form-group">
                    <label for="jumlah_stok">Penyesuaian Stok</label>
                    <div class="stok-baris">
                        <select name="aksi_stok" id="aksi_stok" aria-label="Jenis penyesuaian">
                            <option value="tambah" <?= $input['aksi_stok'] === 'tambah' ? 'selected' : '' ?>>Tambah</option>
                            <option value="kurang" <?= $input['aksi_stok'] === 'kurang' ? 'selected' : '' ?>>Kurangi</option>
                        </select>
                        <input type="number" id="jumlah_stok" name="jumlah_stok" min="0" max="9999"
                               value="<?= e($input['jumlah_stok']) ?>" required>
                        <span class="stok-hasil" id="stokHasil" aria-live="polite"></span>
                    </div>
                    <?php if (!empty($errors['jumlah_stok'])): ?>
                        <div class="error"><?= e($errors['jumlah_stok']) ?></div>
                    <?php endif; ?>
                    <small>Jumlah ditambahkan/dikurangkan dari stok yang ada. Stok tidak bisa kurang dari 0. Isi 0 bila hanya mengubah deskripsi.</small>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-submit">Simpan Perubahan</button>
                    <a href="detail.php?id=<?= (int) $menu['id'] ?>" class="btn btn-cancel">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Pratinjau stok setelah penyesuaian (hanya tampilan; server tetap memvalidasi)
        (function () {
            var STOK = <?= (int) $menu['stok'] ?>;
            var aksi = document.getElementById('aksi_stok'), jml = document.getElementById('jumlah_stok'), out = document.getElementById('stokHasil');
            function hitung() {
                var n = parseInt(jml.value, 10);
                if (isNaN(n) || n < 0) { out.textContent = ''; return; }
                var hasil = STOK + (aksi.value === 'kurang' ? -n : n);
                out.classList.toggle('bahaya', hasil < 0);
                out.textContent = hasil < 0 ? 'Melebihi stok!' : '\u2192 stok menjadi ' + hasil;
            }
            aksi.addEventListener('change', hitung); jml.addEventListener('input', hitung); hitung();
        })();
    </script>
</body>
</html>
