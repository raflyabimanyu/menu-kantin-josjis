<?php
/**
 * registrasi.php
 * Halaman pendaftaran akun baru
 */

require_once 'koneksi.php';
require_once 'sesi.php';
require_once 'validasi.php';

// Jika sudah login, redirect ke daftar menu
if (sudahLogin()) {
    header('Location: daftar.php');
    exit;
}

$errors = [];
$input = [];

// Proses POST - registrasi akun baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Ambil & bersihkan masukan
    $username = strtolower(ambilPost('username'));
    $nama = bersihkanTeks(ambilPost('nama'));
    $sandi = $_POST['sandi'] ?? '';
    $input = ['username' => $username, 'nama' => $nama];
    
    // 2) Validasi sisi server
    // Username: panjang 3-30, hanya huruf kecil, angka, underscore
    if ($e = validasiPanjang('Username', $username, 3, 30)) {
        $errors['username'] = $e;
    } elseif (!preg_match('/^[a-z0-9_]+$/', $username)) {
        $errors['username'] = 'Username hanya boleh huruf kecil, angka, dan underscore (_).';
    }
    
    // Nama: panjang 3-100
    if ($e = validasiPanjang('Nama', $nama, 3, 100)) {
        $errors['nama'] = $e;
    }
    
    // Sandi: minimal 6 karakter
    if (mb_strlen($sandi) < 6) {
        $errors['sandi'] = 'Sandi minimal 6 karakter.';
    }
    
    // 3) Bila lolos validasi, simpan ke database
    if (empty($errors)) {
        // Hash sandi untuk keamanan - JANGAN simpan sandi asli
        $hash = password_hash($sandi, PASSWORD_DEFAULT);
        
        try {
            // Simpan dengan prepared statement untuk mencegah SQL injection
            $stmt = $db->prepare(
                'INSERT INTO pengguna (username, nama, hash_sandi) 
                 VALUES (?, ?, ?)'
            );
            $stmt->bind_param('sss', $username, $nama, $hash);
            $stmt->execute();
            $stmt->close();
            
            // Berhasil - set pesan flash dan redirect ke login (pola PRG)
            setFlash('Akun berhasil dibuat. Silakan masuk dengan akun Anda.');
            header('Location: login.php');
            exit;
            
        } catch (mysqli_sql_exception $ex) {
            // Tangkap error UNIQUE constraint (kode 1062)
            if ($ex->getCode() === 1062) {
                $errors['username'] = 'Username sudah dipakai. Silakan pilih username lain.';
            } else {
                // Error lain - catat ke log, tampilkan pesan umum
                error_log('Registrasi gagal: ' . $ex->getMessage());
                $errors['umum'] = 'Terjadi kesalahan. Silakan coba lagi.';
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
    <title>Registrasi - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #ffffff 0%, #00cc17 90%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #00aa13;
        }
        .error {
            color: #d32f2f;
            font-size: 13px;
            margin-top: 5px;
        }
        .flash {
            background: #00aa13;
            color: white;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #00aa13 0%, #00cc17 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .link-box {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .link-box a {
            color: #00aa13;
            text-decoration: none;
            font-weight: 500;
        }
        .link-box a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="images/logo.png" alt="Logo Menu Kantin" style="display: block; margin: 0 auto 20px auto; height: 100px;">
        <p class="subtitle">Daftar akun baru untuk mulai menggunakan aplikasi</p>
        
        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors['umum'])): ?>
            <div class="error" style="background: #ffebee; padding: 12px; border-radius: 5px; margin-bottom: 20px;">
                <?= e($errors['umum']) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= e($input['username'] ?? '') ?>"
                    placeholder="Huruf kecil, angka, underscore"
                    required
                >
                <?php if (!empty($errors['username'])): ?>
                    <div class="error"><?= e($errors['username']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input 
                    type="text" 
                    id="nama" 
                    name="nama" 
                    value="<?= e($input['nama'] ?? '') ?>"
                    placeholder="Masukkan nama lengkap Anda"
                    required
                >
                <?php if (!empty($errors['nama'])): ?>
                    <div class="error"><?= e($errors['nama']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="sandi">Sandi</label>
                <input 
                    type="password" 
                    id="sandi" 
                    name="sandi" 
                    placeholder="Minimal 6 karakter"
                    required
                >
                <?php if (!empty($errors['sandi'])): ?>
                    <div class="error"><?= e($errors['sandi']) ?></div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn">Daftar Sekarang</button>
        </form>
        
        <div class="link-box">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>
</body>
</html>
