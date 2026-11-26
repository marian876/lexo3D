<?php
declare(strict_types=1);

use App\Support\Response;
use App\Support\Session;

$csrf = Session::csrfToken();
$stats = $summary['stats'];
?>
<div style="display:flex;flex-direction:column;gap:20px;">
  <section class="lx-card" style="display:flex;flex-direction:column;gap:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
      <div style="display:flex;align-items:center;gap:14px;">
        <span class="lx-avatar lx-avatar-lg" style="background:<?= Response::e($user['avatar_color']) ?>">
          <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= Response::e($user['avatar_url']) ?>" alt="" referrerpolicy="no-referrer">
          <?php else: ?>
            <?= Response::e(mb_strtoupper(mb_substr($user['display_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?>
          <?php endif; ?>
        </span>
        <div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <h1 style="margin:0;font-size:1.5rem;font-weight:900;"><?= Response::e($user['display_name']) ?></h1>
            <?php if ((int) $user['is_guest'] === 1): ?>
              <span class="lx-chip">Invitado</span>
            <?php else: ?>
              <span class="lx-chip lx-chip-green">Google vinculado</span>
            <?php endif; ?>
          </div>
          <div class="lx-hint">Nivel <?= (int) $summary['level'] ?> · <?= (int) $summary['xp_into_level'] ?> / <?= (int) $summary['xp_for_next'] ?> XP para el siguiente nivel</div>
        </div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ((int) $user['is_guest'] === 1 && $googleReady): ?>
          <a href="/auth/google" class="lx-btn lx-btn-amber lx-btn-sm">🔐 Vincular con Google</a>
        <?php endif; ?>
        <form action="/auth/logout" method="post" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?= Response::e($csrf) ?>">
          <button type="submit" class="lx-btn lx-btn-cream lx-btn-sm">Cerrar sesión</button>
        </form>
      </div>
    </div>

    <div class="lx-progress-track">
      <div class="lx-progress-fill" style="width:<?= (int) $summary['level_progress_pct'] ?>%;background:linear-gradient(90deg,var(--lx-indigo),var(--lx-emerald));"></div>
    </div>

    <form action="/auth/profile" method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= Response::e($csrf) ?>">
      <div class="lx-field" style="flex:1;min-width:200px;">
        <label for="profile-nick" class="lx-label">Cambiar apodo visible</label>
        <input id="profile-nick" name="display_name" type="text" required minlength="2" maxlength="28" value="<?= Response::e($user['display_name']) ?>" class="lx-input">
      </div>
      <button type="submit" class="lx-btn lx-btn-indigo" style="height:49px;">Guardar apodo</button>
    </form>
  </section>

  <section class="lx-stats-grid">
    <div class="lx-stat-tile">
      <div class="lx-stat-num tabular-nums" style="color:var(--lx-amber);"><?= (int) ($stats['stars_total'] ?? 0) ?> ★</div>
      <div class="lx-stat-label">Estrellas</div>
    </div>
    <div class="lx-stat-tile">
      <div class="lx-stat-num tabular-nums" style="color:var(--lx-indigo);"><?= (int) ($stats['competitive_points_total'] ?? 0) ?></div>
      <div class="lx-stat-label">Puntos totales</div>
    </div>
    <div class="lx-stat-tile">
      <div class="lx-stat-num tabular-nums" style="color:var(--lx-emerald);"><?= (int) ($stats['matches_won'] ?? 0) ?> / <?= (int) ($stats['matches_played'] ?? 0) ?></div>
      <div class="lx-stat-label">Victorias (<?= (int) $summary['win_rate'] ?>%)</div>
    </div>
    <div class="lx-stat-tile">
      <div class="lx-stat-num tabular-nums" style="color:var(--lx-coral);"><?= (int) ($stats['best_win_streak'] ?? 0) ?> 🔥</div>
      <div class="lx-stat-label">Mejor racha</div>
    </div>
  </section>

  <section class="lx-card">
    <h2 style="margin:0 0 12px;font-size:1.15rem;font-weight:900;">Logros e insignias</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;">
      <?php foreach ($achievements as $ach): ?>
        <?php $unlocked = !empty($ach['unlocked_at']); ?>
        <div class="lx-card-soft" style="opacity:<?= $unlocked ? '1' : '0.55' ?>;<?= $unlocked ? 'border-color:var(--lx-amber);' : '' ?>">
          <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:1.6rem;"><?= Response::e($ach['icon']) ?></span>
            <div>
              <div style="font-weight:900;font-size:0.92rem;"><?= Response::e($ach['name']) ?></div>
              <div class="lx-hint" style="font-size:0.78rem;"><?= Response::e($ach['description']) ?></div>
            </div>
          </div>
          <div style="margin-top:8px;font-size:0.75rem;font-weight:800;color:<?= $unlocked ? 'var(--lx-emerald)' : 'var(--lx-muted)' ?>;">
            <?= $unlocked ? '✓ Desbloqueado' : '🔒 +' . (int) $ach['reward_experience'] . ' XP · +' . (int) $ach['reward_stars'] . ' ★' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="lx-card">
    <h2 style="margin:0 0 12px;font-size:1.15rem;font-weight:900;">Historial de partidas</h2>
    <?php if (empty($history)): ?>
      <p class="lx-hint" style="margin:0;">Todavía no completaste ninguna partida. ¡Creá o unite a una mesa para estrenar tu historial!</p>
    <?php else: ?>
      <div class="lx-table-wrap">
        <table class="lx-table">
          <thead>
            <tr><th>Fecha</th><th>Mesa</th><th>Puesto</th><th>Rondas</th><th>Palabras</th><th>Puntos</th><th>Recompensa</th></tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <?php $isPracticeRow = (int) ($h['max_players'] ?? 2) === 1; ?>
              <tr>
                <td class="lx-hint"><?= Response::e(substr((string) $h['started_at'], 0, 16)) ?></td>
                <td>
                  <?php if ($isPracticeRow): ?>
                    🎯 Modo práctica
                  <?php else: ?>
                    <?= Response::e($h['room_name']) ?> <span class="lx-hint">(<?= Response::e($h['room_code']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td><?= $isPracticeRow ? '—' : ((int) $h['final_position'] . 'º') ?></td>
                <td class="tabular-nums"><?= $isPracticeRow ? 'Práctica' : ((int) $h['rounds_won'] . '/' . (int) $h['round_count_snapshot']) ?></td>
                <td class="tabular-nums"><?= (int) $h['valid_word_count'] ?></td>
                <td class="tabular-nums"><?= (int) $h['competitive_points'] ?></td>
                <td class="tabular-nums">+<?= (int) $h['experience_earned'] ?> XP <?= (int) $h['stars_earned'] > 0 ? '· +' . (int) $h['stars_earned'] . ' ★' : '' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>
