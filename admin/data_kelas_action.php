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
    if ($action === 'save_kelas') {
        $kelasId = (int)($_POST['kelas_id'] ?? 0);
        $namaKelas = trim($_POST['nama_kelas'] ?? '');
        $waliGuruId = (int)($_POST['wali_guru_id'] ?? 0);
        $waliGuruId = $waliGuruId > 0 ? $waliGuruId : null;

        if ($namaKelas === '') {
            jsonResponse(false, 'Nama kelas wajib diisi.');
        }

        /* validasi nama unik */
        if ($kelasId > 0) {
            $stmtDup = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ? AND id <> ? LIMIT 1");
            $stmtDup->bind_param("si", $namaKelas, $kelasId);
        } else {
            $stmtDup = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ? LIMIT 1");
            $stmtDup->bind_param("s", $namaKelas);
        }
        $stmtDup->execute();
        $dup = $stmtDup->get_result()->fetch_assoc();
        $stmtDup->close();

        if ($dup) {
            jsonResponse(false, 'Nama kelas sudah digunakan.');
        }

        /* validasi wali guru */
        if ($waliGuruId !== null) {
            $stmtGuru = $conn->prepare("SELECT id FROM guru WHERE id = ? LIMIT 1");
            $stmtGuru->bind_param("i", $waliGuruId);
            $stmtGuru->execute();
            $guru = $stmtGuru->get_result()->fetch_assoc();
            $stmtGuru->close();

            if (!$guru) {
                jsonResponse(false, 'Wali kelas tidak valid.');
            }

            if ($kelasId > 0) {
                $stmtWali = $conn->prepare("
                    SELECT nama_kelas
                    FROM kelas
                    WHERE wali_guru_id = ? AND id <> ?
                    LIMIT 1
                ");
                $stmtWali->bind_param("ii", $waliGuruId, $kelasId);
            } else {
                $stmtWali = $conn->prepare("
                    SELECT nama_kelas
                    FROM kelas
                    WHERE wali_guru_id = ?
                    LIMIT 1
                ");
                $stmtWali->bind_param("i", $waliGuruId);
            }
            $stmtWali->execute();
            $waliUsed = $stmtWali->get_result()->fetch_assoc();
            $stmtWali->close();

            if ($waliUsed) {
                jsonResponse(false, 'Guru tersebut sudah menjadi wali kelas di ' . $waliUsed['nama_kelas'] . '.');
            }
        }

        $conn->begin_transaction();

        if ($kelasId > 0) {
            $stmtSave = $conn->prepare("
                UPDATE kelas
                SET nama_kelas = ?, wali_guru_id = ?
                WHERE id = ?
            ");
            $stmtSave->bind_param("sii", $namaKelas, $waliGuruId, $kelasId);
            $stmtSave->execute();
            $stmtSave->close();
        } else {
            $stmtSave = $conn->prepare("
                INSERT INTO kelas (nama_kelas, wali_guru_id)
                VALUES (?, ?)
            ");
            $stmtSave->bind_param("si", $namaKelas, $waliGuruId);
            $stmtSave->execute();
            $kelasId = $conn->insert_id;
            $stmtSave->close();
        }

        /* sinkronkan role wali di guru_kelas */
        $stmtDeleteWali = $conn->prepare("
            DELETE FROM guru_kelas
            WHERE kelas_id = ? AND role_guru_kelas = 'wali'
        ");
        $stmtDeleteWali->bind_param("i", $kelasId);
        $stmtDeleteWali->execute();
        $stmtDeleteWali->close();

        if ($waliGuruId !== null) {
            $stmtInsertWali = $conn->prepare("
                INSERT INTO guru_kelas (guru_id, kelas_id, role_guru_kelas)
                VALUES (?, ?, 'wali')
                ON DUPLICATE KEY UPDATE role_guru_kelas = 'wali'
            ");
            $stmtInsertWali->bind_param("ii", $waliGuruId, $kelasId);
            $stmtInsertWali->execute();
            $stmtInsertWali->close();
        }

        $conn->commit();
        jsonResponse(true, 'Data kelas berhasil disimpan.');
    }

    if ($action === 'save_pengajar') {
        $kelasId = (int)($_POST['kelas_id'] ?? 0);
        $guruIds = $_POST['guru_ids'] ?? [];

        if ($kelasId <= 0) {
            jsonResponse(false, 'Kelas tidak valid.');
        }

        $stmtKelas = $conn->prepare("SELECT wali_guru_id FROM kelas WHERE id = ? LIMIT 1");
        $stmtKelas->bind_param("i", $kelasId);
        $stmtKelas->execute();
        $kelas = $stmtKelas->get_result()->fetch_assoc();
        $stmtKelas->close();

        if (!$kelas) {
            jsonResponse(false, 'Data kelas tidak ditemukan.');
        }

        $waliGuruId = (int)($kelas['wali_guru_id'] ?? 0);

        $cleanGuruIds = [];
        if (is_array($guruIds)) {
            foreach ($guruIds as $gid) {
                $gid = (int)$gid;
                if ($gid > 0 && $gid !== $waliGuruId) {
                    $cleanGuruIds[] = $gid;
                }
            }
            $cleanGuruIds = array_values(array_unique($cleanGuruIds));
        }

        $conn->begin_transaction();

        $stmtDelete = $conn->prepare("
            DELETE FROM guru_kelas
            WHERE kelas_id = ? AND role_guru_kelas = 'pengajar'
        ");
        $stmtDelete->bind_param("i", $kelasId);
        $stmtDelete->execute();
        $stmtDelete->close();

        if (!empty($cleanGuruIds)) {
            $stmtCheckGuru = $conn->prepare("SELECT id FROM guru WHERE id = ? LIMIT 1");
            $stmtInsert = $conn->prepare("
                INSERT INTO guru_kelas (guru_id, kelas_id, role_guru_kelas)
                VALUES (?, ?, 'pengajar')
                ON DUPLICATE KEY UPDATE role_guru_kelas = 'pengajar'
            ");

            foreach ($cleanGuruIds as $guruId) {
                $stmtCheckGuru->bind_param("i", $guruId);
                $stmtCheckGuru->execute();
                $guru = $stmtCheckGuru->get_result()->fetch_assoc();
                if (!$guru) {
                    continue;
                }

                $stmtInsert->bind_param("ii", $guruId, $kelasId);
                $stmtInsert->execute();
            }

            $stmtCheckGuru->close();
            $stmtInsert->close();
        }

        $conn->commit();
        jsonResponse(true, 'Guru pengajar berhasil diperbarui.');
    }

    if ($action === 'delete_kelas') {
        $kelasId = (int)($_POST['kelas_id'] ?? 0);

        if ($kelasId <= 0) {
            jsonResponse(false, 'Kelas tidak valid.');
        }

        $stmtCountSiswa = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM siswa
            WHERE kelas_id = ?
        ");
        $stmtCountSiswa->bind_param("i", $kelasId);
        $stmtCountSiswa->execute();
        $totalSiswa = (int)($stmtCountSiswa->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtCountSiswa->close();

        if ($totalSiswa > 0) {
            jsonResponse(false, 'Kelas tidak bisa dihapus karena masih dipakai oleh ' . $totalSiswa . ' siswa.');
        }

        $conn->begin_transaction();

        $stmtDeleteRelasi = $conn->prepare("DELETE FROM guru_kelas WHERE kelas_id = ?");
        $stmtDeleteRelasi->bind_param("i", $kelasId);
        $stmtDeleteRelasi->execute();
        $stmtDeleteRelasi->close();

        $stmtDeleteKelas = $conn->prepare("DELETE FROM kelas WHERE id = ?");
        $stmtDeleteKelas->bind_param("i", $kelasId);
        $stmtDeleteKelas->execute();
        $stmtDeleteKelas->close();

        $conn->commit();
        jsonResponse(true, 'Kelas berhasil dihapus.');
    }

    jsonResponse(false, 'Action tidak dikenali.');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    jsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage());
}