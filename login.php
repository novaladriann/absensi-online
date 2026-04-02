<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (is_login()) {
    redirect(BASE_URL . '/index.php');
}

$error = '';
$mode = $_POST['mode'] ?? 'staff'; // default: guru/admin

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');

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
            $stmt->bind_param("s", $nisn);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['siswa_id']  = $user['siswa_id'];
                    $_SESSION['nama']      = $user['nama'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role']      = strtolower(trim($user['role']));
                    $_SESSION['nisn']      = $user['nisn'];
                    $_SESSION['kelas_id']  = $user['kelas_id'];

                    redirect(BASE_URL . '/index.php');
                } else {
                    $error = 'Password siswa salah.';
                }
            } else {
                $error = 'NISN siswa tidak ditemukan atau akun tidak aktif.';
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
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id']  = $user['user_id'];
                    $_SESSION['guru_id']  = $user['guru_id'] ?? null;
                    $_SESSION['nama']     = $user['nama'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = strtolower(trim($user['role']));
                    $_SESSION['nip']      = $user['nip'] ?? null;

                    redirect(BASE_URL . '/index.php');
                } else {
                    $error = 'Password guru/admin salah.';
                }
            } else {
                $error = 'Username guru/admin tidak ditemukan atau akun tidak aktif.';
            }

            $stmt->close();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

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
                <div class="login-badge">v2.0.1</div>
                <div class="login-badge">Secure Login</div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-title">Selamat Datang Kembali</div>
            <div class="login-sub">Silakan masuk ke akun Anda</div>

            <div class="login-tabs">
                <button
                    type="button"
                    class="login-tab <?= $mode === 'siswa' ? 'active' : ''; ?>"
                    onclick="setMode('siswa')">
                    Siswa
                </button>

                <button
                    type="button"
                    class="login-tab <?= $mode === 'staff' ? 'active' : ''; ?>"
                    onclick="setMode('staff')">
                    Guru / Admin
                </button>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert-theme"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm" autocomplete="off">
                <input type="hidden" name="mode" id="mode" value="<?= htmlspecialchars($mode); ?>">

                <div id="formSiswa" style="<?= $mode === 'siswa' ? '' : 'display:none;'; ?>">
                    <div class="form-label">NISN SISWA</div>
                    <div class="input-icon">
                        <i class="fa-regular fa-id-card"></i>
                        <input
                            type="text"
                            name="nisn"
                            placeholder="Masukkan NISN"
                            value="<?= htmlspecialchars($_POST['nisn'] ?? ''); ?>">
                    </div>
                </div>

                <div id="formStaff" style="<?= $mode === 'staff' ? '' : 'display:none;'; ?>">
                    <div class="form-label">USERNAME</div>
                    <div class="input-icon">
                        <i class="fa-regular fa-user"></i>
                        <input
                            type="text"
                            name="username"
                            placeholder="Masukkan username"
                            value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-label">PASSWORD</div>
                <div class="input-icon">
                    <i class="fa-solid fa-lock"></i>
                    <input
                        type="password"
                        name="password"
                        placeholder="Masukkan password">
                </div>

                <button type="submit" class="login-btn">
                    MASUK SEKARANG <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function setMode(mode) {
    document.getElementById('mode').value = mode;

    const formSiswa = document.getElementById('formSiswa');
    const formStaff = document.getElementById('formStaff');
    const tabs = document.querySelectorAll('.login-tab');

    tabs.forEach(tab => tab.classList.remove('active'));

    if (mode === 'siswa') {
        formSiswa.style.display = 'block';
        formStaff.style.display = 'none';
        tabs[0].classList.add('active');
    } else {
        formSiswa.style.display = 'none';
        formStaff.style.display = 'block';
        tabs[1].classList.add('active');
    }
}
</script>

<?php include 'includes/footer.php'; ?>