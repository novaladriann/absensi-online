<?php
// ============================================================
// includes/captcha.php
// Tambahkan: require_once 'includes/captcha.php'; di login.php
// ============================================================

// -----------------------------------------------------------
// KONFIGURASI — isi dengan key dari https://www.google.com/recaptcha
// Daftarkan domain kamu, pilih reCAPTCHA v3
// -----------------------------------------------------------
define('RECAPTCHA_SITE_KEY',   '6Le62qcsAAAAABdDNqmshAOKvaTVELLw2ChfzX30');   // tampil di HTML
define('RECAPTCHA_SECRET_KEY', '6Le62qcsAAAAAFTCsB-5GvE6jOW00xALPyedT05g'); // hanya di server 
define('RECAPTCHA_MIN_SCORE',  0.5);                   // 0.0 (bot) - 1.0 (manusia)

// Batas percobaan sebelum CAPTCHA wajib diverifikasi
define('LOGIN_MAX_ATTEMPTS',   5);    // max gagal
define('LOGIN_LOCKOUT_SECONDS', 300); // 15 menit lockout

// -----------------------------------------------------------
// Ambil IP pengguna (support proxy / ngrok)
// -----------------------------------------------------------
function get_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For bisa berisi daftar IP — ambil yang pertama
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// -----------------------------------------------------------
// Hitung percobaan gagal dalam window waktu tertentu
// -----------------------------------------------------------
function count_login_attempts(mysqli $conn, string $identifier): int {
    $since = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_SECONDS);
    $stmt  = $conn->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (identifier = ? OR ip_address = ?)
           AND attempted_at >= ?"
    );
    $ip = get_client_ip();
    $stmt->bind_param('sss', $identifier, $ip, $since);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int) $count;
}

// -----------------------------------------------------------
// Catat percobaan gagal
// -----------------------------------------------------------
function record_failed_attempt(mysqli $conn, string $identifier): void {
    $ip   = get_client_ip();
    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)"
    );
    $stmt->bind_param('ss', $identifier, $ip);
    $stmt->execute();
    $stmt->close();
}

// -----------------------------------------------------------
// Hapus catatan setelah login berhasil
// -----------------------------------------------------------
function clear_login_attempts(mysqli $conn, string $identifier): void {
    $stmt = $conn->prepare(
        "DELETE FROM login_attempts WHERE identifier = ?"
    );
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $stmt->close();
}

// -----------------------------------------------------------
// Verifikasi token reCAPTCHA v3 ke server Google
// Return true  = lolos (manusia)
// Return false = gagal / skor terlalu rendah
// -----------------------------------------------------------
function verify_recaptcha(string $token, string $action = 'login'): bool {
    if (empty($token)) return false;

    $response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => get_client_ip(),
    ]));

    if ($response === false) {
        // Gagal koneksi ke Google → jangan blokir user, log saja
        error_log('[reCAPTCHA] Gagal koneksi ke Google verify API');
        return true; // fail-open: tetap izinkan agar user bisa login
    }

    $data = json_decode($response, true);

    return isset($data['success'], $data['score'], $data['action'])
        && $data['success']  === true
        && $data['action']   === $action
        && $data['score']    >= RECAPTCHA_MIN_SCORE;
}

// -----------------------------------------------------------
// Apakah user perlu diminta CAPTCHA?
// (dipanggil saat render form — untuk tampilkan/sembunyikan widget)
// -----------------------------------------------------------
function needs_captcha(mysqli $conn, string $identifier): bool {
    return count_login_attempts($conn, $identifier) >= LOGIN_MAX_ATTEMPTS;
}