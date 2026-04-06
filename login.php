<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/captcha.php'; // ← tambahan

if (is_login()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
$mode  = $_POST['mode'] ?? 'staff';

// Identifier untuk rate-limit tracking (username / nisn / IP)
$identifier = trim($_POST['username'] ?? $_POST['nisn'] ?? get_client_ip());

// Hitung percobaan gagal saat ini
$attempt_count  = count_login_attempts($conn, $identifier);
$is_locked      = $attempt_count >= LOGIN_MAX_ATTEMPTS;
$show_captcha   = $attempt_count >= 3; // tampilkan CAPTCHA setelah 3x gagal

// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
// ============================================================

    $password = trim($_POST['password'] ?? '');

    // --- Cek lockout ---
    if ($is_locked) {
        $error = 'Terlalu banyak percobaan gagal. Silakan coba lagi dalam 15 menit.';

    // --- Verifikasi reCAPTCHA jika sudah melewati threshold ---
    } elseif ($show_captcha) {
        $token = trim($_POST['g-recaptcha-response'] ?? '');
        if (!verify_recaptcha($token, 'login')) {
            $error = 'Verifikasi CAPTCHA gagal. Silakan coba lagi.';
        }
    }

    // --- Proses login hanya jika tidak ada error CAPTCHA/lockout ---
    if ($error === '') {

        if ($mode === 'siswa') {
            $nisn = trim($_POST['nisn'] ?? '');

            if ($nisn === '' || $password === '') {
                $error = 'NISN dan password wajib diisi.';
            } else {
                $stmt = $conn->prepare("
                    SELECT
                        u.id AS user_id,
                        u.nama,
                        u.username,
                        u.password,
                        u.role,
                        u.status_akun,
                        s.id AS siswa_id,
                        s.nis,
                        s.nisn,
                        s.kelas_id,
                        s.status_siswa
                    FROM users u
                    INNER JOIN siswa s ON s.user_id = u.id
                    WHERE s.nisn = ?
                      AND u.role = 'siswa'
                      AND u.status_akun = 'aktif'
                      AND s.status_siswa = 'aktif'
                    LIMIT 1
                ");
                $stmt->bind_param('s', $nisn);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password'])) {
                        // ✅ Login berhasil — bersihkan catatan gagal
                        clear_login_attempts($conn, $nisn);

                        $_SESSION['user_id']  = $user['user_id'];
                        $_SESSION['siswa_id'] = $user['siswa_id'];
                        $_SESSION['nama']     = $user['nama'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role']     = strtolower(trim($user['role']));
                        $_SESSION['nisn']     = $user['nisn'];
                        $_SESSION['kelas_id'] = $user['kelas_id'];

                        redirect(BASE_URL . '/index.php');
                    } else {
                        // ❌ Password salah — catat percobaan
                        record_failed_attempt($conn, $nisn);
                        $attempt_count++;
                        $show_captcha = $attempt_count >= 3;
                        $is_locked    = $attempt_count >= LOGIN_MAX_ATTEMPTS;
                        $error        = $is_locked
                            ? 'Terlalu banyak percobaan gagal. Silakan coba lagi dalam 15 menit.'
                            : 'NISN atau password salah.';
                    }
                } else {
                    record_failed_attempt($conn, $nisn);
                    $attempt_count++;
                    $show_captcha = $attempt_count >= 3;
                    $error        = 'NISN siswa tidak ditemukan atau akun tidak aktif.';
                }

                $stmt->close();
            }

        } else {
            $username = trim($_POST['username'] ?? '');

            if ($username === '' || $password === '') {
                $error = 'Username dan password wajib diisi.';
            } else {
                $stmt = $conn->prepare("
                    SELECT
                        u.id AS user_id,
                        u.nama,
                        u.username,
                        u.password,
                        u.role,
                        u.status_akun,
                        g.id AS guru_id,
                        g.nip
                    FROM users u
                    LEFT JOIN guru g ON g.user_id = u.id
                    WHERE u.username = ?
                      AND u.role IN ('guru', 'admin')
                      AND u.status_akun = 'aktif'
                    LIMIT 1
                ");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password'])) {
                        // ✅ Login berhasil
                        clear_login_attempts($conn, $username);

                        $_SESSION['user_id']  = $user['user_id'];
                        $_SESSION['guru_id']  = $user['guru_id'] ?? null;
                        $_SESSION['nama']     = $user['nama'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role']     = strtolower(trim($user['role']));
                        $_SESSION['nip']      = $user['nip'] ?? null;

                        redirect(BASE_URL . '/index.php');
                    } else {
                        // ❌ Password salah
                        record_failed_attempt($conn, $username);
                        $attempt_count++;
                        $show_captcha = $attempt_count >= 3;
                        $is_locked    = $attempt_count >= LOGIN_MAX_ATTEMPTS;
                        $error        = $is_locked
                            ? 'Terlalu banyak percobaan gagal. Silakan coba lagi dalam 15 menit.'
                            : 'Username atau password salah.';
                    }
                } else {
                    record_failed_attempt($conn, $username);
                    $attempt_count++;
                    $show_captcha = $attempt_count >= 3;
                    $error        = 'Username tidak ditemukan atau akun tidak aktif.';
                }

                $stmt->close();
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php if ($show_captcha): ?>
<!-- reCAPTCHA v3 — load hanya saat dibutuhkan -->
<script src="https://www.google.com/recaptcha/api.js?render=<?= RECAPTCHA_SITE_KEY ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('loginForm');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        grecaptcha.ready(function () {
            grecaptcha.execute('<?= RECAPTCHA_SITE_KEY ?>', { action: 'login' })
                .then(function (token) {
                    document.getElementById('g-recaptcha-response').value = token;
                    form.submit();
                });
        });
    });
});
</script>
<?php endif; ?>

