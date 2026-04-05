<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);
header('Content-Type: application/json');

function jsonResponse(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit;
}

$action = trim($_POST['action'] ?? '');
if ($action === '') {
    jsonResponse(false, 'Action tidak valid.');
}

try {
    if ($action === 'save_guru') {
        $guruId = (int)($_POST['guru_id'] ?? 0);

        $nama = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $noHp = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $statusAkun = ($_POST['status_akun'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';

        if ($nama === '' || $username === '' || $nip === '') {
            jsonResponse(false, 'Nama, username, dan NIP wajib diisi.');
        }

        if ($guruId <= 0 && $password === '') {
            jsonResponse(false, 'Password wajib diisi saat tambah guru.');
        }

        /* ambil user_id lama jika edit */
        $userIdLama = null;
        if ($guruId > 0) {
            $stmtGuru = $conn->prepare("SELECT user_id FROM guru WHERE id = ? LIMIT 1");
            $stmtGuru->bind_param("i", $guruId);
            $stmtGuru->execute();
            $guruLama = $stmtGuru->get_result()->fetch_assoc();
            $stmtGuru->close();

            if (!$guruLama) {
                jsonResponse(false, 'Data guru tidak ditemukan.');
            }

            $userIdLama = (int)$guruLama['user_id'];
        }

        /* validasi username unik */
        if ($guruId > 0) {
            $stmtUserDup = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $stmtUserDup->bind_param("si", $username, $userIdLama);
        } else {
            $stmtUserDup = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmtUserDup->bind_param("s", $username);
        }
        $stmtUserDup->execute();
        $dupUser = $stmtUserDup->get_result()->fetch_assoc();
        $stmtUserDup->close();

        if ($dupUser) {
            jsonResponse(false, 'Username sudah digunakan.');
        }

        /* validasi email unik jika diisi */
        if ($email !== '') {
            if ($guruId > 0) {
                $stmtEmailDup = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
                $stmtEmailDup->bind_param("si", $email, $userIdLama);
            } else {
                $stmtEmailDup = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmtEmailDup->bind_param("s", $email);
            }
            $stmtEmailDup->execute();
            $dupEmail = $stmtEmailDup->get_result()->fetch_assoc();
            $stmtEmailDup->close();

            if ($dupEmail) {
                jsonResponse(false, 'Email sudah digunakan.');
            }
        } else {
            $email = null;
        }

        /* validasi NIP unik */
        if ($guruId > 0) {
            $stmtNipDup = $conn->prepare("SELECT id FROM guru WHERE nip = ? AND id <> ? LIMIT 1");
            $stmtNipDup->bind_param("si", $nip, $guruId);
        } else {
            $stmtNipDup = $conn->prepare("SELECT id FROM guru WHERE nip = ? LIMIT 1");
            $stmtNipDup->bind_param("s", $nip);
        }
        $stmtNipDup->execute();
        $dupNip = $stmtNipDup->get_result()->fetch_assoc();
        $stmtNipDup->close();

        if ($dupNip) {
            jsonResponse(false, 'NIP sudah digunakan.');
        }

        $conn->begin_transaction();

        if ($guruId > 0) {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmtUser = $conn->prepare("
                    UPDATE users
                    SET nama = ?, username = ?, email = ?, password = ?, status_akun = ?
                    WHERE id = ?
                ");
                $stmtUser->bind_param("sssssi", $nama, $username, $email, $passwordHash, $statusAkun, $userIdLama);
            } else {
                $stmtUser = $conn->prepare("
                    UPDATE users
                    SET nama = ?, username = ?, email = ?, status_akun = ?
                    WHERE id = ?
                ");
                $stmtUser->bind_param("ssssi", $nama, $username, $email, $statusAkun, $userIdLama);
            }
            $stmtUser->execute();
            $stmtUser->close();

            $stmtGuruSave = $conn->prepare("
                UPDATE guru
                SET nip = ?, no_hp = ?, alamat = ?
                WHERE id = ?
            ");
            $stmtGuruSave->bind_param("sssi", $nip, $noHp, $alamat, $guruId);
            $stmtGuruSave->execute();
            $stmtGuruSave->close();
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmtUser = $conn->prepare("
                INSERT INTO users (nama, username, email, password, role, status_akun)
                VALUES (?, ?, ?, ?, 'guru', ?)
            ");
            $stmtUser->bind_param("sssss", $nama, $username, $email, $passwordHash, $statusAkun);
            $stmtUser->execute();
            $userIdBaru = $conn->insert_id;
            $stmtUser->close();

            $stmtGuruSave = $conn->prepare("
                INSERT INTO guru (user_id, nip, no_hp, alamat)
                VALUES (?, ?, ?, ?)
            ");
            $stmtGuruSave->bind_param("isss", $userIdBaru, $nip, $noHp, $alamat);
            $stmtGuruSave->execute();
            $guruId = $conn->insert_id;
            $stmtGuruSave->close();
        }

        $conn->commit();
        jsonResponse(true, 'Data guru berhasil disimpan.');
    }

    if ($action === 'save_guru_kelas') {
        $guruId = (int)($_POST['guru_id'] ?? 0);
        $waliKelasId = (int)($_POST['wali_kelas_id'] ?? 0);
        $pengajarKelasIds = $_POST['pengajar_kelas_ids'] ?? [];

        if ($guruId <= 0) {
            jsonResponse(false, 'Guru tidak valid.');
        }

        $stmtGuru = $conn->prepare("SELECT id FROM guru WHERE id = ? LIMIT 1");
        $stmtGuru->bind_param("i", $guruId);
        $stmtGuru->execute();
        $guru = $stmtGuru->get_result()->fetch_assoc();
        $stmtGuru->close();

        if (!$guru) {
            jsonResponse(false, 'Data guru tidak ditemukan.');
        }

        $cleanPengajar = [];
        if (is_array($pengajarKelasIds)) {
            foreach ($pengajarKelasIds as $kid) {
                $kid = (int)$kid;
                if ($kid > 0 && $kid !== $waliKelasId) {
                    $cleanPengajar[] = $kid;
                }
            }
            $cleanPengajar = array_values(array_unique($cleanPengajar));
        }

        if ($waliKelasId > 0) {
            $stmtCheckWali = $conn->prepare("
                SELECT nama_kelas
                FROM kelas
                WHERE id = ? AND (wali_guru_id IS NULL OR wali_guru_id = ?)
                LIMIT 1
            ");
            $stmtCheckWali->bind_param("ii", $waliKelasId, $guruId);
            $stmtCheckWali->execute();
            $waliKelas = $stmtCheckWali->get_result()->fetch_assoc();
            $stmtCheckWali->close();

            if (!$waliKelas) {
                jsonResponse(false, 'Kelas wali tidak valid atau sudah dipakai guru lain.');
            }
        }

        $conn->begin_transaction();

        /* lepas wali lama guru ini */
        $stmtClearOldWali = $conn->prepare("
            UPDATE kelas
            SET wali_guru_id = NULL
            WHERE wali_guru_id = ?
        ");
        $stmtClearOldWali->bind_param("i", $guruId);
        $stmtClearOldWali->execute();
        $stmtClearOldWali->close();

        $stmtDeleteWaliRel = $conn->prepare("
            DELETE FROM guru_kelas
            WHERE guru_id = ? AND role_guru_kelas = 'wali'
        ");
        $stmtDeleteWaliRel->bind_param("i", $guruId);
        $stmtDeleteWaliRel->execute();
        $stmtDeleteWaliRel->close();

        if ($waliKelasId > 0) {
            $stmtSetWali = $conn->prepare("
                UPDATE kelas
                SET wali_guru_id = ?
                WHERE id = ?
            ");
            $stmtSetWali->bind_param("ii", $guruId, $waliKelasId);
            $stmtSetWali->execute();
            $stmtSetWali->close();

            $stmtInsertWali = $conn->prepare("
                INSERT INTO guru_kelas (guru_id, kelas_id, role_guru_kelas)
                VALUES (?, ?, 'wali')
                ON DUPLICATE KEY UPDATE role_guru_kelas = 'wali'
            ");
            $stmtInsertWali->bind_param("ii", $guruId, $waliKelasId);
            $stmtInsertWali->execute();
            $stmtInsertWali->close();
        }

        /* reset pengajar lama */
        $stmtDeletePengajar = $conn->prepare("
            DELETE FROM guru_kelas
            WHERE guru_id = ? AND role_guru_kelas = 'pengajar'
        ");
        $stmtDeletePengajar->bind_param("i", $guruId);
        $stmtDeletePengajar->execute();
        $stmtDeletePengajar->close();

        if (!empty($cleanPengajar)) {
            $stmtCheckKelas = $conn->prepare("SELECT id FROM kelas WHERE id = ? LIMIT 1");
            $stmtInsertPengajar = $conn->prepare("
                INSERT INTO guru_kelas (guru_id, kelas_id, role_guru_kelas)
                VALUES (?, ?, 'pengajar')
                ON DUPLICATE KEY UPDATE role_guru_kelas = 'pengajar'
            ");

            foreach ($cleanPengajar as $kelasId) {
                $stmtCheckKelas->bind_param("i", $kelasId);
                $stmtCheckKelas->execute();
                $kelas = $stmtCheckKelas->get_result()->fetch_assoc();
                if (!$kelas) {
                    continue;
                }

                $stmtInsertPengajar->bind_param("ii", $guruId, $kelasId);
                $stmtInsertPengajar->execute();
            }

            $stmtCheckKelas->close();
            $stmtInsertPengajar->close();
        }

        $conn->commit();
        jsonResponse(true, 'Relasi kelas guru berhasil diperbarui.');
    }

    if ($action === 'delete_guru') {
        $guruId = (int)($_POST['guru_id'] ?? 0);

        if ($guruId <= 0) {
            jsonResponse(false, 'Guru tidak valid.');
        }

        $stmtGet = $conn->prepare("SELECT user_id FROM guru WHERE id = ? LIMIT 1");
        $stmtGet->bind_param("i", $guruId);
        $stmtGet->execute();
        $guru = $stmtGet->get_result()->fetch_assoc();
        $stmtGet->close();

        if (!$guru) {
            jsonResponse(false, 'Data guru tidak ditemukan.');
        }

        $userId = (int)$guru['user_id'];

        $conn->begin_transaction();

        $stmtClearWali = $conn->prepare("
            UPDATE kelas
            SET wali_guru_id = NULL
            WHERE wali_guru_id = ?
        ");
        $stmtClearWali->bind_param("i", $guruId);
        $stmtClearWali->execute();
        $stmtClearWali->close();

        $stmtDeleteUser = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmtDeleteUser->bind_param("i", $userId);
        $stmtDeleteUser->execute();
        $stmtDeleteUser->close();

        $conn->commit();
        jsonResponse(true, 'Data guru berhasil dihapus.');
    }

    jsonResponse(false, 'Action tidak dikenali.');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    jsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage());
}