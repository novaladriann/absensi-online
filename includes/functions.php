<?php

function redirect($url)
{
    header("Location: $url");
    exit;
}

function is_login()
{
    return isset($_SESSION['user_id']);
}

function user_role()
{
    return $_SESSION['role'] ?? null;
}

function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function require_role($roles = [])
{
    if (!isset($_SESSION['role'])) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }

    $currentRole = strtolower(trim($_SESSION['role']));
    $allowedRoles = array_map(function ($role) {
        return strtolower(trim($role));
    }, $roles);

    if (!in_array($currentRole, $allowedRoles)) {
        $_SESSION['error'] = "Akses ditolak!";
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }
}

/**
 * Format tanggal ke format Indonesia.
 * Contoh output: "Jumat, 03 April 2026"
 */
function formatTanggalIndonesia(string $tanggal): string
{
    static $hariMap = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
    ];

    static $bulanMap = [
        'January'   => 'Januari',
        'February'  => 'Februari',
        'March'     => 'Maret',
        'April'     => 'April',
        'May'       => 'Mei',
        'June'      => 'Juni',
        'July'      => 'Juli',
        'August'    => 'Agustus',
        'September' => 'September',
        'October'   => 'Oktober',
        'November'  => 'November',
        'December'  => 'Desember',
    ];

    $hasil = date('l, d F Y', strtotime($tanggal));
    $hasil = strtr($hasil, $hariMap);
    $hasil = strtr($hasil, $bulanMap);

    return $hasil;
}

/**
 * Kembalikan nama hari dalam Bahasa Indonesia dari sebuah tanggal.
 * Contoh output: "Jumat"
 */
function hariIndonesia(string $tanggal): string
{
    static $map = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
    ];

    return $map[date('l', strtotime($tanggal))] ?? '';
}

/**
 * Ambil guru_id berdasarkan user_id dari session.
 * Hasil di-cache ke $_SESSION['guru_id'] agar tidak query berulang.
 *
 * @param  mysqli $conn  Koneksi database aktif.
 * @return int|null      guru_id jika ditemukan, null jika tidak.
 */
function getGuruId(mysqli $conn): ?int
{
    if (!empty($_SESSION['guru_id'])) {
        return (int) $_SESSION['guru_id'];
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId === 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id FROM guru WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $_SESSION['guru_id'] = (int) $row['id'];
        return (int) $row['id'];
    }

    return null;
}