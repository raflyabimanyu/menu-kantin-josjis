<?php
/**
 * login.php
 * Halaman login/masuk untuk pengguna terdaftar
 */

require_once 'koneksi.php';
require_once 'sesi.php';

// Jika sudah login, redirect ke daftar menu
if (sudahLogin()) {
    header('Location: daftar.php');
    exit;
}

$error = '';

// Proses POST - verifikasi login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajibCsrf('login.php', 'Kembali ke halaman login');

    $username = strtolower(trim($_POST['username'] ?? ''));
    $sandi = $_POST['sandi'] ?? '';
    
    if (!empty($username) && !empty($sandi)) {
        // Ambil data pengguna dari database dengan prepared statement
        $stmt = $db->prepare(
            'SELECT * FROM pengguna WHERE username = ?'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $pengguna = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Verifikasi: pesan error SAMA untuk akun tidak ada atau sandi salah
        // Ini mencegah penyerang mengetahui username mana yang terdaftar
        if ($pengguna === null || !password_verify($sandi, $pengguna['hash_sandi'])) {
            $error = 'Username atau sandi salah.';
        } else {
            // Login berhasil: mulai session baru (regenerate ID untuk mencegah session fixation)
            mulaiSesiPengguna((int) $pengguna['id'], $username, $pengguna['nama'], $pengguna['peran'], $pengguna['foto_profil'] ?? null);

            // Jika "Ingat saya" dicentang, buat cookie login jangka panjang (30 hari)
            if (!empty($_POST['ingat'])) {
                ingatPengguna($db, (int) $pengguna['id']);
            }
            
            // Redirect ke halaman daftar menu (pola PRG)
            header('Location: daftar.php');
            exit;
        }
    } else {
        $error = 'Username dan sandi wajib diisi.';
    }
}

// Ambil pesan flash jika ada (dari registrasi berhasil, dll)
$flash = ambilFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Menu Kantin</title>
    <link rel="icon" href="images/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #ffffff 0%, #3fd8c4 90%);
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
            color: #363636;
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
            border-color: #000000;
        }
        .ingat label { display: flex; align-items: center; gap: 8px; font-weight: 400; font-size: 14px; color: #555; cursor: pointer; }
        .ingat input[type="checkbox"] { width: auto; margin: 0; }
        .error {
            background: #ffebee;
            color: #d32f2f;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        .flash {
            background: #2a9d72;
            color: white;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #2a9d72 0%, #3fd8c4 100%);
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
            color: #2a9d72;
            text-decoration: none;
            font-weight: 500;
        }
        .link-box a:hover {
            text-decoration: underline;
        }
        .demo-accounts {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 13px;
        }
        .demo-accounts h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .demo-accounts p {
            margin: 5px 0;
            color: #666;
        }
        .demo-accounts code {
            background: #e0e0e0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
    <link rel="stylesheet" href="tema.css?v=<?= (int) @filemtime(__DIR__ . '/tema.css') ?>">
</head>
<body class="auth">
    <aside class="auth-hero">
        <img src="images/logo.png" alt="Logo Menu Kantin" class="auth-logo">
        <div class="auth-tile t1"><img src="images/nasi_goreng.png" alt=""></div>
        <div class="auth-tile t2"><img src="images/teh_tarik.png" alt=""></div>
        <div>
            <h2>Tuntaskan rasa laparmu.</h2>
            <p>Pilih menu favorit, pesan dari mana saja, lalu ambil saat sudah siap.</p>
        </div>
    </aside>
    <div class="container">
        <h1>Ayo mulai</h1>
        <p class="subtitle">Masuk ke akun Anda untuk melanjutkan</p>
        
        <?php if ($flash): ?>
            <div class="flash"><?= e($flash) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?= inputCsrf() ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="Masukkan username"
                    required
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label for="sandi">Sandi</label>
                <input 
                    type="password" 
                    id="sandi" 
                    name="sandi" 
                    placeholder="Masukkan sandi"
                    required
                >
            </div>
            
            <div class="form-group ingat">
                <label for="ingat">
                    <input type="checkbox" id="ingat" name="ingat" value="1">
                    Ingat saya selama 30 hari
                </label>
            </div>

            <button type="submit" class="btn">Masuk</button>
        </form>
        
        <div class="link-box">
            Belum punya akun? <a href="registrasi.php">Daftar di sini</a>
        </div>
        
        <div class="demo-accounts">
            <h3> Akun Demo untuk Testing:</h3>
            <p><strong>Admin:</strong> <code>admin</code> / <code>admin123</code></p>
            <p><strong>Anggota:</strong> <code>rina</code> / <code>rina123</code></p>
        </div>
    </div>
</body>
</html>
