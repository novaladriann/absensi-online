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

function ensureUploadDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function deleteIfExists(?string $filename, string $dir): void
{
    if (!$filename) {
        return;
    }

    $safeName = basename($filename);
    $fullPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $safeName;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function uploadSiswaPhoto(array $file, string $uploadDir, int $siswaId = 0): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload foto gagal.');
    }

    $maxSize = 2 * 1024 * 1024; // 2 MB
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Ukuran foto maksimal 2 MB.');
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $tmpPath = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpPath)) {
        throw new RuntimeException('File upload tidak valid.');
    }

    $mime = mime_content_type($tmpPath);
    if (!isset($allowedMime[$mime])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP.');
    }

    ensureUploadDir($uploadDir);

    $ext = $allowedMime[$mime];
    $random = bin2hex(random_bytes(6));
    $filename = 'siswa_' . ($siswaId > 0 ? $siswaId : 'new') . '_' . time() . '_' . $random . '.' . $ext;
    $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Gagal menyimpan foto ke folder upload.');
    }

    return $filename;
}

$action = trim($_POST['action'] ?? '');
if ($action === '') {
    jsonResponse(false, 'Action tidak valid.');
}

$uploadDir = dirname(__DIR__) . '/upload/siswa';
$uploadedNewPhoto = '';
$oldPhotoToDelete = null;

