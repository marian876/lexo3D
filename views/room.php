<?php
declare(strict_types=1);

use App\Support\Response;
use App\Support\Session;

$csrf = Session::csrfToken();
$room = $state['room'];
$isPractice = (int) $room['max_players'] === 1;
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px;flex-wrap:wrap;">
  <div style="display:flex;align-items:center;gap:10px;">
    <?php if ($isPractice): ?>
      <span class="lx-chip lx-chip-gold" style="font-weight:900;">🎯 Modo práctica · 1 jugador</span>
    <?php else: ?>
      <span class="lx-chip" style="font-weight:900;color:var(--lx-indigo);">Sala <?= Response::e($room['code']) ?></span>
      <span class="lx-hint"><?= Response::e($room['name']) ?></span>
    <?php endif; ?>
  </div>
  <form action="/rooms/leave" method="post" style="margin:0;">
    <input type="hidden" name="csrf_token" value="<?= Response::e($csrf) ?>">
    <input type="hidden" name="code" value="<?= Response::e($room['code']) ?>">
    <button type="submit" class="lx-btn lx-btn-cream lx-btn-sm"><?= $isPractice ? 'Terminar práctica' : 'Salir de la sala' ?></button>
  </form>
</div>

<div id="lexo-room-app">
  <div class="lx-card" style="text-align:center;padding:40px;">
    Cargando mesa de juego...
  </div>
</div>

<!-- Diálogo Tarjeta Compartible (Stories / WhatsApp) -->
<dialog id="share-dialog" closedby="any" aria-labelledby="share-title">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <h1 id="share-title" style="margin:0;font-size:1.2rem;font-weight:900;">Compartir resultado</h1>
    <button type="button" class="lx-btn-icon" command="close" commandfor="share-dialog" data-close-dialog aria-label="Cerrar">✕</button>
  </div>
  <div style="display:flex;flex-direction:column;gap:12px;align-items:center;">
    <canvas id="share-canvas" width="600" height="720" style="width:100%;max-width:340px;height:auto;border-radius:18px;border:2px solid var(--lx-border);"></canvas>
    <a id="btn-download-share" href="#" download="lexo-resultado.png" class="lx-btn lx-btn-coral lx-btn-block">⬇️ Descargar imagen PNG</a>
  </div>
</dialog>

<script>
  window.__LEXO_INITIAL_STATE__ = <?= json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
