<?php
declare(strict_types=1);

use App\Support\Response;
use App\Support\Session;

$csrf = Session::csrfToken();
$roomCfg = $config['room'];
?>
<div class="lx-arcade-home">
  <!-- Columna Izquierda: Escena Videojuego + Logo 3D + 3 Tarjetas Glossy -->
  <section class="lx-arcade-hero">
    <div class="lx-hero-stage">
      <!-- Ilustración de personajes + trofeo -->
      <img
        src="/assets/img/hero-characters.jpg"
        alt="Jugadores celebrando con el trofeo de LEXO"
        class="lx-hero-art"
        width="1024"
        height="576"
      >

      <!-- Fichas 3D flotando alrededor del logo -->
      <div class="lx-float-tiles" aria-hidden="true">
        <span class="lx-float-tile lx-ft-l">L</span>
        <span class="lx-float-tile lx-ft-e">E</span>
        <span class="lx-float-tile lx-ft-x">X</span>
        <span class="lx-float-tile lx-ft-o">O</span>
      </div>

      <!-- Emblema 3D LEXO3D con corona y cinta (exacto a la muestra) -->
      <div class="lx-logo-emblem">
        <svg class="lx-logo-svg" viewBox="0 0 580 215" role="img" aria-label="LEXO3D — El desafío de las palabras">
          <defs>
            <linearGradient id="lxGoldGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#FFF078"/>
              <stop offset="48%" stop-color="#FFB800"/>
              <stop offset="100%" stop-color="#FF5A1F"/>
            </linearGradient>
            <linearGradient id="lxWhiteGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#FFFFFF"/>
              <stop offset="68%" stop-color="#F1ECFF"/>
              <stop offset="100%" stop-color="#C9BCEF"/>
            </linearGradient>
            <linearGradient id="lxRibbonGrad" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stop-color="#31106B"/>
              <stop offset="50%" stop-color="#581C87"/>
              <stop offset="100%" stop-color="#31106B"/>
            </linearGradient>
            <filter id="lxLogoShadow" x="-15%" y="-15%" width="130%" height="145%">
              <feDropShadow dx="0" dy="8" stdDeviation="6" flood-color="#130533" flood-opacity="0.72"/>
            </filter>
          </defs>

          <!-- Corona dorada encima de la O -->
          <g transform="translate(270, 4) rotate(-4 38 25)" filter="url(#lxLogoShadow)">
            <path d="M12 44 L4 16 L24 28 L38 6 L52 28 L72 16 L64 44 Z" fill="url(#lxGoldGrad)" stroke="#FFF7C2" stroke-width="2.5" stroke-linejoin="round"/>
            <circle cx="4" cy="14" r="4" fill="#FFF078"/>
            <circle cx="38" cy="4" r="4.5" fill="#FFF078"/>
            <circle cx="72" cy="14" r="4" fill="#FFF078"/>
          </g>

          <!-- Letras 3D L E X O 3 D -->
          <g filter="url(#lxLogoShadow)" font-family="ui-rounded, 'SF Pro Rounded', 'Nunito', system-ui, sans-serif" font-weight="900" text-anchor="middle">
            <!-- Extrusión morada trasera -->
            <g transform="translate(0, 8)" fill="#2D0C6B" stroke="#2D0C6B" stroke-width="14" stroke-linejoin="round">
              <text x="92" y="138" font-size="108" transform="rotate(-6 92 102)">L</text>
              <text x="166" y="133" font-size="108" transform="rotate(-3 166 102)">E</text>
              <text x="242" y="130" font-size="108" transform="rotate(-1 242 102)">X</text>
              <text x="322" y="131" font-size="108" transform="rotate(2 322 102)">O</text>
              <text x="412" y="136" font-size="116" transform="rotate(4 412 102)">3</text>
              <text x="492" y="139" font-size="114" transform="rotate(6 492 102)">D</text>
            </g>
            <!-- Contorno oscuro nítido -->
            <g fill="none" stroke="#1E074A" stroke-width="10" stroke-linejoin="round">
              <text x="92" y="138" font-size="108" transform="rotate(-6 92 102)">L</text>
              <text x="166" y="133" font-size="108" transform="rotate(-3 166 102)">E</text>
              <text x="242" y="130" font-size="108" transform="rotate(-1 242 102)">X</text>
              <text x="322" y="131" font-size="108" transform="rotate(2 322 102)">O</text>
              <text x="412" y="136" font-size="116" transform="rotate(4 412 102)">3</text>
              <text x="492" y="139" font-size="114" transform="rotate(6 492 102)">D</text>
            </g>
            <!-- Relleno frontal brillante -->
            <text x="92" y="138" font-size="108" fill="url(#lxWhiteGrad)" transform="rotate(-6 92 102)">L</text>
            <text x="166" y="133" font-size="108" fill="url(#lxWhiteGrad)" transform="rotate(-3 166 102)">E</text>
            <text x="242" y="130" font-size="108" fill="url(#lxWhiteGrad)" transform="rotate(-1 242 102)">X</text>
            <text x="322" y="131" font-size="108" fill="url(#lxWhiteGrad)" transform="rotate(2 322 102)">O</text>
            <text x="412" y="136" font-size="116" fill="url(#lxGoldGrad)" transform="rotate(4 412 102)">3</text>
            <text x="492" y="139" font-size="114" fill="url(#lxGoldGrad)" transform="rotate(6 492 102)">D</text>
          </g>

          <!-- Cinta inferior -->
          <g transform="translate(106, 152) rotate(-2.5 184 22)" filter="url(#lxLogoShadow)">
            <rect x="0" y="0" width="368" height="40" rx="14" fill="url(#lxRibbonGrad)" stroke="#FFB800" stroke-width="3"/>
            <text x="184" y="26" text-anchor="middle" fill="#FFFFFF" font-family="ui-rounded, 'SF Pro Rounded', system-ui, sans-serif" font-weight="900" font-size="17" letter-spacing="1.4">EL DESAFÍO DE LAS PALABRAS</text>
          </g>
        </svg>
      </div>
    </div>

    <p class="lx-arcade-subtitle">
      Compartán un mismo Lexo3D en la mesa. La app sincroniza el reloj de la sala, registra tus palabras en privado y corona al ganador.
    </p>

    <!-- 3 Tarjetas Glossy estilo Videojuego -->
    <div class="lx-arcade-cards-3">
      <div class="lx-gloss-card lx-gloss-blue">
        <div class="lx-gloss-icon-wrap">
          <img src="/assets/img/lexo3d-device.png" alt="Lexo3D" class="lx-gloss-img" width="90" height="72">
        </div>
        <div class="lx-gloss-title">1. Lexo3D</div>
        <div class="lx-gloss-sub">Mismo juego en la mesa</div>
      </div>

      <div class="lx-gloss-card lx-gloss-purple">
        <div class="lx-gloss-icon-wrap" aria-hidden="true">
          <svg width="64" height="64" viewBox="0 0 64 64">
            <rect x="26" y="4" width="12" height="7" rx="3" fill="#FDE047" stroke="#B45309" stroke-width="2"/>
            <circle cx="32" cy="35" r="23" fill="#FB7185" stroke="#FFF1F2" stroke-width="3"/>
            <circle cx="32" cy="35" r="17" fill="#FFFDF8"/>
            <path d="M32 35 L32 23 M32 35 L41 39" stroke="#4C1D95" stroke-width="4" stroke-linecap="round"/>
            <circle cx="32" cy="35" r="3" fill="#FF5A36"/>
          </svg>
        </div>
        <div class="lx-gloss-title">2. Contra reloj</div>
        <div class="lx-gloss-sub">Anotá desde el móvil</div>
      </div>

      <div class="lx-gloss-card lx-gloss-amber">
        <div class="lx-gloss-icon-wrap" aria-hidden="true">
          <svg width="64" height="64" viewBox="0 0 64 64">
            <defs>
              <linearGradient id="lxStar3d" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#FFF59D"/>
                <stop offset="55%" stop-color="#FFC107"/>
                <stop offset="100%" stop-color="#F57C00"/>
              </linearGradient>
            </defs>
            <path d="M32 6 L39.8 22.2 L57.6 24.6 L44.6 37 L47.8 54.8 L32 46.2 L16.2 54.8 L19.4 37 L6.4 24.6 L24.2 22.2 Z" fill="url(#lxStar3d)" stroke="#FFF9C4" stroke-width="2.5" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="lx-gloss-title">3. Podio y ★</div>
        <div class="lx-gloss-sub">Sumá XP y rachas</div>
      </div>
    </div>

    <?php if (!empty($user) && !empty($profile)): ?>
      <div class="lx-arcade-playerbar">
        <div style="display:flex;align-items:center;gap:12px;">
          <span class="lx-avatar" style="background:<?= Response::e($user['avatar_color']) ?>">
            <?= Response::e(mb_strtoupper(mb_substr($user['display_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
          </span>
          <div>
            <div style="font-weight:900;font-size:1rem;color:#FFFFFF;">¡Hola, <?= Response::e($user['display_name']) ?>!</div>
            <div style="font-size:0.82rem;color:#DDD6FE;font-weight:700;">Nivel <?= (int) $profile['level'] ?> · <?= (int) ($profile['stats']['stars_total'] ?? 0) ?> ★ · <?= (int) ($profile['stats']['matches_won'] ?? 0) ?> victorias</div>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="/perfil" class="lx-btn lx-btn-cream lx-btn-sm">Ver historial</a>
          <?php if ((int) $user['is_guest'] === 1 && $googleReady): ?>
            <a href="/auth/google" class="lx-btn lx-btn-amber lx-btn-sm">Guardar con Google</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- Columna Derecha: Tarjeta Luminosa Blanca (exacta a la muestra) -->
  <section class="lx-arcade-panel">
    <?php if (empty($user)): ?>
      <div class="lx-panel-hero-top">
        <div class="lx-gamepad-badge" aria-hidden="true">
          <svg width="96" height="58" viewBox="0 0 96 58" fill="none">
            <defs>
              <linearGradient id="lxPadGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#8B5CF6"/>
                <stop offset="55%" stop-color="#5B21B6"/>
                <stop offset="100%" stop-color="#3B0764"/>
              </linearGradient>
            </defs>
            <!-- Destellos amarillos -->
            <path d="M12 22 L4 18 M10 30 L2 32 M16 38 L10 43" stroke="#FBBF24" stroke-width="3" stroke-linecap="round"/>
            <path d="M84 22 L92 18 M86 30 L94 32 M80 38 L86 43" stroke="#FBBF24" stroke-width="3" stroke-linecap="round"/>
            <!-- Cuerpo gamepad -->
            <path d="M32 14h32c10 0 17 8 19 20l2 10c1 6-5 10-10 7l-9-6H30l-9 6c-5 3-11-1-10-7l2-10c2-12 9-20 19-20z" fill="url(#lxPadGrad)" stroke="#C4B5FD" stroke-width="2"/>
            <!-- Cruceta -->
            <path d="M31 24v10M26 29h10" stroke="#F8FAFC" stroke-width="3.6" stroke-linecap="round"/>
            <!-- Botones colores -->
            <circle cx="62" cy="25" r="2.8" fill="#FDE047"/>
            <circle cx="68" cy="30" r="2.8" fill="#FB7185"/>
            <circle cx="56" cy="30" r="2.8" fill="#38BDF8"/>
            <circle cx="62" cy="35" r="2.8" fill="#34D399"/>
          </svg>
        </div>
        <h1 class="lx-panel-title lx-text-center">¡Comenzá a jugar!</h1>
        <p class="lx-panel-sub lx-text-center">Elegí cómo querés ingresar a Lexo3D.</p>
      </div>

      <div class="lx-guest-box">
        <div class="lx-guest-box-head">
          <span class="lx-guest-users-icon" aria-hidden="true">
            <svg width="36" height="28" viewBox="0 0 36 28" fill="none">
              <circle cx="18" cy="8" r="5" fill="#6D28D9"/>
              <path d="M8 25c0-4.8 4.4-8 10-8s10 3.2 10 8" fill="#6D28D9"/>
              <circle cx="8" cy="11" r="3.6" fill="#8B5CF6"/>
              <path d="M1 24c0-3.4 2.8-5.6 6.5-5.6" stroke="#8B5CF6" stroke-width="4" stroke-linecap="round"/>
              <circle cx="28" cy="11" r="3.6" fill="#8B5CF6"/>
              <path d="M35 24c0-3.4-2.8-5.6-6.5-5.6" stroke="#8B5CF6" stroke-width="4" stroke-linecap="round"/>
            </svg>
          </span>
          <div>
            <div class="lx-guest-box-title">Ingresar como invitado</div>
            <div class="lx-guest-box-sub">Escribí tu apodo en la mesa y jugá sin registrarte.</div>
          </div>
        </div>

        <form action="/auth/guest" method="post" style="display:flex;flex-direction:column;gap:12px;">
          <input type="hidden" name="csrf_token" value="<?= Response::e($csrf) ?>">
          <input type="hidden" name="join_code" value="<?= Response::e($prefillCode ?? '') ?>">

          <div class="lx-field">
            <label for="guest-name" class="visually-hidden">Tu apodo en la mesa</label>
            <div class="lx-input-icon-wrap">
              <span class="lx-input-lead-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="12" cy="8" r="4"/>
                  <path d="M4 20c0-3.6 3.6-6 8-6s8 2.4 8 6"/>
                </svg>
              </span>
              <input
                id="guest-name"
                name="display_name"
                type="text"
                required
                minlength="2"
                maxlength="28"
                placeholder="Tu apodo en la mesa..."
                class="lx-panel-input lx-panel-input-with-icon"
                data-error="guest-name-error"
              >
              <div id="guest-name-error" class="lx-error-msg">⚠️ Ingresá un apodo de al menos 2 letras.</div>
            </div>
          </div>

          <button type="submit" class="lx-btn-create-arcade">
            <span aria-hidden="true" style="font-size:0.9rem;">▶</span>
            <span><?= !empty($prefillCode) ? 'Entrar como invitado' : 'Jugar como invitado' ?></span>
          </button>
        </form>
      </div>

      <div class="lx-or-divider" aria-hidden="true">
        <span>o</span>
      </div>

      <div>
        <a href="/auth/google" class="lx-btn-google">
          <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.7-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8z"/>
            <path fill="#34A853" d="M12 24c3.2 0 6-1.1 8-2.9l-3.9-3c-1.1.7-2.5 1.2-4.1 1.2-3.1 0-5.8-2.1-6.7-5H1.3v3.1C3.3 21.3 7.3 24 12 24z"/>
            <path fill="#FBBC05" d="M5.3 14.3c-.2-.7-.4-1.5-.4-2.3s.2-1.6.4-2.3V6.6H1.3C.5 8.2 0 10 0 12s.5 3.8 1.3 5.4l4-3.1z"/>
            <path fill="#EA4335" d="M12 4.7c1.8 0 3.3.6 4.6 1.8l3.4-3.4C17.9 1.1 15.2 0 12 0 7.3 0 3.3 2.7 1.3 6.6l4 3.1c.9-2.9 3.6-5 6.7-5z"/>
          </svg>
          <span>Iniciar sesión con Google</span>
        </a>
        <p class="lx-panel-note">
          Guardá tu historial, acumulá puntos, estrellas y logros.
        </p>
      </div>

    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <span class="lx-arcade-badge">⚡ Sala en vivo</span>
        <h1 class="lx-panel-title">Unirse a una sala</h1>
        <p class="lx-panel-sub">Ingresá el código que te compartió el anfitrión o creá tu propia sala.</p>
      </div>

      <form action="/join" method="post" style="display:flex;flex-direction:column;gap:12px;">
        <input type="hidden" name="csrf_token" value="<?= Response::e($csrf) ?>">
        <div class="lx-field">
          <label for="join-code-input" class="lx-panel-label">
            <span aria-hidden="true">📲</span> Código de sala
          </label>
          <input
            id="join-code-input"
            name="code"
            type="text"
            required
            value="<?= Response::e($prefillCode ?? '') ?>"
            placeholder="LEX-4K8P"
            class="lx-panel-input lx-input-code"
            data-error="join-code-err"
          >
          <div id="join-code-err" class="lx-error-msg">⚠️ Ingresá un código válido.</div>
        </div>
        <button type="submit" class="lx-btn lx-btn-indigo lx-btn-lg lx-btn-block">Entrar a la sala</button>
      </form>

      <div class="lx-panel-footer" style="display:flex;flex-direction:column;gap:10px;">
        <button type="button" class="lx-btn-arcade-cta" command="show-modal" commandfor="create-room-dialog" data-open-dialog="create-room-dialog">
          <span style="display:inline-flex;align-items:center;gap:10px;">
            <span aria-hidden="true">✨</span>
            <span>Crear nueva sala</span>
          </span>
          <span class="lx-cta-arrow" aria-hidden="true">›</span>
        </button>
        <button type="button" class="lx-btn-create-arcade" command="show-modal" commandfor="practice-dialog" data-open-dialog="practice-dialog">
          <span aria-hidden="true">🎯</span>
          <span>Modo práctica (1 jugador)</span>
        </button>
        <a href="/reglas" class="lx-btn-google">📖 Ver reglas y puntuación</a>
      </div>
    <?php endif; ?>
  </section>
</div>

  <!-- Diálogo Crear Sala (disponible tanto para invitados nuevos como usuarios con sesión) -->
  <dialog id="create-room-dialog" closedby="any" aria-labelledby="create-room-title">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <h1 id="create-room-title" style="margin:0;font-size:1.3rem;font-weight:900;">Configurar nueva sala</h1>
      <button type="button" class="lx-btn-icon" command="close" commandfor="create-room-dialog" data-close-dialog aria-label="Cerrar">✕</button>
    </div>

    <form action="/rooms/create" method="post" style="display:flex;flex-direction:column;gap:14px;">
      <input type="hidden" name="csrf_token" value="<?= Response::e($csrf) ?>">

      <?php if (empty($user)): ?>
        <div class="lx-field">
          <label for="create-guest-name" class="lx-label">Tu apodo en la mesa</label>
          <input
            id="create-guest-name"
            name="display_name"
            type="text"
            required
            minlength="2"
            maxlength="28"
            placeholder="Ej: Sofía, Lucas_99..."
            class="lx-input"
            data-error="create-guest-name-err"
            autofocus
          >
          <div id="create-guest-name-err" class="lx-error-msg">⚠️ Ingresá un apodo de al menos 2 letras.</div>
        </div>
      <?php endif; ?>

      <div class="lx-field">
        <label for="room-name" class="lx-label">Nombre de la sala</label>
        <input id="room-name" name="name" type="text" maxlength="50" value="<?= !empty($user) ? 'Sala de ' . Response::e($user['display_name']) : 'Sala LEXO' ?>" class="lx-input" <?= !empty($user) ? 'autofocus' : '' ?>>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="lx-field">
          <label for="round-count" class="lx-label">Rondas</label>
          <select id="round-count" name="round_count" class="lx-select">
            <?php foreach ($roomCfg['round_counts'] as $rc): ?>
              <option value="<?= (int) $rc ?>" <?= $rc === $roomCfg['default_round_count'] ? 'selected' : '' ?>><?= (int) $rc ?> rondas</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="lx-field">
          <label for="round-duration" class="lx-label">Duración</label>
          <select id="round-duration" name="round_duration_seconds" class="lx-select">
            <?php foreach ($roomCfg['round_durations'] as $sec): ?>
              <option value="<?= (int) $sec ?>" <?= $sec === $roomCfg['default_round_duration'] ? 'selected' : '' ?>><?= (int) $sec ?> seg</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div class="lx-field">
          <label for="max-players" class="lx-label">Máx. jugadores</label>
          <select id="max-players" name="max_players" class="lx-select">
            <?php foreach ($roomCfg['max_players_options'] as $mp): ?>
              <option value="<?= (int) $mp ?>" <?= $mp === $roomCfg['default_max_players'] ? 'selected' : '' ?>><?= (int) $mp ?> jugadores</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="lx-field">
          <label for="min-len" class="lx-label">Mín. de letras</label>
          <select id="min-len" name="min_word_length" class="lx-select">
            <?php foreach ($roomCfg['min_word_lengths'] as $ml): ?>
              <option value="<?= (int) $ml ?>" <?= $ml === $roomCfg['default_min_word_length'] ? 'selected' : '' ?>><?= (int) $ml ?> letras</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="lx-field">
        <label for="scoring-mode" class="lx-label">Puntuación</label>
        <select id="scoring-mode" name="scoring_mode" class="lx-select">
          <?php foreach ($roomCfg['scoring_modes'] as $key => $label): ?>
            <option value="<?= Response::e($key) ?>" <?= $key === $roomCfg['default_scoring_mode'] ? 'selected' : '' ?>><?= Response::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="lx-field">
        <label for="validation-mode" class="lx-label">Validación de palabras</label>
        <select id="validation-mode" name="validation_mode" class="lx-select">
          <?php foreach ($roomCfg['validation_modes'] as $key => $label): ?>
            <?php
              $suffix = ($key === 'dictionary') ? ($dictReady ? " ({$dictCount} palabras)" : ' (no disponible)') : '';
            ?>
            <option value="<?= Response::e($key) ?>" <?= $key === $roomCfg['default_validation_mode'] ? 'selected' : '' ?>><?= Response::e($label . $suffix) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="lx-btn lx-btn-coral lx-btn-lg lx-btn-block">🚀 Abrir sala y obtener código QR</button>
    </form>
  </dialog>

  <?php if (!empty($user)): ?>
    <!-- Diálogo Modo Práctica (1 jugador, sin sala) -->
    <dialog id="practice-dialog" closedby="any" aria-labelledby="practice-title">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h1 id="practice-title" style="margin:0;font-size:1.3rem;font-weight:900;">🎯 Modo práctica (1 jugador)</h1>
        <button type="button" class="lx-btn-icon" command="close" commandfor="practice-dialog" data-close-dialog aria-label="Cerrar">✕</button>
      </div>
      <p class="lx-hint" style="margin:0 0 14px;">Entrená solo con tu Lexo3D contra reloj, sin crear sala ni esperar rivales.</p>

      <form action="/practice/start" method="post" style="display:flex;flex-direction:column;gap:14px;">
        <input type="hidden" name="csrf_token" value="<?= Response::e($csrf) ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div class="lx-field">
            <label for="practice-duration" class="lx-label">Duración</label>
            <select id="practice-duration" name="round_duration_seconds" class="lx-select" autofocus>
              <?php foreach ($roomCfg['round_durations'] as $sec): ?>
                <option value="<?= (int) $sec ?>" <?= $sec === $roomCfg['default_round_duration'] ? 'selected' : '' ?>><?= (int) $sec ?> seg</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="lx-field">
            <label for="practice-min-len" class="lx-label">Mín. de letras</label>
            <select id="practice-min-len" name="min_word_length" class="lx-select">
              <?php foreach ($roomCfg['min_word_lengths'] as $ml): ?>
                <option value="<?= (int) $ml ?>" <?= $ml === $roomCfg['default_min_word_length'] ? 'selected' : '' ?>><?= (int) $ml ?> letras</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="lx-field">
          <label for="practice-scoring" class="lx-label">Puntuación</label>
          <select id="practice-scoring" name="scoring_mode" class="lx-select">
            <?php foreach ($roomCfg['scoring_modes'] as $key => $label): ?>
              <option value="<?= Response::e($key) ?>" <?= $key === $roomCfg['default_scoring_mode'] ? 'selected' : '' ?>><?= Response::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="lx-field">
          <label for="practice-validation" class="lx-label">Validación de palabras</label>
          <select id="practice-validation" name="validation_mode" class="lx-select">
            <?php foreach ($roomCfg['validation_modes'] as $key => $label): ?>
              <?php
                $suffix = ($key === 'dictionary') ? ($dictReady ? " ({$dictCount} palabras)" : ' (no disponible)') : '';
              ?>
              <option value="<?= Response::e($key) ?>" <?= $key === $roomCfg['default_validation_mode'] ? 'selected' : '' ?>><?= Response::e($label . $suffix) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="lx-btn lx-btn-coral lx-btn-lg lx-btn-block">⚡ Empezar práctica ahora</button>
      </form>
    </dialog>
  <?php endif; ?>
