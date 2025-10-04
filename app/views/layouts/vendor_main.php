<?php
// app/views/layouts/vendor_main.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_login();
require_role(['vendor', 'owner', 'admin_full']); // vendor y administradores con acceso POS

// preparar enlace a punto de venta (si hay branch asignada la usamos)
$branchId = $_SESSION['branch_id'] ?? null;
$vendorSalesHref = BASE_URL . '/?url=branch/index';
if ($branchId && intval($branchId) > 0) {
    $vendorSalesHref = BASE_URL . '/?url=branch/sales&id=' . intval($branchId);
}

// Enlace del historial: USAMOS LA MISMA LIGA QUE TU BOTÓN EN branches/sales.php
// (el botón usa ?url=branch/history&id=...), por lo que replicamos eso.
if ($branchId && intval($branchId) > 0) {
    $saleHistoryHref = BASE_URL . '/?url=branch/history&id=' . intval($branchId);
} else {
    $saleHistoryHref = BASE_URL . '/?url=branch/history';
}

// detectar ruta actual para comportamiento específico (p.ej. vista branch/sales)
$currentUrl = $_GET['url'] ?? '';
$isSalesView = (strpos($currentUrl, 'branch/sales') !== false);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Punto de Venta - <?= isset($branch['name']) ? htmlspecialchars($branch['name']) : 'Vendedor' ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- App CSS (reutiliza estilos comunes) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">

  <style>
    /* Mantener consistencia con los demás layouts */
    body { background: #f5f7fb; }
    /* Si es la vista sales queremos que el contenido ocupe todo el ancho disponible.
       Por defecto mantenemos max-width para páginas normales. */
    .content-area {
      padding-top: 70px;
      max-width: <?= $isSalesView ? 'none' : '1200px' ?>;
      margin: 0 auto;
    }
    .navbar-brand { font-weight: 700; display:flex; align-items:center; gap:.6rem; }
    .nav-width-98 { width: 98%; margin-left:1%; margin-right:1%; }
    .vendor-logo { height: 28px; display:inline-block; }
    .sidebar { width: 260px; background: #fff; border-right: 1px solid rgba(0,0,0,0.04); min-height: 100vh; position: fixed; top: 70px; left: 0; padding-top: 1rem; }
    .content-area { margin-left: 0; } /* para mobile y vista general: el contenido principal ocupa todo (cuando se usan col-*) */
    @media(min-width: 992px) {
      .content-area { margin-left: 260px; padding-top: 70px; }
    }
    .sidebar .nav-link { color: #333; }
    .sidebar .nav-link.active { background: rgba(13,110,253,0.06); color: #0d6efd; border-radius: 8px; }
    /* pequeñas utilidades para consistencia visual */
    .section-title { font-weight:600; color:#6c757d; font-size:0.9rem; margin-bottom:.5rem; }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-gradient fixed-top">
    <div class="nav-width-98 d-flex align-items-center">
      <div class="d-flex align-items-center">
        <!-- Burger (mobile) -->
        <button class="btn btn-outline-light d-lg-none me-2 nav-icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Abrir menú">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path fill-rule="evenodd" d="M1.5 3a.5.5 0 0 1 .5-.5h12a.5.5 0 0 1 0 1h-12A.5.5 0 0 1 1.5 3zm0 5a.5.5 0 0 1 .5-.5h12a.5.5 0 0 1 0 1h-12a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h12a.5.5 0 0 1 0 1h-12a.5.5 0 0 1-.5-.5z"/></svg>
        </button>

        <!-- Logo (desktop) -->
        <a class="navbar-brand d-none d-lg-inline" href="<?= $vendorSalesHref ?>">
          <img src="<?= BASE_URL ?>/assets/images/Lombardi_LOGO.png" alt="Logo" class="vendor-logo img-fluid" />
        </a>
      </div>

      <!-- Logo centered mobile -->
      <a class="navbar-brand d-lg-none mx-auto" href="<?= $vendorSalesHref ?>">
        <img src="<?= BASE_URL ?>/assets/images/Lombardi_LOGO.png" alt="Logo" class="vendor-logo img-fluid" />
      </a>

      <!-- Right actions -->
      <div class="d-flex align-items-center ms-auto">
        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="me-3 text-white small d-none d-lg-block">Hola, <?= htmlspecialchars($_SESSION['user_name']) ?></div>
          <a href="<?= BASE_URL ?>/?url=auth/logout" class="btn btn-outline-light">Salir</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/?url=auth/login" class="btn btn-outline-light">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <?php if (is_logged_in()): ?>
    <!-- Sidebar (desktop) -->
    <aside class="sidebar d-none d-lg-block">
      <div class="p-3 d-flex flex-column" style="height:100%;">
        <div class="d-flex align-items-center mb-3">
          <div class="me-2">
            <div style="width:44px;height:44px;border-radius:8px;background:#e9f0ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0d6efd;">
              <?= mb_substr(htmlspecialchars($_SESSION['user_name'] ?? 'V'), 0, 1) ?>
            </div>
          </div>
          <div>
            <div class="fw-bold"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Vendedor') ?></div>
            <div class="text-muted small"><?= htmlspecialchars($_SESSION['role_name'] ?? ($_SESSION['role_slug'] ?? 'Vendedor')) ?></div>
          </div>
        </div>

        <div class="mb-3">
          <div class="section-title">Navegación</div>
          <ul class="nav flex-column gap-1 mt-2">
            <li class="nav-item">
              <a class="nav-link <?= (strpos($currentUrl,'branch/sales')!==false || strpos($currentUrl,'branch/index')!==false) ? 'active' : '' ?>" href="<?= $vendorSalesHref ?>">
                <span class="icon">🛒</span> Punto de Venta
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= (strpos($currentUrl,'branch/history')!==false || strpos($currentUrl,'sale/history')!==false) ? 'active' : '' ?>" href="<?= $saleHistoryHref ?>">
                <span class="icon">📜</span> Historial de Ventas
              </a>
            </li>
          </ul>
        </div>

        <div class="mt-auto small text-muted">
          <div class="mb-1"><strong>Consejos rápidos</strong></div>
          <ul style="padding-left:1rem;">
            <li class="mb-1"><small>Usa Punto de Venta para ventas rápidas por sucursal.</small></li>
            <li><small>Revisa el historial para consultar tickets o anulaciones.</small></li>
          </ul>
        </div>
      </div>
    </aside>

    <!-- Offcanvas (mobile) -->
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Punto de Venta</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
      </div>
      <div class="offcanvas-body p-0">
        <div class="p-3">
          <div class="d-flex align-items-center mb-3">
            <div class="me-2">
              <div style="width:44px;height:44px;border-radius:8px;background:#e9f0ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0d6efd;">
                <?= mb_substr(htmlspecialchars($_SESSION['user_name'] ?? 'V'), 0, 1) ?>
              </div>
            </div>
            <div>
              <div class="fw-bold"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Vendedor') ?></div>
              <div class="text-muted small"><?= htmlspecialchars($_SESSION['role_name'] ?? ($_SESSION['role_slug'] ?? 'Vendedor')) ?></div>
            </div>
          </div>

          <div class="mb-3">
            <div class="section-title">Navegación</div>
            <ul class="nav flex-column gap-1 mt-2">
              <li class="nav-item"><a class="nav-link" href="<?= $vendorSalesHref ?>"><span class="icon">🛒</span> Punto de Venta</a></li>
              <li class="nav-item"><a class="nav-link" href="<?= $saleHistoryHref ?>"><span class="icon">📜</span> Historial de Ventas</a></li>
            </ul>
          </div>

          <div class="small text-muted">
            <div class="mb-1"><strong>Consejos rápidos</strong></div>
            <ul style="padding-left:1rem;">
              <li class="mb-1"><small>Usa Punto de Venta para ventas rápidas por sucursal.</small></li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <main class="content-area">
      <div class="container-fluid" style="<?= $isSalesView ? 'max-width:100%; padding-left:1rem; padding-right:1rem;' : '' ?>">
        <?php $flash = get_flash(); if ($flash): ?>
          <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>
        <?= $content ?? '' ?>
      </div>
    </main>

  <?php else: ?>
    <main class="container my-5">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
      <?php endif; ?>
      <?= $content ?? '' ?>
    </main>
  <?php endif; ?>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/scanner-hid.js" defer></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js" defer></script>

  <script>
    // Exponer la ruta del historial para que otras vistas o botones (por ejemplo el card "Historial de ventas")
    // puedan utilizar exactamente la misma URL/comportamiento que los enlaces del sidebar.
    window.VENDOR_SALE_HISTORY_HREF = <?= json_encode($saleHistoryHref) ?>;

    document.addEventListener('DOMContentLoaded', function() {
      var offcanvasEl = document.getElementById('sidebarOffcanvas');
      if (offcanvasEl) {
        var bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
        offcanvasEl.addEventListener('click', function(e) {
          var target = e.target;
          var a = target.closest && target.closest('a.nav-link');
          if (a && a.getAttribute('href') && a.getAttribute('href') !== '#') {
            try { bsOffcanvas.hide(); } catch (err) {}
          }
        });
      }
    });
  </script>

</body>
</html>
