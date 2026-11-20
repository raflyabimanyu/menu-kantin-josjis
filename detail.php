<?php
/**
 * detail.php
 * Halaman detail menu - pelanggan bisa lihat detail lengkap setiap menu
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'fungsi_pesanan.php';

// Wajib login untuk mengakses halaman ini
wajibLogin();

// Ambil ID menu dari parameter URL
$menu_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($menu_id <= 0) {
    setFlash('Menu tidak ditemukan.');
    header('Location: daftar.php');
    exit;
}

// Ambil data menu dari database dengan JOIN ke pengguna
$stmt = $db->prepare(
    'SELECT m.id, m.nama_menu, m.deskripsi, m.harga, m.kategori, m.gambar, m.stok, m.tersedia, 
            m.dibuat_pada, u.nama AS penulis
     FROM menu AS m
     JOIN pengguna AS u ON u.id = m.pengguna_id
     WHERE m.id = ? AND m.dihapus_pada IS NULL'
);
$stmt->bind_param('i', $menu_id);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();
$stmt->close();

// Jika menu tidak ditemukan, redirect
if (!$menu) {
    setFlash('Menu tidak ditemukan.');
    header('Location: daftar.php');
    exit;
}

$adalah_admin = $_SESSION['peran'] === 'admin';
// Bisa dipesan hanya bila ditandai tersedia DAN stok masih ada
$bisa_dipesan = $menu['tersedia'] === 'Ya' && (int) $menu['stok'] > 0;
$flash = ambilFlash();


$label_kategori = ['Makanan' => 'Makanan', 'Minum' => 'Minuman', 'Camilan' => 'Camilan', 'Dessert' => 'Dessert', 'Lainnya' => 'Lainnya'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($menu['nama_menu']) ?> - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <link rel="stylesheet" href="detail.css?v=<?= (int) @filemtime(__DIR__ . '/detail.css') ?>">
    <link rel="stylesheet" href="keranjang.css?v=<?= (int) @filemtime(__DIR__ . '/keranjang.css') ?>">
</head>
<body>
    <?php $nb_aktif = 'beranda'; include __DIR__ . '/komponen_navbar.php'; ?>

    <main class="dt-wrap">
        <a href="daftar.php" class="dt-kembali">&lsaquo; Kembali ke daftar menu</a>

        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>

        <article class="dt-panel">
            <div class="dt-foto">
                <img src="<?= e(ambilGambarMenu($menu['id'], $menu['kategori'], $menu['gambar'] ?? null)) ?>"
                     alt="<?= e($menu['nama_menu']) ?>"
                     onerror="this.onerror=null;this.src='images/nasi_goreng.png';">
                <span class="dt-status <?= $bisa_dipesan ? '' : 'habis' ?>"><?= $bisa_dipesan ? 'Tersedia' : 'Habis' ?></span>
            </div>

            <div class="dt-info">
                <p class="dt-kat"><?= e($label_kategori[$menu['kategori']] ?? $menu['kategori']) ?></p>
                <h1 class="dt-nama"><?= e($menu['nama_menu']) ?></h1>
                <p class="dt-harga"><?= formatRupiah($menu['harga']) ?></p>
                <p class="dt-desk"><?= nl2br(e($menu['deskripsi'])) ?></p>

                <?php if ($adalah_admin): ?>
                    <div class="dt-aksi">
                        <a href="edit.php?id=<?= (int) $menu['id'] ?>" class="btn-3d">Edit menu</a>
                        <form method="POST" action="hapus_menu.php" style="margin:0;"
                              onsubmit="return confirm('Hapus menu ini dari daftar? Pesanan yang sudah ada tetap tersimpan.');">
                            <?= inputCsrf() ?>
                            <input type="hidden" name="id" value="<?= (int) $menu['id'] ?>">
                            <button type="submit" class="dt-hapus">Hapus menu</button>
                        </form>
                    </div>
                <?php elseif ($bisa_dipesan): ?>
                    <div class="dt-pesan mk-bar">
                        <span>Jumlah pesanan</span>
                        <?= htmlKontrolJumlah((int) $menu['id'], $menu['nama_menu']) ?>
                    </div>
                    <p class="dt-hint">Jumlah ini masuk ke keranjang di pojok kanan bawah.</p>
                <?php else: ?>
                    <p class="dt-habis">Maaf, menu ini sedang tidak bisa dipesan.</p>
                <?php endif; ?>

                <dl class="dt-meta">
                    <div><dt>Ditambahkan oleh</dt><dd><?= e($menu['penulis']) ?></dd></div>
                    <div><dt>Tanggal</dt><dd><?= date('d/m/Y', strtotime($menu['dibuat_pada'])) ?></dd></div>
                    <?php if ($adalah_admin): ?>
                        <?php /* Stok HANYA dirender untuk admin */ ?>
                        <div><dt>Stok</dt><dd><?= (int) $menu['stok'] ?> porsi</dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </article>
    </main>

    <?php include __DIR__ . '/keranjang_ui.php'; ?>
</body>
</html>
