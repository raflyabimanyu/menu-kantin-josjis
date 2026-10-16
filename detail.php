<?php
/**
 * detail.php
 * Halaman detail menu - pelanggan bisa lihat detail lengkap setiap menu
 */

require_once 'koneksi.php';
require_once 'sesi.php';

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
    'SELECT m.id, m.nama_menu, m.deskripsi, m.harga, m.kategori, m.gambar, m.tersedia, 
            m.dibuat_pada, u.nama AS penulis
     FROM menu AS m
     JOIN pengguna AS u ON u.id = m.pengguna_id
     WHERE m.id = ?'
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

// Format harga ke format Rupiah
function formatRupiah($harga) {
    return 'Rp ' . number_format($harga, 0, ',', '.');
}

// Fungsi untuk mapping ID menu ke file gambar
function ambilGambarMenu($menu_id, $kategori, $gambar_db = null) {
    // 1) Jika admin upload gambar, gunakan itu (folder images/)
    if (!empty($gambar_db)) {
        return 'images/' . $gambar_db;
    }
    
    // 2) Mapping default per ID menu
    $gambar_map = [
        1 => 'nasi_goreng.png',
        2 => 'teh_tarik.png',
        3 => 'martabak.png',
        4 => 'es_krim.png',
        5 => 'mie_ayam.png',
        6 => 'es_teh.png',
        7 => 'soto_ayam.png',
        8 => 'jus_alpukat.png'
    ];
    
    // Jika gambar spesifik untuk menu ID ada, gunakan
    if (isset($gambar_map[$menu_id])) {
        return 'images/' . $gambar_map[$menu_id];
    }
    
    // 3) Fallback: gunakan kategori
    $kategori_map = [
        'Makanan' => 'nasi_goreng.png',
        'Minum' => 'teh_tarik.png',
        'Camilan' => 'martabak.png',
        'Dessert' => 'es_krim.png',
        'Lainnya' => 'nasi_goreng.png'
    ];
    
    return 'images/' . ($kategori_map[$kategori] ?? 'nasi_goreng.png');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($menu['nama_menu']) ?> - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #51c05e 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .navbar-brand {
            font-size: 20px;
            font-weight: 600;
            text-decoration: none;
            color: #00aa13;
        }
        .btn-back {
            padding: 8px 16px;
            background: #00aa13;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: #008f10;
            transform: translateY(-2px);
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .detail-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .detail-header {
            background: linear-gradient(135deg, #00aa13 0%, #7cdd87 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        .illustration-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            padding: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .illustration-container svg {
            width: 100%;
            height: 100%;
        }
        .menu-title {
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .menu-price {
            border: solid;
            border-color: white;
            font-size: 28px;
            font-weight: 700;
            color: #FFD700;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        .detail-body {
            padding: 40px 30px;
        }
        .status-badge {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-tersedia {
            background: linear-gradient(135deg, #00aa13 0%, #00cc17 100%);
            color: white;
        }
        .status-tidak-tersedia {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
            color: white;
        }
        .info-section {
            margin-bottom: 30px;
        }
        .info-label {
            font-weight: 700;
            color: #00aa13;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-content {
            color: #333;
            font-size: 16px;
            line-height: 1.8;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #00aa13;
        }
        .category-badge {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1976d2;
            border-radius: 20px;
            font-weight: 600;
            font-size: 16px;
        }
        .meta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }
        .meta-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .meta-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .meta-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        .icon {
            font-size: 20px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="daftar.php" class="navbar-brand"><img src="images/logo.png" alt="Logo Menu Kantin" style="height:40px;"></a>
        <a href="daftar.php" class="btn-back">← Kembali ke Daftar</a>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="detail-card">
            <!-- Header with Illustration -->
            <div class="detail-header">
                <h1 class="menu-title"><?= e($menu['nama_menu']) ?></h1>
                <div class="menu-price"><?= formatRupiah($menu['harga']) ?></div>
            </div>

            <!-- Body -->
            <div class="detail-body">
                <!-- Status Badge -->
                <div style="text-align: center;">
                    <span class="status-badge <?= $menu['tersedia'] === 'Ya' ? 'status-tersedia' : 'status-tidak-tersedia' ?>">
                        <?= $menu['tersedia'] === 'Ya' ? '✓ Tersedia' : '✕ Tidak Tersedia' ?>
                    </span>
                </div>

                <!-- Kategori -->
                <div class="info-section">
                    <div class="info-label">
                        <span class="icon"></span>
                        Kategori
                    </div>
                    <div style="margin-top: 10px;">
                        <span class="category-badge"><?= e($menu['kategori']) ?></span>
                    </div>
                </div>

                <!-- Deskripsi -->
                <div class="info-section">
                    <div class="info-label">
                        <span class="icon"></span>
                        Deskripsi Menu
                    </div>
                    <div class="info-content">
                        <?= nl2br(e($menu['deskripsi'])) ?>
                    </div>
                </div>

                <!-- Meta Information -->
                <div class="meta-info">
                    <div class="meta-item">
                        <div class="meta-label"> Ditambahkan Oleh</div>
                        <div class="meta-value"><?= e($menu['penulis']) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Tanggal</div>
                        <div class="meta-value"><?= date('d/m/Y', strtotime($menu['dibuat_pada'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
