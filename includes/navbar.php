<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#"><?= APP_NAME; ?></a>
        <div class="d-flex text-white">
            <span class="me-3">
                <?= $_SESSION['nama'] ?? 'Guest'; ?> 
                (<?= $_SESSION['role'] ?? '-'; ?>)
            </span>
            <a href="<?= BASE_URL; ?>/logout.php" class="btn btn-light btn-sm">Logout</a>
        </div>
    </div>
</nav>