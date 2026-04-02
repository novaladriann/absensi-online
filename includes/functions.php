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
?>