<div class="login-wrap">
    <div class="login-card">
        <div class="login-left">
            <div>
                <div class="login-school">
                    <i class="fa-solid fa-school"></i> SMA 12 CIREBON
                </div>
            </div>

            <div>
                <div class="login-big">Sistem<br>Absensi<br>Digital</div>
                <p class="login-desc">
                    Platform manajemen kehadiran siswa yang terintegrasi, real-time, dan mudah digunakan.
                </p>
            </div>

            <div class="login-badges">
                <div class="login-badge">v1.0.0</div>
                <div class="login-badge">Secure Login</div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-title">Selamat Datang Kembali</div>
            <div class="login-sub">Silakan masuk ke akun Anda</div>

            <div class="login-tabs">
                <button type="button"
                    class="login-tab <?= $mode === 'siswa' ? 'active' : '' ?>"
                    onclick="setMode('siswa')">Siswa</button>
                <button type="button"
                    class="login-tab <?= $mode === 'staff' ? 'active' : '' ?>"
                    onclick="setMode('staff')">Guru / Admin</button>
            </div>

            <?php if ($is_locked): ?>
                <!-- Lockout box — tanpa duplikasi pesan error -->
                <div class="login-lockout-box">
                    <div class="login-lockout-icon">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div class="login-lockout-title">Akun Sementara Dikunci</div>
                    <div class="login-lockout-sub">
                        Terlalu banyak percobaan login gagal.<br>
                        Demi keamanan akun, login dinonaktifkan sementara.
                    </div>
                    <div class="login-lockout-timer">
                        <i class="fa-regular fa-clock"></i>
                        Coba lagi dalam 15 menit
                    </div>
                </div>
            <?php else: ?>

            <?php if ($error !== ''): ?>
                <div class="alert-theme">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" autocomplete="off">
                <input type="hidden" name="mode"               id="mode"               value="<?= htmlspecialchars($mode) ?>">
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response" value="">

                <div id="formSiswa" style="<?= $mode === 'siswa' ? '' : 'display:none;' ?>">
                    <div class="form-label">NISN SISWA</div>
                    <div class="input-icon">
                        <i class="fa-regular fa-id-card"></i>
                        <input type="text" name="nisn" placeholder="Masukkan NISN"
                               value="<?= htmlspecialchars($_POST['nisn'] ?? '') ?>">
                    </div>
                </div>

                <div id="formStaff" style="<?= $mode === 'staff' ? '' : 'display:none;' ?>">
                    <div class="form-label">USERNAME</div>
                    <div class="input-icon">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" name="username" placeholder="Masukkan username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-label">PASSWORD</div>
                <div class="input-icon">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Masukkan password">
                </div>

                <?php if ($show_captcha): ?>
                <!-- Info kecil bahwa CAPTCHA aktif — badge tidak perlu centang -->
                <div style="
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    background: #f3f4f9;
                    border: 1px solid #e0e3ef;
                    border-radius: 12px;
                    padding: 10px 14px;
                    margin-bottom: 16px;
                    font-size: 13px;
                    color: #586074;
                ">
                    <i class="fa-solid fa-shield-halved" style="color:#5b4cf0; font-size:16px;"></i>
                    <span>Proteksi aktif — verifikasi otomatis saat login</span>
                    <span style="margin-left:auto; font-size:10px; color:#9aa1b5;">reCAPTCHA v3</span>
                </div>
                <?php endif; ?>

                <button type="submit" class="login-btn" <?= $is_locked ? 'disabled' : '' ?>>
                    MASUK SEKARANG <i class="fa-solid fa-arrow-right"></i>
                </button>

                <?php if ($attempt_count > 0 && !$is_locked): ?>
                <p style="text-align:center; font-size:12px; color:#ea5455; margin-top:10px;">
                    <?= $attempt_count ?> percobaan gagal —
                    akun dikunci setelah <?= LOGIN_MAX_ATTEMPTS ?> percobaan
                </p>
                <?php endif; ?>
            </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function setMode(mode) {
    document.getElementById('mode').value = mode;
    document.getElementById('formSiswa').style.display = mode === 'siswa' ? 'block' : 'none';
    document.getElementById('formStaff').style.display = mode === 'staff' ? 'block' : 'none';
    document.querySelectorAll('.login-tab').forEach((t, i) => {
        t.classList.toggle('active', (mode === 'siswa' && i === 0) || (mode === 'staff' && i === 1));
    });
}
</script>

<?php include 'includes/footer.php'; ?>