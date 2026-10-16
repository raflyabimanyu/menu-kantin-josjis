<?php
/**
 * tambah.php
 * Halaman untuk menambah menu baru (HANYA admin)
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'validasi.php';

// Wajib login DAN wajib peran admin
wajibLogin();
wajibPeran('admin');

// Aturan validasi khusus Menu Kantin:
// - Harga: 1000-100000
// - Nama menu: 3-100 karakter
// - Deskripsi: maksimal 2000 karakter
// - Kategori: ENUM (Nasi, Minum, Camilan, Dessert, Lainnya)
// - Tersedia: ENUM (Ya, Tidak)
// - Khusus: Jika tersedia = Tidak, deskripsi wajib menyebut alasan (min 10 karakter)

$KATEGORI = ['Makanan', 'Minum', 'Camilan', 'Dessert', 'Lainnya'];

$errors = [];
$input = [];
$nama_file_gambar = null;

// Proses POST - tambah menu baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Ambil & bersihkan masukan
    $nama_menu = bersihkanTeks(ambilPost('nama_menu'));
    $deskripsi = bersihkanTeks(ambilPost('deskripsi'));
    $harga = ambilPost('harga');
    $kategori = ambilPost('kategori');
    $tersedia = ambilPost('tersedia');
    
    $input = [
        'nama_menu' => $nama_menu,
        'deskripsi' => $deskripsi,
        'harga' => $harga,
        'kategori' => $kategori,
        'tersedia' => $tersedia
    ];
    
    // 2) Validasi sisi server - aturan umum
    // Nama menu
    if ($e = validasiPanjang('Nama Menu', $nama_menu, 3, 100)) {
        $errors['nama_menu'] = $e;
    }
    
    // Deskripsi
    if ($e = validasiPanjang('Deskripsi', $deskripsi, 10, 2000)) {
        $errors['deskripsi'] = $e;
    }
    
    // Harga (angka dalam rentang 1000-100000)
    if ($e = validasiRentangAngka('Harga', $harga, 1000, 100000)) {
        $errors['harga'] = $e;
    }
    
    // Kategori (daftar putih)
    if ($e = validasiPilihan('Kategori', $kategori, $KATEGORI)) {
        $errors['kategori'] = $e;
    }
    
    // Tersedia (daftar putih)
    if ($e = validasiPilihan('Ketersediaan', $tersedia, ['Ya', 'Tidak'])) {
        $errors['tersedia'] = $e;
    }
    
    // 2b) Validasi & Upload GAMBAR
    if (!empty($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['gambar'];
        
        // Cek error upload
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $errors['gambar'] = 'Ukuran gambar terlalu besar (maksimal 2 MB).';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['gambar'] = 'Terjadi kesalahan saat mengupload gambar.';
        } else {
            // Validasi tipe file (hanya JPEG dan PNG)
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                $errors['gambar'] = 'Format gambar tidak didukung. Gunakan format JPG atau PNG.';
            }
            
            // Validasi ukuran (maksimal 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                $errors['gambar'] = 'Ukuran gambar terlalu besar (maksimal 2 MB).';
            }
            
            // Jika lolos validasi, pindahkan ke folder images/
            if (empty($errors['gambar'])) {
                $upload_dir = __DIR__ . '/images/';
                
                // Buat nama file unik: nama_menu_timestamp.ext
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safe_nama = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($nama_menu));
                $nama_file_gambar = $safe_nama . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $nama_file_gambar;
                
                if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                    $errors['gambar'] = 'Gagal menyimpan gambar. Pastikan folder images/ bisa ditulis.';
                    $nama_file_gambar = null;
                }
            }
        }
    }
    
    // 3) Validasi khusus Menu Kantin:
    // Jika tersedia = Tidak, deskripsi wajib menyebut alasan
    if (empty($errors) && $tersedia === 'Tidak') {
        if (mb_strlen($deskripsi) < 10) {
            $errors['deskripsi'] = 'Jika menu tidak tersedia, deskripsi wajib menyebut alasan (minimal 10 karakter).';
        }
    }
    
    // 4) Bila lolos semua validasi, simpan ke database
    if (empty($errors)) {
        // PENTING: pengguna_id diambil dari SESSION, BUKAN dari formulir
        // Ini mencegah pengguna memalsukan identitas penulis
        $pengguna_id = (int) $_SESSION['id'];
        
        $harga_int = (int) $harga;
        
        try {
            // Simpan dengan prepared statement untuk mencegah SQL injection
            $stmt = $db->prepare(
                'INSERT INTO menu 
                 (pengguna_id, nama_menu, deskripsi, harga, kategori, gambar, tersedia) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            
            // bind_param: i = integer, s = string
            $stmt->bind_param(
                'issssss', 
                $pengguna_id, 
                $nama_menu, 
                $deskripsi, 
                $harga_int, 
                $kategori,
                $nama_file_gambar,
                $tersedia
            );
            
            $stmt->execute();
            $stmt->close();
            
            // Berhasil - set pesan flash dan redirect (pola PRG)
            setFlash('Menu berhasil ditambahkan' . ($nama_file_gambar ? ' beserta gambarnya.' : '.'));
            header('Location: daftar.php');
            exit;
            
        } catch (mysqli_sql_exception $ex) {
            // Tangkap error
            error_log('Tambah menu gagal: ' . $ex->getMessage());
            $errors['umum'] = 'Terjadi kesalahan saat menyimpan. Silakan coba lagi.';
            
            // Hapus file yang sudah diupload jika database gagal
            if ($nama_file_gambar && file_exists(__DIR__ . '/images/' . $nama_file_gambar)) {
                unlink(__DIR__ . '/images/' . $nama_file_gambar);
            }
        }
    }
}

// Ambil pesan flash jika ada
$flash = ambilFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Menu - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .navbar {
            background: linear-gradient(135deg, #00aa13 0%, #00cc17 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-size: 24px;
            font-weight: 600;
            text-decoration: none;
            color: white;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .page-header h1 {
            color: #333;
            margin-bottom: 5px;
        }
        .page-header p {
            color: #666;
            font-size: 14px;
        }
        .flash {
            background: #00aa13;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        textarea {
            min-height: 150px;
            resize: vertical;
        }
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #00aa13;
        }
        .error {
            color: #d32f2f;
            font-size: 13px;
            margin-top: 5px;
        }
        .error-box {
            background: #ffebee;
            color: #d32f2f;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #d32f2f;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 14px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-submit {
            background: linear-gradient(135deg, #00aa13 0%, #00cc17 100%);
            color: white;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 170, 19, 0.3);
        }
        .btn-cancel {
            background: #f5f5f5;
            color: #666;
        }
        .btn-cancel:hover {
            background: #e0e0e0;
        }
        .info-box {
            background: #e3f2fd;
            color: #1976d2;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        /* ===== FILE UPLOAD STYLING ===== */
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
            position: relative;
        }
        .upload-area:hover {
            border-color: #00aa13;
            background: #f0fdf4;
        }
        .upload-area.has-file {
            border-color: #00aa13;
            background: #f0fdf4;
        }
        .upload-area input[type="file"] {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .upload-icon {
            font-size: 48px;
            margin-bottom: 10px;
            color: #999;
        }
        .upload-text {
            color: #666;
            font-size: 14px;
        }
        .upload-text strong {
            color: #00aa13;
        }
        .preview-container {
            margin-top: 15px;
            display: none;
        }
        .preview-image {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #00aa13;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            display: block;
            margin: 0 auto;
        }
        .file-name {
            margin-top: 8px;
            font-size: 13px;
            color: #00aa13;
            font-weight: 600;
        }
        .remove-btn {
            margin-top: 8px;
            padding: 6px 16px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }
        .remove-btn:hover {
            background: #ff3742;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <a href="daftar.php" class="navbar-brand"><img src="images/logo.png" alt="Logo Menu Kantin" style="height:40px;"></a>
        <div class="user-info" style="color: white;">
            Admin: <strong><?= e($_SESSION['nama']) ?></strong>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="card">
            <div class="page-header">
                <h1>Tambah Menu Baru</h1>
                <p>Isi formulir di bawah ini untuk menambahkan menu ke kantin</p>
            </div>

            <?php if ($flash): ?>
                <div class="flash"><?= e($flash) ?></div>
            <?php endif; ?>

            <?php if (!empty($errors['umum'])): ?>
                <div class="error-box">
                    <strong>Error:</strong> <?= e($errors['umum']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nama_menu">Nama Menu <span style="color: red;">*</span></label>
                    <input 
                        type="text" 
                        id="nama_menu" 
                        name="nama_menu" 
                        value="<?= e($input['nama_menu'] ?? '') ?>"
                        placeholder="Contoh: Nasi Goreng Spesial"
                        required
                    >
                    <?php if (!empty($errors['nama_menu'])): ?>
                        <div class="error"><?= e($errors['nama_menu']) ?></div>
                    <?php endif; ?>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Minimal 3 karakter, maksimal 100 karakter
                    </small>
                </div>

                <div class="form-group">
                    <label for="deskripsi">Deskripsi Menu <span style="color: red;">*</span></label>
                    <textarea 
                        id="deskripsi" 
                        name="deskripsi" 
                        placeholder="Jelaskan menu secara detail..."
                        required
                    ><?= e($input['deskripsi'] ?? '') ?></textarea>
                    <?php if (!empty($errors['deskripsi'])): ?>
                        <div class="error"><?= e($errors['deskripsi']) ?></div>
                    <?php endif; ?>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Minimal 10 karakter, maksimal 2000 karakter.<br>
                        <strong>Jika menu tidak tersedia, wajib sebutkan alasan minimal 10 karakter.</strong>
                    </small>
                </div>

                <div class="form-group">
                    <label for="harga">Harga (Rp) <span style="color: red;">*</span></label>
                    <input 
                        type="number" 
                        id="harga" 
                        name="harga" 
                        value="<?= e($input['harga'] ?? '') ?>"
                        placeholder="10000"
                        min="1000"
                        max="100000"
                        required
                    >
                    <?php if (!empty($errors['harga'])): ?>
                        <div class="error"><?= e($errors['harga']) ?></div>
                    <?php endif; ?>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Minimal Rp 1.000, maksimal Rp 100.000
                    </small>
                </div>

                <div class="form-group">
                    <label for="kategori">Kategori <span style="color: red;">*</span></label>
                    <select id="kategori" name="kategori" required>
                        <option value="">Pilih kategori...</option>
                        <option value="Makanan" <?= ($input['kategori'] ?? '') === 'Makanan' ? 'selected' : '' ?>>Makanan</option>
                        <option value="Minum" <?= ($input['kategori'] ?? '') === 'Minum' ? 'selected' : '' ?>>Minuman</option>
                        <option value="Camilan" <?= ($input['kategori'] ?? '') === 'Camilan' ? 'selected' : '' ?>>Camilan</option>
                        <option value="Dessert" <?= ($input['kategori'] ?? '') === 'Dessert' ? 'selected' : '' ?>>Dessert</option>
                        <option value="Lainnya" <?= ($input['kategori'] ?? '') === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                    <?php if (!empty($errors['kategori'])): ?>
                        <div class="error"><?= e($errors['kategori']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- ===== FILE UPLOAD GAMBAR ===== -->
                <div class="form-group">
                    <label>Foto Menu</label>
                    <div class="upload-area" id="uploadArea">
                        <input 
                            type="file" 
                            id="gambar" 
                            name="gambar" 
                            accept="image/jpeg,image/jpg,image/png"
                            onchange="previewImage(this)"
                        >
                        <div class="upload-icon"></div>
                        <div class="upload-text">
                            <strong>Klik atau seret</strong> foto menu ke sini<br>
                            <small>Format: JPG / PNG | Maksimal: 2 MB</small>
                        </div>
                        
                        <!-- Preview Area -->
                        <div class="preview-container" id="previewContainer">
                            <img src="" alt="Preview" class="preview-image" id="previewImg">
                            <div class="file-name" id="fileName"></div>
                            <button type="button" class="remove-btn" onclick="removeImage()">✕ Hapus Foto</button>
                        </div>
                    </div>
                    <?php if (!empty($errors['gambar'])): ?>
                        <div class="error"><?= e($errors['gambar']) ?></div>
                    <?php endif; ?>
                    <small style="color: #666; display: block; margin-top: 8px;">Opsional. Jika tidak dipilih, akan menggunakan ilustrasi default sesuai kategori.</small>
                </div>

                <div class="form-group">
                    <label for="tersedia">Ketersediaan <span style="color: red;">*</span></label>
                    <select id="tersedia" name="tersedia" required>
                        <option value="">Pilih status...</option>
                        <option value="Ya" <?= ($input['tersedia'] ?? '') === 'Ya' ? 'selected' : '' ?>>Tersedia</option>
                        <option value="Tidak" <?= ($input['tersedia'] ?? '') === 'Tidak' ? 'selected' : '' ?>>Tidak Tersedia</option>
                    </select>
                    <?php if (!empty($errors['tersedia'])): ?>
                        <div class="error"><?= e($errors['tersedia']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-submit">Simpan Menu</button>
                    <a href="daftar.php" class="btn btn-cancel">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