try {
    if ($action === 'save_siswa') {
        $siswaId = (int)($_POST['siswa_id'] ?? 0);

        $nama         = trim($_POST['nama'] ?? '');
        $username     = trim($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $password     = (string)($_POST['password'] ?? '');
        $nis          = trim($_POST['nis'] ?? '');
        $nisn         = trim($_POST['nisn'] ?? '');
        $kelasId      = (int)($_POST['kelas_id'] ?? 0);
        $jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
        $alamat       = trim($_POST['alamat'] ?? '');
        $noHpOrtu     = trim($_POST['no_hp_ortu'] ?? '');
        $kodeKartu    = trim($_POST['kode_kartu'] ?? '');
        $statusSiswa  = ($_POST['status_siswa'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
        $statusAkun   = ($_POST['status_akun'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';
        $hapusFoto    = (int)($_POST['hapus_foto'] ?? 0) === 1;

        if ($nama === '' || $username === '' || $nis === '' || $nisn === '' || $kelasId <= 0 || $kodeKartu === '') {
            jsonResponse(false, 'Nama, username, NIS, NISN, kelas, dan kode kartu wajib diisi.');
        }

        if (!in_array($jenisKelamin, ['L', 'P', ''], true)) {
            $jenisKelamin = '';
        }

        if ($siswaId <= 0 && $password === '') {
            jsonResponse(false, 'Password wajib diisi saat tambah siswa.');
        }

        /* cek kelas valid */
        $stmtKelas = $conn->prepare("SELECT id FROM kelas WHERE id = ? LIMIT 1");
        $stmtKelas->bind_param("i", $kelasId);
        $stmtKelas->execute();
        $kelas = $stmtKelas->get_result()->fetch_assoc();
        $stmtKelas->close();

        if (!$kelas) {
            jsonResponse(false, 'Kelas tidak valid.');
        }

        /* data lama jika edit */
        $userIdLama = null;
        $fotoLama = null;

        if ($siswaId > 0) {
            $stmtSiswa = $conn->prepare("SELECT user_id, foto FROM siswa WHERE id = ? LIMIT 1");
            $stmtSiswa->bind_param("i", $siswaId);
            $stmtSiswa->execute();
            $siswaLama = $stmtSiswa->get_result()->fetch_assoc();
            $stmtSiswa->close();

            if (!$siswaLama) {
                jsonResponse(false, 'Data siswa tidak ditemukan.');
            }

            $userIdLama = (int)$siswaLama['user_id'];
            $fotoLama = $siswaLama['foto'] ?? null;
        }

        /* username unik */
        if ($siswaId > 0) {
            $stmtDup = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $stmtDup->bind_param("si", $username, $userIdLama);
        } else {
            $stmtDup = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmtDup->bind_param("s", $username);
        }
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($dup) {
            jsonResponse(false, 'Username sudah digunakan.');
        }

        /* email unik jika diisi */
        if ($email !== '') {
            if ($siswaId > 0) {
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

        /* NIS unik */
        if ($siswaId > 0) {
            $stmtNisDup = $conn->prepare("SELECT id FROM siswa WHERE nis = ? AND id <> ? LIMIT 1");
            $stmtNisDup->bind_param("si", $nis, $siswaId);
        } else {
            $stmtNisDup = $conn->prepare("SELECT id FROM siswa WHERE nis = ? LIMIT 1");
            $stmtNisDup->bind_param("s", $nis);
        }
        $stmtNisDup->execute();
        $dupNis = $stmtNisDup->get_result()->fetch_assoc();
        $stmtNisDup->close();

        if ($dupNis) {
            jsonResponse(false, 'NIS sudah digunakan.');
        }

        /* NISN unik */
        if ($siswaId > 0) {
            $stmtNisnDup = $conn->prepare("SELECT id FROM siswa WHERE nisn = ? AND id <> ? LIMIT 1");
            $stmtNisnDup->bind_param("si", $nisn, $siswaId);
        } else {
            $stmtNisnDup = $conn->prepare("SELECT id FROM siswa WHERE nisn = ? LIMIT 1");
            $stmtNisnDup->bind_param("s", $nisn);
        }
        $stmtNisnDup->execute();
        $dupNisn = $stmtNisnDup->get_result()->fetch_assoc();
        $stmtNisnDup->close();

        if ($dupNisn) {
            jsonResponse(false, 'NISN sudah digunakan.');
        }

        /* kode kartu unik */
        if ($siswaId > 0) {
            $stmtKodeDup = $conn->prepare("SELECT id FROM siswa WHERE kode_kartu = ? AND id <> ? LIMIT 1");
            $stmtKodeDup->bind_param("si", $kodeKartu, $siswaId);
        } else {
            $stmtKodeDup = $conn->prepare("SELECT id FROM siswa WHERE kode_kartu = ? LIMIT 1");
            $stmtKodeDup->bind_param("s", $kodeKartu);
        }
        $stmtKodeDup->execute();
        $dupKode = $stmtKodeDup->get_result()->fetch_assoc();
        $stmtKodeDup->close();

        if ($dupKode) {
            jsonResponse(false, 'Kode kartu sudah digunakan.');
        }

        /* upload foto baru jika ada */
        if (!empty($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadedNewPhoto = uploadSiswaPhoto($_FILES['foto'], $uploadDir, $siswaId);
        }

        $conn->begin_transaction();

        if ($siswaId > 0) {
            /* update users */
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

            /* tentukan query update siswa berdasarkan kondisi foto */
            if ($uploadedNewPhoto !== '') {
                $stmtSiswaSave = $conn->prepare("
                    UPDATE siswa
                    SET nis = ?, nisn = ?, kelas_id = ?, jenis_kelamin = ?, alamat = ?, no_hp_ortu = ?, foto = ?, kode_kartu = ?, status_siswa = ?
                    WHERE id = ?
                ");
                $stmtSiswaSave->bind_param(
                    "ssissssssi",
                    $nis,
                    $nisn,
                    $kelasId,
                    $jenisKelamin,
                    $alamat,
                    $noHpOrtu,
                    $uploadedNewPhoto,
                    $kodeKartu,
                    $statusSiswa,
                    $siswaId
                );

                if ($fotoLama) {
                    $oldPhotoToDelete = $fotoLama;
                }
            } elseif ($hapusFoto) {
                $stmtSiswaSave = $conn->prepare("
                    UPDATE siswa
                    SET nis = ?, nisn = ?, kelas_id = ?, jenis_kelamin = ?, alamat = ?, no_hp_ortu = ?, foto = NULL, kode_kartu = ?, status_siswa = ?
                    WHERE id = ?
                ");
                $stmtSiswaSave->bind_param(
                    "ssisssssi",
                    $nis,
                    $nisn,
                    $kelasId,
                    $jenisKelamin,
                    $alamat,
                    $noHpOrtu,
                    $kodeKartu,
                    $statusSiswa,
                    $siswaId
                );

                if ($fotoLama) {
                    $oldPhotoToDelete = $fotoLama;
                }
            } else {
                $stmtSiswaSave = $conn->prepare("
                    UPDATE siswa
                    SET nis = ?, nisn = ?, kelas_id = ?, jenis_kelamin = ?, alamat = ?, no_hp_ortu = ?, kode_kartu = ?, status_siswa = ?
                    WHERE id = ?
                ");
                $stmtSiswaSave->bind_param(
                    "ssisssssi",
                    $nis,
                    $nisn,
                    $kelasId,
                    $jenisKelamin,
                    $alamat,
                    $noHpOrtu,
                    $kodeKartu,
                    $statusSiswa,
                    $siswaId
                );
            }

            $stmtSiswaSave->execute();
            $stmtSiswaSave->close();
        } else {
            /* insert users */
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmtUser = $conn->prepare("
                INSERT INTO users (nama, username, email, password, role, status_akun)
                VALUES (?, ?, ?, ?, 'siswa', ?)
            ");
            $stmtUser->bind_param("sssss", $nama, $username, $email, $passwordHash, $statusAkun);
            $stmtUser->execute();
            $userIdBaru = $conn->insert_id;
            $stmtUser->close();

            /* insert siswa */
            $stmtSiswaSave = $conn->prepare("
                INSERT INTO siswa (user_id, nis, nisn, kelas_id, jenis_kelamin, alamat, no_hp_ortu, foto, kode_kartu, status_siswa)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtSiswaSave->bind_param(
                "ississssss",
                $userIdBaru,
                $nis,
                $nisn,
                $kelasId,
                $jenisKelamin,
                $alamat,
                $noHpOrtu,
                $uploadedNewPhoto,
                $kodeKartu,
                $statusSiswa
            );
            $stmtSiswaSave->execute();
            $stmtSiswaSave->close();
        }

        $conn->commit();

        /* hapus foto lama setelah commit sukses */
        if ($oldPhotoToDelete) {
            deleteIfExists($oldPhotoToDelete, $uploadDir);
        }

        jsonResponse(true, 'Data siswa berhasil disimpan.');
    }

    if ($action === 'delete_siswa') {
        $siswaId = (int)($_POST['siswa_id'] ?? 0);

        if ($siswaId <= 0) {
            jsonResponse(false, 'Siswa tidak valid.');
        }

        $stmtGet = $conn->prepare("SELECT user_id, foto FROM siswa WHERE id = ? LIMIT 1");
        $stmtGet->bind_param("i", $siswaId);
        $stmtGet->execute();
        $siswa = $stmtGet->get_result()->fetch_assoc();
        $stmtGet->close();

        if (!$siswa) {
            jsonResponse(false, 'Data siswa tidak ditemukan.');
        }

        $userId = (int)$siswa['user_id'];
        $oldPhotoToDelete = $siswa['foto'] ?? null;

        $conn->begin_transaction();

        /* asumsi FK users -> siswa memakai cascade seperti schema project */
        $stmtDeleteUser = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmtDeleteUser->bind_param("i", $userId);
        $stmtDeleteUser->execute();
        $stmtDeleteUser->close();

        $conn->commit();

        if ($oldPhotoToDelete) {
            deleteIfExists($oldPhotoToDelete, $uploadDir);
        }

        jsonResponse(true, 'Data siswa berhasil dihapus.');
    }

    jsonResponse(false, 'Action tidak dikenali.');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    /* hapus file baru jika sempat terupload tapi proses gagal */
    if ($uploadedNewPhoto !== '') {
        deleteIfExists($uploadedNewPhoto, $uploadDir);
    }

    jsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage());
}