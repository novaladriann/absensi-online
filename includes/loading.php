<?php
// ============================================================
//  includes/loading.php
//  Global loading overlay — include SATU KALI di layout utama
//  Letakkan di dalam includes/header.php atau layout bersama,
//  tepat sebelum tag </body> atau setelah <body>
// ============================================================
?>

<!-- ===== LOADING OVERLAY ===== -->
<div id="loadingOverlay" aria-hidden="true">
  <div class="ld-box">
    <div class="ld-spinner">
      <div class="ld-ring"></div>
      <div class="ld-ring ld-ring-2"></div>
    </div>
    <div class="ld-text" id="loadingText">Memuat...</div>
  </div>
</div>

<style>
/* Overlay fullscreen */
#loadingOverlay {
  display: none;              /* hidden by default */
  position: fixed;
  inset: 0;
  background: rgba(15, 14, 40, 0.55);
  backdrop-filter: blur(3px);
  -webkit-backdrop-filter: blur(3px);
  z-index: 99999;
  align-items: center;
  justify-content: center;
  animation: ldFadeIn 0.18s ease;
}

#loadingOverlay.active {
  display: flex;
}

@keyframes ldFadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* Box tengah */
.ld-box {
  background: #ffffff;
  border-radius: 20px;
  padding: 32px 40px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 18px;
  min-width: 160px;
  box-shadow: 0 24px 60px rgba(0,0,0,0.18);
  animation: ldSlideUp 0.2s ease;
}

@keyframes ldSlideUp {
  from { transform: translateY(12px); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}

/* Spinner dua cincin */
.ld-spinner {
  position: relative;
  width: 48px;
  height: 48px;
}

.ld-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 3.5px solid transparent;
  border-top-color: #5b4cf0;
  animation: ldSpin 0.9s linear infinite;
}

.ld-ring-2 {
  inset: 7px;
  border-top-color: #a89af8;
  animation-duration: 0.65s;
  animation-direction: reverse;
}

@keyframes ldSpin {
  to { transform: rotate(360deg); }
}

/* Teks loading */
.ld-text {
  font-size: 14px;
  font-weight: 600;
  color: #3d3a6e;
  letter-spacing: 0.3px;
  font-family: "Segoe UI", sans-serif;
}
</style>

<script src="<?= BASE_URL ?>/assets/js/loading.js"></script>