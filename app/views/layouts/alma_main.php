<?php
// app/views/layouts/alma_main.php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_login();
require_role(['almacenista']); // almacenista (si quieres permitir owner/admin_full agrega sus slugs aquí)
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Almacenista - Mi Inventario</title>

  <!-- Bootstrap CSS CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Tu CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">

</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-gradient fixed-top">
    <div class="nav-width-98 d-flex align-items-center">
      <div class="d-flex align-items-center">
        <!-- Hamburguer (mobile) -->
        <button class="btn btn-outline-light d-lg-none me-2 nav-icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Abrir menú">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path fill-rule="evenodd" d="M1.5 3a.5.5 0 0 1 .5-.5h12a.5.5 0 0 1 0 1h-12A.5.5 0 0 1 1.5 3zm0 5a.5.5 0 0 1 .5-.5h12a.5.5 0 0 1 0 1h-12a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h12a.5.5 0 0 1 0 1h-12a.5.5 0 0 1-.5-.5z" />
          </svg>
        </button>

        <!-- Logo desktop -->
        <a class="navbar-brand d-none d-lg-inline" href="<?= BASE_URL ?>/?url=product/index">
          <img src="<?= BASE_URL ?>/assets/images/Lombardi_LOGO.png" alt="Lombardi" class="navbar-logo img-fluid">
        </a>
      </div>

      <!-- Logo mobile centered -->
      <a class="navbar-brand d-lg-none mx-auto" href="<?= BASE_URL ?>/?url=product/index">
        <img src="<?= BASE_URL ?>/assets/images/Lombardi_LOGO.png" alt="Lombardi" class="navbar-logo img-fluid">
      </a>

      <!-- Desktop search (estilo minimalista, elegante, sin color de fondo) -->
      <div class="d-none d-lg-flex align-items-center ms-3">
        <form class="d-flex" id="globalSearchForm" onsubmit="return false;">
          <div class="input-group align-items-center"
            style="width:360px; background:transparent; border-radius:40px; box-shadow:0 6px 18px rgba(0,0,0,0.06); overflow:hidden; border:1px solid rgba(0,0,0,0.06);">

            <!-- input -->
            <input id="globalSearch"
              class="form-control border-0"
              type="search"
              placeholder="Buscar SKU, código o nombre"
              aria-label="Buscar"
              style="background:transparent; box-shadow:none; padding-left:18px;">

            <!-- botón (solo icono blanco) -->
            <button id="searchBtn"
              class="btn btn-sm"
              type="button"
              aria-label="Buscar"
              style="margin-right:6px; margin-left:8px; border-radius:30px; background:transparent; border:1px solid rgba(0,0,0,0.06); color:rgba(0,0,0,0.7); padding:6px 10px; display:flex; align-items:center; justify-content:center;">
              <!-- lupa en botón (blanca) -->
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" fill="#ffffff">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.397l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85z" />
                <path d="M12.5 6.5a6 6 0 1 1-12 0 6 6 0 0 1 12 0z" style="opacity:0.0" />
              </svg>
            </button>
          </div>
        </form>
      </div>

      <!-- Right actions -->
      <div class="d-flex align-items-center ms-auto">
        <!-- Mobile search icon -->
        <button class="btn btn-outline-light d-lg-none me-2 nav-icon-btn" type="button" data-bs-toggle="modal" data-bs-target="#mobileSearchModal" aria-label="Buscar">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.397h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z" />
          </svg>
        </button>

        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="me-3 text-white small d-none d-lg-block">Hola, <?= htmlspecialchars($_SESSION['user_name']) ?></div>

          <a href="<?= BASE_URL ?>/?url=auth/logout" class="btn btn-outline-light d-none d-lg-inline">Salir</a>

          <!-- Mobile logout icon -->
          <a href="<?= BASE_URL ?>/?url=auth/logout" class="btn btn-outline-light nav-icon-btn d-lg-none" title="Salir" aria-label="Salir">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
              <path d="M6 2a1 1 0 0 0-1 1v2h1V3h6v10H6v-2H5v2a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H6z" />
              <path d="M.146 8.354a.5.5 0 0 1 0-.708L3.793 4 4.207 4.414 2.414 6.207H10.5a.5.5 0 0 1 0 1H2.414l1.793 1.793L3.793 12  .146 8.354z" />
            </svg>
          </a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/?url=auth/login" class="btn btn-outline-light d-none d-lg-inline">Login</a>
          <a href="<?= BASE_URL ?>/?url=auth/login" class="btn btn-outline-light nav-icon-btn d-lg-none" title="Login" aria-label="Login">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
              <path d="M6 2a1 1 0 0 0-1 1v2h1V3h6v10H6v-2H5v2a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H6z" />
              <path d="M.146 8.354a.5.5 0 0 1 0-.708L3.793 4 4.207 4.414 2.414 6.207H10.5a.5.5 0 0 1 0 1H2.414l1.793 1.793L3.793 12  .146 8.354z" />
            </svg>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <?php if (is_logged_in()): ?>
    <aside class="sidebar d-none d-lg-block">
      <div class="p-3">
        <div class="d-flex align-items-center mb-3">
          <div class="me-2">
            <div style="width:44px;height:44px;border-radius:8px;background:#e9f0ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0d6efd;">
              <?= mb_substr(htmlspecialchars($_SESSION['user_name'] ?? 'U'), 0, 1) ?></div>
          </div>
          <div>
            <div class="fw-bold"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></div>
            <div class="text-muted small"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Almacenista') ?></div>
          </div>
        </div>

        <div class="mb-3">
          <div class="section-title">Navegación</div>
          <ul class="nav flex-column gap-1">
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=product/index"><span class="icon">📦</span> Productos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=transfer/index"><span class="icon">🔁</span> Traspasos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=stockmovements/index"><span class="icon">📘</span> Movimientos</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=product/alerts"><span class="icon">⚠️</span> Alerta de stock</a></li>
          </ul>
        </div>

        <div class="small text-muted">
          <div class="mb-1"><strong>Consejos rápidos</strong></div>
          <ul style="padding-left:1rem;">
            <li class="mb-1"><small>Revisa los traspasos pendientes antes de realizar inventario.</small></li>
            <li><small>Marca como revisado cuando confirmes movimientos.</small></li>
          </ul>
        </div>
      </div>
    </aside>

    <!-- Offcanvas -->
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Mi Inventario</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
      </div>
      <div class="offcanvas-body p-0">
        <div class="p-3">
          <div class="d-flex align-items-center mb-3">
            <div class="me-2">
              <div style="width:44px;height:44px;border-radius:8px;background:#e9f0ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0d6efd;">
                <?= mb_substr(htmlspecialchars($_SESSION['user_name'] ?? 'U'), 0, 1) ?></div>
            </div>
            <div>
              <div class="fw-bold"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></div>
              <div class="text-muted small"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Almacenista') ?></div>
            </div>
          </div>

          <div class="mb-3">
            <div class="section-title">Navegación</div>
            <ul class="nav flex-column gap-1">
              <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=product/index"><span class="icon">📦</span> Productos</a></li>
              <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=transfer/index"><span class="icon">🔁</span> Traspasos</a></li>
              <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=stockmovements/index"><span class="icon">📘</span> Movimientos</a></li>
              <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/?url=product/alerts"><span class="icon">⚠️</span> Alerta de stock</a></li>
            </ul>
          </div>

          <div class="small text-muted">
            <div class="mb-1"><strong>Consejos rápidos</strong></div>
            <ul style="padding-left:1rem;">
              <li class="mb-1"><small>Revisa los traspasos pendientes antes de realizar inventario.</small></li>
              <li><small>Marca como revisado cuando confirmes movimientos.</small></li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <main class="content-area">
      <div class="container-fluid">
        <?php $flash = get_flash();
        if ($flash): ?>
          <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>
        <?= $content ?? '' ?>
      </div>
    </main>

  <?php else: ?>
    <main class="container my-5">
      <?php $flash = get_flash();
      if ($flash): ?>
        <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
      <?php endif; ?>
      <?= $content ?? '' ?>
    </main>
  <?php endif; ?>

  <!-- Mobile search modal -->
  <div class="modal fade" id="mobileSearchModal" tabindex="-1" aria-labelledby="mobileSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title" id="mobileSearchModalLabel">Buscar</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="input-group">
            <input id="mobileGlobalSearch" class="form-control modal-search-input" type="search" placeholder="SKU, código o nombre" aria-label="Buscar">
            <button id="mobileSearchBtn" class="btn btn-primary" type="button">Ir</button>
          </div>
          <div class="mt-2 small text-muted">La búsqueda usa el mismo motor que la versión de escritorio.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Global search results modal -->
  <div class="modal fade" id="globalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Resultados de búsqueda</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" id="globalModalBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/scanner-hid.js" defer></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js" defer></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var offcanvasEl = document.getElementById('sidebarOffcanvas');
      if (offcanvasEl) {
        var bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
        offcanvasEl.addEventListener('click', function(e) {
          var target = e.target;
          var a = target.closest && target.closest('a.nav-link');
          if (a && a.getAttribute('href') && a.getAttribute('href') !== '#') {
            try {
              bsOffcanvas.hide();
            } catch (err) {}
          }
        });
      }

      var mobileBtn = document.getElementById('mobileSearchBtn');
      var mobileInput = document.getElementById('mobileGlobalSearch');
      var desktopInput = document.getElementById('globalSearch');
      var desktopBtn = document.getElementById('searchBtn');

      if (mobileBtn && mobileInput && desktopInput && desktopBtn) {
        mobileBtn.addEventListener('click', function() {
          desktopInput.value = mobileInput.value;
          var mobileModalEl = document.getElementById('mobileSearchModal');
          try {
            var mModal = bootstrap.Modal.getInstance(mobileModalEl) || new bootstrap.Modal(mobileModalEl);
            mModal.hide();
          } catch (err) {}
          try {
            desktopBtn.click();
          } catch (err) {}
        });
        mobileInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            mobileBtn.click();
          }
        });
      }
    });
  </script>

</body>

</html>