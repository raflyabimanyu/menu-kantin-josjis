<?php
/**
 * validasi.php
 * Helper fungsi validasi untuk form input
 */

/**
 * Validasi panjang string
 * @param string $label - label field untuk pesan error
 * @param string $nilai - nilai yang akan divalidasi
 * @param int $min - panjang minimal
 * @param int $maks - panjang maksimal
 * @return string|null - null jika lolos, pesan error jika gagal
 */
function validasiPanjang(string $label, string $nilai, int $min, int $maks): ?string
{
    $panjang = mb_strlen($nilai);
    
    if ($panjang === 0) {
        return "$label wajib diisi.";
    }
    if ($panjang < $min) {
        return "$label minimal $min karakter.";
    }
    if ($panjang > $maks) {
        return "$label maksimal $maks karakter.";
    }
    
    return null;
}

/**
 * Validasi pilihan dari daftar putih (whitelist)
 * @param string $label - label field untuk pesan error
 * @param string $nilai - nilai yang akan divalidasi
 * @param array $daftarValid - array berisi pilihan yang valid
 * @return string|null - null jika lolos, pesan error jika gagal
 */
function validasiPilihan(string $label, string $nilai, array $daftarValid): ?string
{
    if (empty($nilai)) {
        return "$label wajib dipilih.";
    }
    
    // Cek apakah nilai ada di daftar putih
    if (!in_array($nilai, $daftarValid, true)) {
        return "$label tidak valid.";
    }
    
    return null;
}

/**
 * Validasi rentang angka
 * @param string $label - label field untuk pesan error
 * @param mixed $nilai - nilai yang akan divalidasi
 * @param int $min - nilai minimal
 * @param int $maks - nilai maksimal
 * @return string|null - null jika lolos, pesan error jika gagal
 */
function validasiRentangAngka(string $label, $nilai, int $min, int $maks): ?string
{
    // Cek apakah nilai adalah angka
    if (!is_numeric($nilai)) {
        return "$label harus berupa angka.";
    }
    
    $angka = (int) $nilai;
    
    if ($angka < $min || $angka > $maks) {
        return "$label harus antara " . number_format($min, 0, ',', '.') . 
               " dan " . number_format($maks, 0, ',', '.') . ".";
    }
    
    return null;
}

/**
 * Validasi khusus Menu Kantin:
 * Jika tersedia = Tidak, deskripsi wajib menyebut alasan (min. 10 karakter)
 * @param string $tersedia - status ketersediaan
 * @param string $deskripsi - deskripsi menu
 * @return string|null - null jika lolos, pesan error jika gagal
 */
function validasiKetersediaanDeskripsi(string $tersedia, string $deskripsi): ?string
{
    if ($tersedia === 'Tidak') {
        // Jika tidak tersedia, deskripsi harus minimal 10 karakter
        if (mb_strlen($deskripsi) < 10) {
            return "Jika menu tidak tersedia, deskripsi wajib menyebut alasan (minimal 10 karakter).";
        }
    }
    
    return null;
}
?>
