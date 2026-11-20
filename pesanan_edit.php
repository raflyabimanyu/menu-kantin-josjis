<?php
/**
 * pesanan_edit.php
 * Mengubah jumlah / catatan pesanan (UPDATE) - hanya pemilik pesanan.
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'validasi.php';
require_once 'fungsi_pesanan.php';

wajibLogin();
wajibPeran('anggota');

$pesanan_id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ambilPost('id');

// Ambil pesanan milik pengguna ini saja (pengguna_id dari SESSION)
$stmt = $db->prepare(
    'SELECT p.id, p.jumlah, p.harga_satuan, p.catatan, m.nama_menu
     FROM pesanan AS p
     JOIN menu AS m ON m.id = p.menu_id
     WHERE p.id = ? AND p.pengguna_id = ?'
);
$uid = (int) $_SESSION['id'];
$stmt->bind_param('ii', $pesanan_id, $uid);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    setFlash('Pesanan tidak ditemukan.');
    header('Location: pesanan.php');
    exit;
}

$errors = [];
$input = [
    'jumlah'  => (string) $pesanan['jumlah'],
    'catatan' => (string) ($pesanan['catatan'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajibCsrf();

    $jumlah  = ambilPost('jumlah');
    $catatan = bersihkanTeks(ambilPost('catatan'));
    $input = ['jumlah' => $jumlah, 'catatan' => $catatan];

    if ($e = validasiRentangAngka('Jumlah', $jumlah, 1, JUMLAH_MAKS_PER_PESANAN)) {
        $errors['jumlah'] = $e;
    }
    if (mb_strlen($catatan) > 255) {
        $errors['catatan'] = 'Catatan maksimal 255 karakter.';
    }

    if (empty($errors)) {
        try {
            ubahPesanan($db, $uid, $pesanan_id, (int) $jumlah, $catatan);
            setFlash('Pesanan berhasil diperbarui.');
            header('Location: pesanan.php');
            exit;
        } catch (PesananGagal $ex) {
            $errors['umum'] = $ex->getMessage();
        } catch (Throwable $ex) {
            error_log('Ubah pesanan gagal: ' . $ex->getMessage());
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
    <title>Ubah Pesanan - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; color: #333; }
        .navbar { background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .container { max-width: 600px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .page-header { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
        .page-header p { color: #666; font-size: 14px; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        input[type="number"], textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; font-family: inherit; }
        textarea { min-height: 90px; resize: vertical; }
        input:focus, textarea:focus { outline: none; border-color: #2a9d72; }
        .error { color: #d32f2f; font-size: 13px; margin-top: 5px; }
        .error-box { background: #ffebee; color: #d32f2f; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #d32f2f; }
        .btn-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn { flex: 1; padding: 14px; border-radius: 5px; font-size: 16px; font-weight: 600; border: none; cursor: pointer; text-align: center; text-decoration: none; }
        .btn-submit { background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%); color: white; }
        .btn-cancel { background: #f5f5f5; color: #666; }
        small { color: #666; display: block; margin-top: 5px; }
    </style>
</head>
<body>
    <?php $nb_aktif = 'pesanan'; include __DIR__ . '/komponen_navbar.php'; ?>

    <div class="container">
        <div class="card">
            <div class="page-header">
                <h1>Ubah Pesanan</h1>
                <p><?= e($pesanan['nama_menu']) ?> &middot; <?= formatRupiah($pesanan['harga_satuan']) ?> per porsi</p>
            </div>

            <?php if (!empty($errors['umum'])): ?>
                <div class="error-box"><?= e($errors['umum']) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= inputCsrf() ?>
                <input type="hidden" name="id" value="<?= (int) $pesanan['id'] ?>">

                <div class="form-group">
                    <label for="jumlah">Jumlah</label>
                    <input type="number" id="jumlah" name="jumlah" min="1" max="<?= JUMLAH_MAKS_PER_PESANAN ?>"
                           value="<?= e($input['jumlah']) ?>" required>
                    <?php if (!empty($errors['jumlah'])): ?>
                        <div class="error"><?= e($errors['jumlah']) ?></div>
                    <?php endif; ?>
                    <small>Maksimal <?= JUMLAH_MAKS_PER_PESANAN ?> porsi per pesanan.</small>
                </div>

                <div class="form-group">
                    <label for="catatan">Catatan (opsional)</label>
                    <textarea id="catatan" name="catatan" maxlength="255" placeholder="Contoh: tidak pakai pedas"><?= e($input['catatan']) ?></textarea>
                    <?php if (!empty($errors['catatan'])): ?>
                        <div class="error"><?= e($errors['catatan']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-submit">Simpan Perubahan</button>
                    <a href="pesanan.php" class="btn btn-cancel">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
