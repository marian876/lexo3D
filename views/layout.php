<?php
declare(strict_types=1);

use App\Support\Response;
use App\Support\Session;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$flashError = Session::flash('error');
$flashSuccess = Session::flash('success');
$csrf = Session::csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="color-scheme" content="light dark">
  <script>try{document.querySelector('meta[name="color-scheme"]').content=localStorage.getItem("color-scheme")??"light dark";}catch(_){}</script>
  <meta name="theme-color" content="#FF5A36">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="LEXO">
  <title><?= Response::e($title ?? 'LEXO') ?></title>
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
  <link rel="apple-touch-icon" href="/assets/img/icon-192.png">
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="stylesheet" href="/assets/css/lexo.css">
</head>
<body>
  <header class="lx-header">
    <div class="lx-container lx-header-inner">
      <a href="/" class="lx-brand">
        <span class="lx-brand-mark" aria-hidden="true">L</span>
        <span>LEXO<span style="color:#FFB800;">3D</span></span>
      </a>

      <nav class="lx-nav-desktop" aria-label="Navegación principal">
        <a href="/" class="lx-nav-link <?= $currentPath === '/' ? 'is-active' : '' ?>">
          <span aria-hidden="true">🎮</span> Jugar
        </a>
        <a href="/ranking" class="lx-nav-link <?= $currentPath === '/ranking' ? 'is-active' : '' ?>">
          <span aria-hidden="true">🏆</span> Ranking
        </a>
      </nav>

      <div class="lx-header-actions">
        <?php if (!empty($user)): ?>
          <a href="/perfil" class="lx-user-pill <?= $currentPath === '/perfil' ? 'is-active' : '' ?>" title="Mi perfil" aria-label="Mi perfil: <?= Response::e($user['display_name']) ?>">
            <span class="lx-avatar lx-avatar-sm" style="background:<?= Response::e($user['avatar_color']) ?>">
              <?php if (!empty($user['avatar_url'])): ?>
                <img src="<?= Response::e($user['avatar_url']) ?>" alt="" referrerpolicy="no-referrer">
              <?php else: ?>
                <?= Response::e(mb_strtoupper(mb_substr($user['display_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
              <?php endif; ?>
            </span>
            <span><?= Response::e($user['display_name']) ?></span>
          </a>
        <?php endif; ?>

        <a href="/reglas" class="lx-btn-icon <?= $currentPath === '/reglas' ? 'is-active' : '' ?>" aria-label="Ayuda y reglas" title="Ayuda y reglas">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </a>

        <button type="button" id="install-app" class="lx-btn lx-btn-coral lx-btn-sm" hidden>
          📲 Instalar
        </button>
        <span id="install-status" role="status" class="visually-hidden"></span>

        <button type="button" class="lx-btn-icon" command="show-modal" commandfor="settings-dialog" data-open-dialog="settings-dialog" aria-label="Configuración visual y sonido" title="Configuración">
          ⚙️
        </button>
      </div>
    </div>
  </header>

  <main class="lx-main">
    <div class="lx-container">
      <?php if ($flashError): ?>
        <div class="lx-alert lx-alert-error" role="alert">
          <span aria-hidden="true">⚠️</span>
          <span><?= Response::e($flashError) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($flashSuccess): ?>
        <div class="lx-alert lx-alert-success" role="status">
          <span aria-hidden="true">✨</span>
          <span><?= Response::e($flashSuccess) ?></span>
        </div>
      <?php endif; ?>

      <?= $content ?>
    </div>
  </main>

  <nav class="lx-bottom-nav" aria-label="Menú móvil">
    <a href="/" class="lx-bottom-item <?= $currentPath === '/' ? 'is-active' : '' ?>">
      <span aria-hidden="true">🎮</span><span>Jugar</span>
    </a>
    <a href="/ranking" class="lx-bottom-item <?= $currentPath === '/ranking' ? 'is-active' : '' ?>">
      <span aria-hidden="true">🏆</span><span>Ranking</span>
    </a>
    <a href="/reglas" class="lx-bottom-item <?= $currentPath === '/reglas' ? 'is-active' : '' ?>">
      <span aria-hidden="true">📖</span><span>Reglas</span>
    </a>
    <a href="<?= !empty($user) ? '/perfil' : '/' ?>" class="lx-bottom-item <?= $currentPath === '/perfil' ? 'is-active' : '' ?>">
      <span aria-hidden="true">👤</span><span><?= !empty($user) ? 'Perfil' : 'Entrar' ?></span>
    </a>
  </nav>

  <!-- Diálogo de Configuración (Tema + Sonido) -->
  <dialog id="settings-dialog" closedby="any" aria-labelledby="settings-title">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <h1 id="settings-title" style="margin:0;font-size:1.25rem;font-weight:900;">Preferencias</h1>
      <button type="button" class="lx-btn-icon" command="close" commandfor="settings-dialog" data-close-dialog aria-label="Cerrar configuración">✕</button>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
      <fieldset style="border:1.5px solid var(--lx-border);border-radius:16px;padding:12px 14px;margin:0;">
        <legend style="font-weight:900;font-size:0.85rem;padding:0 6px;">Apariencia</legend>
        <div style="display:flex;flex-direction:column;gap:8px;font-weight:700;font-size:0.9rem;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="lexo-color-scheme" value="light dark"> 🎮 Videojuego Arcade (predeterminado)
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="lexo-color-scheme" value="light"> ☀️ Mesa de Día (claro)
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="lexo-color-scheme" value="dark"> 🌙 Noche de Torneo (oscuro)
          </label>
        </div>
      </fieldset>

      <label class="lx-card-soft" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-weight:800;">
        <span>🔊 Efectos de sonido en partida</span>
        <input id="lexo-sound-toggle" type="checkbox" checked style="width:20px;height:20px;">
      </label>

      <button type="button" class="lx-btn lx-btn-indigo lx-btn-block" command="close" commandfor="settings-dialog" data-close-dialog autofocus>Listo</button>
    </div>
  </dialog>

  <script>
    window.__LEXO_CSRF__ = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;
    window.__LEXO_USER_ID__ = <?= json_encode($user ? (int) $user['id'] : null) ?>;
  </script>
  <script src="/assets/js/lexo.js" defer></script>
</body>
</html>
