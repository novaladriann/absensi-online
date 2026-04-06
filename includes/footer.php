<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const appLayout = document.querySelector('.app-layout');

    if (!menuToggle || !sidebar || !appLayout) return;

    function isMobile() {
        return window.innerWidth <= 991;
    }

    function applyDesktopState() {
        const savedState = localStorage.getItem('sidebar-desktop');
        if (!isMobile() && savedState === 'collapsed') {
            appLayout.classList.add('sidebar-collapsed');
        } else if (!isMobile()) {
            appLayout.classList.remove('sidebar-collapsed');
        }
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    }

    function toggleSidebar() {
        if (isMobile()) {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        } else {
            appLayout.classList.toggle('sidebar-collapsed');

            if (appLayout.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('sidebar-desktop', 'collapsed');
            } else {
                localStorage.setItem('sidebar-desktop', 'expanded');
            }
        }
    }

    applyDesktopState();

    menuToggle.addEventListener('click', toggleSidebar);

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    window.addEventListener('resize', function () {
        if (isMobile()) {
            appLayout.classList.remove('sidebar-collapsed');
        } else {
            closeMobileSidebar();
            applyDesktopState();
        }
    });
});
</script>
<?php include_once __DIR__ . '/loading.php'; ?>
</body>
</html>